<?php

namespace App\Services;

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use App\Models\MembershipUpgrade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpgradeBatchProcessor
{
    public function __construct(
        private readonly MembershipGradeResolver $gradeResolver,
        private readonly AuditLogger $audit,
    ) {}

    public function process(MembershipBatch $batch, User $actor): MembershipBatch
    {
        if ($batch->batch_type !== MembershipBatchType::Upgrade) {
            throw new RuntimeException('Only upgrade batches can be processed by this action.');
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
                ->whereNotNull('member_id')
                ->whereNotNull('intended_grade_id')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new RuntimeException('There are no valid rows to process in this batch.');
            }

            /** @var Collection<string, array{id:int,name:string}> $gradesByName */
            $gradesByName = MembershipGrade::query()
                ->where('is_active', true)
                ->get(['id', 'name'])
                ->mapWithKeys(static fn (MembershipGrade $grade): array => [
                    Member::normaliseName($grade->name) => [
                        'id' => $grade->getKey(),
                        'name' => $grade->name,
                    ],
                ]);

            $locked->update(['status' => MembershipBatchStatus::Processing]);

            $now = now();
            $successful = 0;
            $failedDuringProcess = 0;

            foreach ($rows as $row) {
                $member = Member::query()
                    ->with('grade:id,name')
                    ->whereKey($row->member_id)
                    ->lockForUpdate()
                    ->first();

                if ($member === null) {
                    $this->markRowFailed($row, 'Matched member record no longer exists.');
                    $failedDuringProcess++;

                    continue;
                }

                if ($row->supplied_membership_number !== null
                    && $member->membership_number !== $row->supplied_membership_number) {
                    $this->markRowFailed($row, 'The member\'s membership number no longer matches this batch. Re-upload and review the upgrade file.');
                    $failedDuringProcess++;

                    continue;
                }

                $previousGradeId = (int) $member->membership_grade_id;

                if ($row->previous_grade_id !== null && (int) $row->previous_grade_id !== $previousGradeId) {
                    $this->markRowFailed($row, 'The member\'s grade changed after this batch was prepared. Re-upload and review the upgrade file.');
                    $failedDuringProcess++;

                    continue;
                }

                $currentGradeName = $member->grade?->name;
                $nextGradeName = $this->gradeResolver->nextGradeName($currentGradeName);

                if ($nextGradeName === null) {
                    $this->markRowFailed($row, 'The member\'s current grade can no longer be upgraded by exactly one step.');
                    $failedDuringProcess++;

                    continue;
                }

                $targetGrade = $this->gradeResolver->resolve($nextGradeName, $gradesByName);

                if ($targetGrade === null) {
                    $this->markRowFailed($row, "Target grade \"{$nextGradeName}\" is not an active configured grade.");
                    $failedDuringProcess++;

                    continue;
                }

                if ((int) $targetGrade['id'] !== (int) $row->intended_grade_id) {
                    $this->markRowFailed($row, 'The intended grade is no longer the confirmed one-step upgrade. Re-upload and review the upgrade file.');
                    $failedDuringProcess++;

                    continue;
                }

                $member->forceFill(['membership_grade_id' => $targetGrade['id']])->save();

                MembershipUpgrade::query()->create([
                    'member_id' => $member->getKey(),
                    'membership_batch_id' => $locked->getKey(),
                    'upgrade_type_id' => $row->upgrade_type_id ?? $locked->upgrade_type_id,
                    'previous_grade_id' => $previousGradeId,
                    'new_grade_id' => $targetGrade['id'],
                    'processed_by' => $actor->getKey(),
                    'effective_date' => null,
                ]);

                MembershipHistory::query()->create([
                    'member_id' => $member->getKey(),
                    'previous_grade_id' => $previousGradeId,
                    'new_grade_id' => $targetGrade['id'],
                    'change_type' => 'upgrade',
                    'changed_by' => $actor->getKey(),
                    'changed_at' => $now,
                ]);

                $row->forceFill([
                    'status' => MembershipBatchRowStatus::Processed,
                    'previous_grade_id' => $previousGradeId,
                ])->save();

                $this->audit->record('membership.upgraded', $member, [
                    'membership_number' => $member->membership_number,
                    'batch_reference' => $locked->batch_reference,
                    'upgrade_type_id' => $row->upgrade_type_id ?? $locked->upgrade_type_id,
                    'previous_grade' => $currentGradeName,
                    'new_grade' => $targetGrade['name'],
                    'previous_grade_id' => $previousGradeId,
                    'new_grade_id' => $targetGrade['id'],
                ], $actor);

                $successful++;
            }

            $existingFailed = (int) $locked->failed_rows;
            $totalFailed = $existingFailed + $failedDuringProcess;
            $status = $totalFailed > 0
                ? MembershipBatchStatus::CompletedWithErrors
                : MembershipBatchStatus::Completed;

            $locked->update([
                'status' => $status,
                'failed_rows' => $totalFailed,
                'successful_rows' => $successful,
                'processed_at' => $now,
            ]);

            return $locked->refresh();
        });
    }

    private function markRowFailed(MembershipBatchRow $row, string $message): void
    {
        $errors = $row->validation_errors ?? [];
        $errors[] = $message;

        $row->forceFill([
            'status' => MembershipBatchRowStatus::Invalid,
            'validation_errors' => $errors,
        ])->save();
    }
}
