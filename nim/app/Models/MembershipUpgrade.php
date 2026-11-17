<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id',
    'membership_batch_id',
    'upgrade_type_id',
    'previous_grade_id',
    'new_grade_id',
    'processed_by',
    'effective_date',
])]
class MembershipUpgrade extends Model
{
    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<MembershipBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MembershipBatch::class, 'membership_batch_id');
    }

    /**
     * @return BelongsTo<UpgradeType, $this>
     */
    public function upgradeType(): BelongsTo
    {
        return $this->belongsTo(UpgradeType::class);
    }

    /**
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function previousGrade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'previous_grade_id');
    }

    /**
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function newGrade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'new_grade_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }
}
