<?php

namespace App\Services;

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\MembershipHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NewMembershipBatchProcessor
{
    public function __construct(
        private readonly MembershipNumberAllocator $allocator,
        private readonly AuditLogger $audit,
    ) {}

    public function process(MembershipBatch $batch, User $actor): MembershipBatch
    {
        if ($batch->batch_type !== MembershipBatchType::NewRegistration) {
            throw new RuntimeException('Only new registration batches can be processed by this action.');
        }

        return DB::transaction(function () use ($batch, $actor): MembershipBatch {
            /** @var MembershipBatch $locked */
            $locked = MembershipBatch::query()
                ->whereKey($batch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === MembershipBatchStatus::Processing) {
                throw new RuntimeException('This batch is already being processed.');
            }

            if (in_array($locked->status, [
                MembershipBatchStatus::Completed,
                MembershipBatchStatus::CompletedWithErrors,
            ], true)) {
                throw new RuntimeException('This batch has already been processed.');
            }

            if ($locked->status !== MembershipBatchStatus::ReadyForReview) {
                throw new RuntimeException('Only batches that are ready for review can be processed.');
            }

            $rows = MembershipBatchRow::query()
                ->where('membership_batch_id', $locked->getKey())
                ->whereIn('status', [MembershipBatchRowStatus::Valid->value, MembershipBatchRowStatus::Warning->value])
                ->whereNull('member_id')
                ->whereNull('allocated_membership_number')
                ->orderBy('last_name_sort')
                ->orderBy('normalised_name')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new RuntimeException('There are no valid rows to process in this batch.');
            }

            $suppliedNumbers = $rows
                ->pluck('supplied_membership_number')
                ->filter(fn ($n): bool => is_string($n) && $n !== '' && ctype_digit($n))
                ->values()
                ->all();

            if ($suppliedNumbers !== []) {
                $conflicts = Member::query()
                    ->whereIn('membership_number', $suppliedNumbers)
                    ->pluck('membership_number')
                    ->all();

                if ($conflicts !== []) {
                    $list = implode(', ', array_slice($conflicts, 0, 5));
                    throw new RuntimeException("One or more supplied membership numbers are already assigned: {$list}.");
                }
            }

            $emails = $rows
                ->pluck('email')
                ->filter(fn ($email): bool => is_string($email) && trim($email) !== '')
                ->map(fn (string $email): string => mb_strtolower(trim($email)))
                ->unique()
                ->values()
                ->all();

            if ($emails !== []) {
                $emailConflicts = Member::query()
                    ->whereIn('email', $emails)
                    ->pluck('email')
                    ->all();

                if ($emailConflicts !== []) {
                    throw new RuntimeException('One or more email addresses are already registered. Re-upload and review the batch.');
                }
            }

            $locked->update(['status' => MembershipBatchStatus::Processing]);

            $rowsNeedingAllocation = $rows->filter(fn (MembershipBatchRow $row): bool => $row->supplied_membership_number === null || $row->supplied_membership_number === '');
            $allocatedNumbers = $rowsNeedingAllocation->isEmpty()
                ? []
                : $this->allocator->allocate($rowsNeedingAllocation->count());
            $allocateIndex = 0;
            $now = now();
            $successful = 0;

            foreach ($rows->values() as $row) {
                $supplied = $row->supplied_membership_number;
                if (is_string($supplied) && $supplied !== '' && ctype_digit($supplied)) {
                    $number = $supplied;
                } else {
                    $number = $allocatedNumbers[$allocateIndex] ?? null;
                    $allocateIndex++;
                }

                if (! is_string($number) || $number === '') {
                    throw new RuntimeException("Row {$row->row_number} could not be assigned a membership number.");
                }

                $fullName = (string) $row->full_name;
                $gradeId = $row->membership_grade_id;

                if ($gradeId === null) {
                    throw new RuntimeException("Row {$row->row_number} does not have a valid membership grade.");
                }

                $member = Member::query()->create([
                    'membership_number' => $number,
                    'full_name' => $fullName,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'state' => $row->state,
                    'date_of_birth' => $row->date_of_birth,
                    'address' => $row->address,
                    'membership_grade_id' => $gradeId,
                    'registration_date' => $row->registration_date?->toDateString() ?? $now->toDateString(),
                ]);

                MembershipHistory::query()->create([
                    'member_id' => $member->getKey(),
                    'previous_grade_id' => null,
                    'new_grade_id' => $gradeId,
                    'change_type' => 'registration',
                    'changed_by' => $actor->getKey(),
                    'changed_at' => $now,
                ]);

                $row->forceFill([
                    'status' => MembershipBatchRowStatus::Processed,
                    'member_id' => $member->getKey(),
                    'allocated_membership_number' => $number,
                ])->save();

                $this->audit->record('membership.created', $member, [
                    'membership_number' => $number,
                    'membership_grade_id' => $gradeId,
                    'registration_date' => $member->registration_date?->toDateString(),
                    'batch_reference' => $locked->batch_reference,
                    'number_source' => is_string($supplied) && $supplied !== '' && ctype_digit($supplied)
                        ? 'supplied'
                        : 'allocated',
                ], $actor);

                $successful++;
            }

            $failedRows = (int) $locked->failed_rows;
            $status = $failedRows > 0
                ? MembershipBatchStatus::CompletedWithErrors
                : MembershipBatchStatus::Completed;

            $locked->update([
                'status' => $status,
                'successful_rows' => $successful,
                'processed_at' => $now,
            ]);

            return $locked->refresh();
        });
    }
}
