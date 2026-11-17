<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['membership_number', 'full_name', 'email', 'phone', 'state', 'date_of_birth', 'address', 'membership_grade_id', 'registration_date'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Member $member): void {
            $member->normalised_name = self::normaliseName($member->full_name);
        });
    }

    public static function normaliseName(string $name): string
    {
        return Str::of($name)->squish()->lower()->toString();
    }

    /**
     * Get the member's current grade.
     *
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'membership_grade_id');
    }

    /**
     * Get this member's complete grade-change history.
     *
     * @return HasMany<MembershipHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(MembershipHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<MembershipUpgrade, $this>
     */
    public function upgrades(): HasMany
    {
        return $this->hasMany(MembershipUpgrade::class)->latest('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'date_of_birth' => 'date',
        ];
    }
}
