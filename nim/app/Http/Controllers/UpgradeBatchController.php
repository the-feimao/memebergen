<?php

namespace App\Http\Controllers;

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Exports\UpgradeBatchResultsExport;
use App\Exports\UpgradeMembershipTemplateExport;
use App\Http\Requests\StoreUpgradeBatchRequest;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\UpgradeType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MembershipGradeResolver;
use App\Services\MembershipUpgradeImportService;
use App\Services\SystemSettings;
use App\Services\UpgradeBatchProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpgradeBatchController extends Controller
{
    public function __construct(
        private readonly MembershipGradeResolver $gradeResolver,
        private readonly AuditLogger $audit,
        private readonly SystemSettings $settings,
    ) {}

    public function create(Request $request): Response
    {
        $upgradeTypes = UpgradeType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $recentBatches = $request->user()?->can('membership.batches.view')
            ? MembershipBatch::query()
                ->with('upgradeType:id,name')
                ->where('batch_type', MembershipBatchType::Upgrade)
                ->latest('uploaded_at')
                ->limit(10)
                ->get()
                ->map(static fn (MembershipBatch $batch): array => [
                    'batch_reference' => $batch->batch_reference,
                    'status' => $batch->status->value,
                    'upgrade_type' => $batch->upgradeType?->name,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'failed_rows' => $batch->failed_rows,
                    'uploaded_at' => $batch->uploaded_at?->toISOString(),
                ])
            : collect();

        return Inertia::render('upgrade-batches/create', [
            'maxFileSizeKilobytes' => $this->settings->getInt(SystemSettings::IMPORT_MAX_KILOBYTES),
            'maxRows' => $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS),
            'upgradeTypes' => $upgradeTypes,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function store(
        StoreUpgradeBatchRequest $request,
        MembershipUpgradeImportService $service,
    ): RedirectResponse {
        $file = $request->file('file');
        $user = $request->user();
        $upgradeType = UpgradeType::query()
            ->whereKey($request->integer('upgrade_type_id'))
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless($file instanceof UploadedFile, 422);
        abort_unless($user instanceof User, 403);

        $batch = $service->store($file, $user, $upgradeType);

        $this->audit->record('membership.upgrade_batch.uploaded', $batch, [
            ...$this->batchMetadata($batch),
            'upgrade_type' => $upgradeType->name,
        ], $user);

        return redirect()->route('upgrade-batches.show', $batch);
    }

    public function show(Request $request, MembershipBatch $membershipBatch): Response
    {
        abort_unless($membershipBatch->batch_type === MembershipBatchType::Upgrade, 404);

        $gradeResolver = $this->gradeResolver;

        $rows = $membershipBatch->rows()
            ->with('previousGrade:id,name')
            ->orderBy('row_number')
            ->paginate(50)
            ->through(static function (MembershipBatchRow $row) use ($gradeResolver): array {
                $currentGrade = $row->grade_value ?? $row->previousGrade?->name;

                return [
                    'row_number' => $row->row_number,
                    'supplied_membership_number' => $row->supplied_membership_number,
                    'supplied_full_name' => $row->supplied_full_name ?? $row->full_name,
                    'current_grade' => $currentGrade,
                    'intended_grade' => $gradeResolver->nextGradeName($currentGrade)
                        ?? $row->intended_grade_value,
                    'status' => $row->status->value,
                    'errors' => $row->validation_errors ?? [],
                    'warnings' => $row->validation_warnings ?? [],
                ];
            });

        $membershipBatch->loadMissing('upgradeType:id,name');
        $user = $request->user();

        return Inertia::render('upgrade-batches/show', [
            'batch' => [
                'batch_reference' => $membershipBatch->batch_reference,
                'status' => $membershipBatch->status->value,
                'upgrade_type' => $membershipBatch->upgradeType?->name,
                'original_filename' => $membershipBatch->original_filename,
                'detected_headings' => $membershipBatch->detected_headings ?? [],
                'total_rows' => $membershipBatch->total_rows,
                'valid_rows' => $membershipBatch->valid_rows,
                'failed_rows' => $membershipBatch->failed_rows,
                'warning_rows' => $membershipBatch->warning_rows,
                'successful_rows' => $membershipBatch->successful_rows,
                'failure_reason' => $membershipBatch->failure_reason,
                'uploaded_at' => $membershipBatch->uploaded_at?->toISOString(),
                'processed_at' => $membershipBatch->processed_at?->toISOString(),
            ],
            'rows' => $rows,
            'can' => [
                'process' => $user?->can('membership.upgrades.process') ?? false,
                'export' => $user?->can('membership.upgrades.export') ?? false,
            ],
        ]);
    }

    public function process(
        Request $request,
        MembershipBatch $membershipBatch,
        UpgradeBatchProcessor $processor,
    ): RedirectResponse {
        abort_unless($membershipBatch->batch_type === MembershipBatchType::Upgrade, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($membershipBatch->status !== MembershipBatchStatus::ReadyForReview) {
            return back()->withErrors(['batch' => 'Only batches that are ready for review can be processed.']);
        }

        try {
            $batch = $processor->process($membershipBatch, $user);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch' => $exception->getMessage()]);
        }

        $this->audit->record('membership.upgrade_batch.processed', $batch, [
            ...$this->batchMetadata($batch),
            'successful_rows' => $batch->successful_rows,
            'processed_at' => $batch->processed_at?->toISOString(),
        ], $user);

        return redirect()
            ->route('upgrade-batches.show', $batch)
            ->with('success', 'Upgrade batch processed. Membership numbers were retained and grade changes recorded.');
    }

    public function template(Request $request): BinaryFileResponse
    {
        $this->audit->record('membership.upgrade_template.downloaded', null, [
            'template' => 'membership_upgrade',
        ], $request->user());

        return Excel::download(
            new UpgradeMembershipTemplateExport,
            'nim-membership-upgrade-template.xlsx',
        );
    }

    public function exportResults(Request $request, MembershipBatch $membershipBatch): BinaryFileResponse
    {
        abort_unless($membershipBatch->batch_type === MembershipBatchType::Upgrade, 404);

        $this->audit->record('membership.upgrade_batch.exported', $membershipBatch, [
            'batch_reference' => $membershipBatch->batch_reference,
        ], $request->user());

        return Excel::download(
            new UpgradeBatchResultsExport($membershipBatch),
            "upgrade-batch-{$membershipBatch->batch_reference}-results.xlsx",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function batchMetadata(MembershipBatch $batch): array
    {
        return [
            'batch_reference' => $batch->batch_reference,
            'original_filename' => $batch->original_filename,
            'status' => $batch->status->value,
            'upgrade_type' => $batch->upgradeType?->name,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'failed_rows' => $batch->failed_rows,
            'warning_rows' => $batch->warning_rows,
        ];
    }
}
