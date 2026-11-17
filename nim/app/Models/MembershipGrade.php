<?php

namespace App\Models;

use Database\Factories\MembershipGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class MembershipGrade extends Model
{
    /** @use HasFactory<MembershipGradeFactory> */
    use HasFactory;

    /**
     * Get the members currently assigned to this grade.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get history entries that reference this grade as the previous grade.
     *
     * @return HasMany<MembershipHistory, $this>
     */
    public function previousMembershipHistory(): HasMany
    {
        return $this->hasMany(MembershipHistory::class, 'previous_grade_id');
    }

    /**
     * Get history entries that reference this grade as the new grade.
     *
     * @return HasMany<MembershipHistory, $this>
     */
    public function newMembershipHistory(): HasMany
    {
        return $this->hasMany(MembershipHistory::class, 'new_grade_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
