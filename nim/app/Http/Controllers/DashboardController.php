<?php

namespace App\Http\Controllers;

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
use App\Models\MembershipUpgrade;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $latestBatches = MembershipBatch::query()
            ->with('uploadedBy:id,name')
            ->latest('uploaded_at')
            ->limit(5)
            ->get();

        return Inertia::render('dashboard', [
            'metrics' => [
                'members' => Member::query()->count(),
                'newMembersThisMonth' => Member::query()
                    ->whereNotNull('registration_date')
                    ->whereBetween('registration_date', [$monthStart, $monthEnd])
                    ->count(),
                'upgradesThisMonth' => MembershipUpgrade::query()
                    ->whereNotNull('effective_date')
                    ->whereBetween('effective_date', [$monthStart, $monthEnd])
                    ->count(),
                'awaitingReview' => MembershipBatch::query()
                    ->where('status', MembershipBatchStatus::ReadyForReview)
                    ->count(),
            ],
            'latestBatches' => $latestBatches->map(static fn (MembershipBatch $batch): array => [
                'batch_reference' => $batch->batch_reference,
                'batch_type' => $batch->batch_type === MembershipBatchType::Upgrade
                    ? 'Membership upgrades'
                    : 'New registrations',
                'status' => $batch->status->value,
                'uploaded_by' => $batch->uploadedBy?->name ?? 'Unknown user',
                'uploaded_at' => $batch->uploaded_at?->toISOString(),
                'review_url' => $batch->batch_type === MembershipBatchType::Upgrade
                    ? "/upgrade-batches/{$batch->batch_reference}"
                    : "/membership-batches/{$batch->batch_reference}",
            ])->all(),
            'gradeBreakdown' => MembershipGrade::query()
                ->withCount('members')
                ->orderByDesc('members_count')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (MembershipGrade $grade): array => [
                    'name' => $grade->name,
                    'count' => $grade->members_count,
                ])->all(),
        ]);
    }
}
