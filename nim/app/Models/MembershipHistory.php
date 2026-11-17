<?php

namespace App\Models;

use Database\Factories\MembershipHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'previous_grade_id', 'new_grade_id', 'change_type', 'changed_by', 'changed_at'])]
class MembershipHistory extends Model
{
    /** @use HasFactory<MembershipHistoryFactory> */
    use HasFactory;

    /**
     * Get the member whose history this entry records.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the grade before this change, if there was one.
     *
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function previousGrade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'previous_grade_id');
    }

    /**
     * Get the grade after this change.
     *
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function newGrade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'new_grade_id');
    }

    /**
     * Get the user who recorded this change.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }
}
