<?php

namespace App\Http\Controllers;

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Models\MembershipBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BatchHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(MembershipBatchType::class)],
            'status' => ['nullable', Rule::enum(MembershipBatchStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                Rule::when($request->filled('from'), ['after_or_equal:from']),
            ],
        ]);

        $batches = $this->filteredQuery($filters)
            ->with('uploadedBy:id,name')
            ->latest('uploaded_at')
            ->paginate(20)
            ->withQueryString()
            ->through(static fn (MembershipBatch $batch): array => [
                'id' => $batch->id,
                'batch_reference' => $batch->batch_reference,
                'batch_type' => $batch->batch_type->value,
                'batch_type_label' => $batch->batch_type === MembershipBatchType::Upgrade
                    ? 'Membership upgrades'
                    : 'New registrations',
                'status' => $batch->status->value,
                'uploaded_by' => $batch->uploadedBy?->name ?? 'Unknown user',
                'uploaded_at' => $batch->uploaded_at?->toISOString(),
                'processed_at' => $batch->processed_at?->toISOString(),
                'original_filename' => $batch->original_filename,
                'total_rows' => $batch->total_rows,
                'successful_rows' => $batch->successful_rows,
                'failed_rows' => $batch->failed_rows,
                'warning_rows' => $batch->warning_rows,
                'failure_reason' => $batch->failure_reason,
                'review_url' => $batch->batch_type === MembershipBatchType::Upgrade
                    ? "/upgrade-batches/{$batch->batch_reference}"
                    : "/membership-batches/{$batch->batch_reference}",
            ]);

        return Inertia::render('batch-history/index', [
            'batches' => $batches,
            'types' => array_map(
                static fn (MembershipBatchType $type): array => [
                    'value' => $type->value,
                    'label' => Str::headline($type->value),
                ],
                MembershipBatchType::cases(),
            ),
            'statuses' => array_map(
                static fn (MembershipBatchStatus $status): array => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                ],
                MembershipBatchStatus::cases(),
            ),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'stats' => [
                'total' => MembershipBatch::query()->count(),
                'awaiting_review' => MembershipBatch::query()
                    ->where('status', MembershipBatchStatus::ReadyForReview)
                    ->count(),
                'completed' => MembershipBatch::query()
                    ->whereIn('status', [
                        MembershipBatchStatus::Completed,
                        MembershipBatchStatus::CompletedWithErrors,
                    ])
                    ->count(),
                'with_errors' => MembershipBatch::query()
                    ->where(function (Builder $query): void {
                        $query->where('status', MembershipBatchStatus::Failed)
                            ->orWhere('failed_rows', '>', 0);
                    })
                    ->count(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<MembershipBatch>
     */
    private function filteredQuery(array $filters): Builder
    {
        return MembershipBatch::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('batch_reference', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%")
                        ->orWhere('failure_reason', 'like', "%{$search}%")
                        ->orWhereHas('uploadedBy', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type) => $query->where('batch_type', $type),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('uploaded_at', '>=', $date),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('uploaded_at', '<=', $date),
            );
    }
}
