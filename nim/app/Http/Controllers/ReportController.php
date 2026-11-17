<?php

namespace App\Http\Controllers;

use App\Exports\MembershipRegisterExport;
use App\Exports\UpgradeReportExport;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
use App\Models\MembershipUpgrade;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $members = $this->filteredMembers($filters);
        $upgrades = $this->filteredUpgrades($filters);

        return Inertia::render('reports/index', [
            'summary' => [
                'members' => (clone $members)->count(),
                'upgrades' => (clone $upgrades)->count(),
                'batches' => MembershipBatch::query()->count(),
                'failed_rows' => (int) MembershipBatch::query()->sum('failed_rows'),
            ],
            'gradeBreakdown' => (clone $members)
                ->selectRaw('membership_grade_id, count(*) as total')
                ->groupBy('membership_grade_id')
                ->with('grade:id,name')
                ->get()
                ->map(static fn (Member $member): array => [
                    'name' => $member->grade?->name ?? 'Unassigned',
                    'count' => (int) $member->getAttribute('total'),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'upgradeTypeBreakdown' => (clone $upgrades)
                ->selectRaw('upgrade_type_id, count(*) as total')
                ->groupBy('upgrade_type_id')
                ->with('upgradeType:id,name')
                ->get()
                ->map(static fn (MembershipUpgrade $upgrade): array => [
                    'name' => $upgrade->upgradeType?->name ?? 'Unspecified',
                    'count' => (int) $upgrade->getAttribute('total'),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'monthlyRegistrations' => $this->monthlyCounts($members, 'registration_date'),
            'monthlyUpgrades' => $this->monthlyCounts($upgrades, 'effective_date'),
            'grades' => MembershipGrade::query()->orderBy('name')->pluck('name'),
            'filters' => [
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'grade' => $filters['grade'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
            'can' => [
                'exportRegister' => $request->user()?->can('membership.register.export') ?? false,
                'exportUpgrades' => $request->user()?->can('membership.upgrades.export') ?? false,
            ],
        ]);
    }

    public function exportRegister(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        $this->audit->record('reports.register_exported', null, [
            'filters' => array_filter($filters, static fn ($value): bool => $value !== null && $value !== ''),
        ], $request->user());

        return Excel::download(
            new MembershipRegisterExport($this->filteredMembers($filters)),
            'nim-membership-report.xlsx',
        );
    }

    public function exportUpgrades(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        $this->audit->record('reports.upgrades_exported', null, [
            'filters' => array_filter($filters, static fn ($value): bool => $value !== null && $value !== ''),
        ], $request->user());

        return Excel::download(
            new UpgradeReportExport($this->filteredUpgrades($filters)),
            'nim-upgrade-report.xlsx',
        );
    }

    /**
     * @return array<string, string>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                Rule::when($request->filled('from'), ['after_or_equal:from']),
            ],
            'grade' => ['nullable', 'string', Rule::exists('membership_grades', 'name')],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Member>
     */
    private function filteredMembers(array $filters): Builder
    {
        return Member::query()
            ->with('grade:id,name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('membership_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['grade'] ?? null,
                fn (Builder $query, string $grade) => $query->whereHas('grade', fn (Builder $gradeQuery) => $gradeQuery->where('name', $grade)),
            )
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $date) => $query->where('registration_date', '>=', $date),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $date) => $query->where('registration_date', '<=', $date),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<MembershipUpgrade>
     */
    private function filteredUpgrades(array $filters): Builder
    {
        return MembershipUpgrade::query()
            ->with([
                'member:id,membership_number,full_name,email',
                'previousGrade:id,name',
                'newGrade:id,name',
                'upgradeType:id,name',
                'batch:id,batch_reference',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery
                        ->where('membership_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('batch', fn (Builder $batchQuery) => $batchQuery->where('batch_reference', 'like', "%{$search}%"));
                });
            })
            ->when(
                $filters['grade'] ?? null,
                fn (Builder $query, string $grade) => $query->whereHas('newGrade', fn (Builder $gradeQuery) => $gradeQuery->where('name', $grade)),
            )
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $date) => $query->where('effective_date', '>=', $date),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $date) => $query->where('effective_date', '<=', $date),
            );
    }

    /**
     * @param  Builder<Member>|Builder<MembershipUpgrade>  $query
     * @return list<array{label: string, count: int}>
     */
    private function monthlyCounts(Builder $query, string $column): array
    {
        $counts = [];

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $start = $month->toDateString();
            $end = $month->endOfMonth()->toDateString();

            $counts[] = [
                'label' => $month->format('M Y'),
                'count' => (clone $query)->whereBetween($column, [$start, $end])->count(),
            ];
        }

        return $counts;
    }
}
