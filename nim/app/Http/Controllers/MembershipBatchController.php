<?php

namespace App\Http\Controllers;

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Exports\NewMembershipTemplateExport;
use App\Http\Requests\StoreMembershipBatchRequest;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NewMembershipBatchProcessor;
use App\Services\NewMembershipImportService;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MembershipBatchController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SystemSettings $settings,
    ) {}

    public function create(Request $request): Response
    {
        $recentBatches = $request->user()?->can('membership.batches.view')
            ? MembershipBatch::query()
                ->where('batch_type', MembershipBatchType::NewRegistration)
                ->latest('uploaded_at')
                ->limit(10)
                ->get()
                ->map(static fn (MembershipBatch $batch): array => [
                    'batch_reference' => $batch->batch_reference,
                    'status' => $batch->status->value,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'failed_rows' => $batch->failed_rows,
                    'uploaded_at' => $batch->uploaded_at->toISOString(),
                ])
            : collect();

        return Inertia::render('membership-batches/create', [
            'maxFileSizeKilobytes' => $this->settings->getInt(SystemSettings::IMPORT_MAX_KILOBYTES),
            'maxRows' => $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS),
            'recentBatches' => $recentBatches,
        ]);
    }

    public function store(
        StoreMembershipBatchRequest $request,
        NewMembershipImportService $service,
    ): RedirectResponse {
        $file = $request->file('file');
        $user = $request->user();

        abort_unless($file instanceof UploadedFile, 422);
        abort_unless($user instanceof User, 403);

        $batch = $service->store(
            $file,
            $user,
        );

        $this->audit->record('membership.batch.uploaded', $batch, $this->batchMetadata($batch), $user);

        return redirect()->route('membership-batches.show', $batch);
    }

    public function show(Request $request, MembershipBatch $membershipBatch): Response
    {
        $rows = $membershipBatch->rows()
            ->with('grade:id,name')
            ->orderBy('row_number')
            ->paginate(50)
            ->through(static fn (MembershipBatchRow $row): array => [
                'row_number' => $row->row_number,
                'full_name' => $row->full_name,
                'email' => $row->email,
                'phone' => $row->phone,
                'state' => $row->state,
                'registration_date' => $row->registration_date?->toDateString(),
                'grade' => $row->membership_grade_id === null
                    ? $row->grade_value
                    : $row->grade->name,
                'supplied_membership_number' => $row->supplied_membership_number,
                'allocated_membership_number' => $row->allocated_membership_number,
                'status' => $row->status->value,
                'errors' => $row->validation_errors ?? [],
                'warnings' => $row->validation_warnings ?? [],
            ]);

        $user = $request->user();

        return Inertia::render('membership-batches/show', [
            'batch' => [
                'batch_reference' => $membershipBatch->batch_reference,
                'batch_type' => $membershipBatch->batch_type->value,
                'status' => $membershipBatch->status->value,
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
                'process' => $user?->can('membership.register.process') ?? false,
            ],
        ]);
    }

    public function process(
        Request $request,
        MembershipBatch $membershipBatch,
        NewMembershipBatchProcessor $processor,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($membershipBatch->batch_type !== MembershipBatchType::NewRegistration) {
            return back()->withErrors(['batch' => 'This batch type cannot be processed here.']);
        }

        if ($membershipBatch->status !== MembershipBatchStatus::ReadyForReview) {
            return back()->withErrors(['batch' => 'Only batches that are ready for review can be processed.']);
        }

        try {
            $batch = $processor->process($membershipBatch, $user);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch' => $exception->getMessage()]);
        }

        $this->audit->record('membership.batch.processed', $batch, [
            ...$this->batchMetadata($batch),
            'successful_rows' => $batch->successful_rows,
            'processed_at' => $batch->processed_at?->toISOString(),
        ], $user);

        return redirect()
            ->route('membership-batches.show', $batch)
            ->with('success', 'Batch processed successfully. Membership numbers were allocated in alphabetical order.');
    }

    public function template(Request $request): BinaryFileResponse
    {
        $this->audit->record('membership.template.downloaded', null, [
            'template' => 'new_membership',
        ], $request->user());

        return Excel::download(
            new NewMembershipTemplateExport,
            'nim-new-membership-template.xlsx',
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
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'failed_rows' => $batch->failed_rows,
            'warning_rows' => $batch->warning_rows,
        ];
    }
}
