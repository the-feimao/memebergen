<?php

namespace App\Http\Controllers;

use App\Exports\MembershipRegisterExport;
use App\Models\Member;
use App\Models\MembershipGrade;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MembershipRegisterController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);

        $members = $this->filteredQuery($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Member $member): array => [
                'id' => $member->id,
                'membership_number' => $member->membership_number,
                'full_name' => $member->full_name,
                'registration_date' => ($member->registration_date ?? $member->created_at)?->toDateString(),
                'grade' => [
                    'id' => $member->grade->id,
                    'name' => $member->grade->name,
                ],
            ]);

        return Inertia::render('members/index', [
            'members' => $members,
            'grades' => MembershipGrade::query()->orderBy('name')->pluck('name'),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'grade' => $filters['grade'] ?? '',
                'registration_from' => $filters['registration_from'] ?? '',
                'registration_to' => $filters['registration_to'] ?? '',
            ],
            'can' => [
                'export' => $request->user()?->can('membership.register.export') ?? false,
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        $this->audit->record('membership.register.exported', null, [
            'filters' => array_filter($filters, static fn ($value): bool => $value !== null && $value !== ''),
        ], $request->user());

        return Excel::download(
            new MembershipRegisterExport($this->filteredQuery($filters)),
            'nim-membership-register.xlsx',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', Rule::exists('membership_grades', 'name')],
            'registration_from' => ['nullable', 'date'],
            'registration_to' => [
                'nullable',
                'date',
                Rule::when(
                    $request->filled('registration_from'),
                    ['after_or_equal:registration_from'],
                ),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Member>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Member::query()
            ->with('grade:id,name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('membership_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%");
                });
            })
            ->when($filters['grade'] ?? null, function (Builder $query, string $grade): void {
                $query->whereHas('grade', fn (Builder $gradeQuery) => $gradeQuery->where('name', $grade));
            })
            ->when(
                $filters['registration_from'] ?? null,
                fn (Builder $query, string $date) => $query->where('registration_date', '>=', $date),
            )
            ->when(
                $filters['registration_to'] ?? null,
                fn (Builder $query, string $date) => $query->where('registration_date', '<=', $date),
            )
            ->orderBy('full_name')
            ->orderBy('membership_number');
    }

    public function show(Member $member): Response
    {
        $member->load([
            'grade:id,name',
            'history.previousGrade:id,name',
            'history.newGrade:id,name',
            'history.changedBy:id,name',
            'upgrades.previousGrade:id,name',
            'upgrades.newGrade:id,name',
            'upgrades.upgradeType:id,name',
            'upgrades.batch:id,batch_reference',
            'upgrades.processedBy:id,name',
        ]);

        return Inertia::render('members/show', [
            'member' => [
                'id' => $member->id,
                'membership_number' => $member->membership_number,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'state' => $member->state,
                'registration_date' => ($member->registration_date ?? $member->created_at)?->toDateString(),
                'grade' => [
                    'id' => $member->grade->id,
                    'name' => $member->grade->name,
                ],
                'history' => $member->history->map(static fn ($entry): array => [
                    'id' => $entry->id,
                    'change_type' => $entry->change_type,
                    'changed_at' => $entry->changed_at?->toISOString(),
                    'previous_grade' => $entry->previousGrade ? [
                        'id' => $entry->previousGrade->id,
                        'name' => $entry->previousGrade->name,
                    ] : null,
                    'new_grade' => [
                        'id' => $entry->newGrade->id,
                        'name' => $entry->newGrade->name,
                    ],
                    'changed_by' => $entry->changedBy ? [
                        'id' => $entry->changedBy->id,
                        'name' => $entry->changedBy->name,
                    ] : null,
                ])->values(),
                'upgrades' => $member->upgrades->map(static fn ($upgrade): array => [
                    'id' => $upgrade->id,
                    'previous_grade' => $upgrade->previousGrade ? [
                        'id' => $upgrade->previousGrade->id,
                        'name' => $upgrade->previousGrade->name,
                    ] : null,
                    'new_grade' => $upgrade->newGrade ? [
                        'id' => $upgrade->newGrade->id,
                        'name' => $upgrade->newGrade->name,
                    ] : null,
                    'upgrade_type' => $upgrade->upgradeType ? [
                        'id' => $upgrade->upgradeType->id,
                        'name' => $upgrade->upgradeType->name,
                    ] : null,
                    'batch' => $upgrade->batch ? [
                        'id' => $upgrade->batch->id,
                        'batch_reference' => $upgrade->batch->batch_reference,
                    ] : null,
                    'processed_by' => $upgrade->processedBy ? [
                        'id' => $upgrade->processedBy->id,
                        'name' => $upgrade->processedBy->name,
                    ] : null,
                    'effective_date' => $upgrade->updated_at?->toDateString(),
                    'created_at' => $upgrade->created_at?->toISOString(),
                ])->values(),
            ],
        ]);
    }
}
