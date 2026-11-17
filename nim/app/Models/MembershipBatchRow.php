<?php

namespace App\Models;

use App\Enums\MembershipBatchRowStatus;
use Database\Factories\MembershipBatchRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $row_number
 * @property string|null $full_name
 * @property string|null $last_name_sort
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $state
 * @property string|null $grade_value
 * @property MembershipBatchRowStatus $status
 * @property array<int, string>|null $validation_errors
 * @property array<int, string>|null $validation_warnings
 * @property int|null $membership_grade_id
 * @property int|null $member_id
 * @property string|null $allocated_membership_number
 * @property-read MembershipGrade|null $grade
 */
class MembershipBatchRow extends Model
{
    /** @use HasFactory<MembershipBatchRowFactory> */
    use HasFactory;

    protected $fillable = [
        'membership_batch_id',
        'row_number',
        'full_name',
        'last_name_sort',
        'email',
        'phone',
        'state',
        'date_of_birth',
        'registration_date',
        'address',
        'grade_value',
        'normalised_name',
        'membership_grade_id',
        'previous_grade_id',
        'intended_grade_id',
        'upgrade_type_id',
        'status',
        'validation_errors',
        'validation_warnings',
        'member_id',
        'allocated_membership_number',
        'supplied_membership_number',
        'supplied_full_name',
        'intended_grade_value',
    ];

    /**
     * Get the batch this row belongs to.
     *
     * @return BelongsTo<MembershipBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MembershipBatch::class, 'membership_batch_id');
    }

    /**
     * Get the configured grade matched during validation.
     *
     * @return BelongsTo<MembershipGrade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'membership_grade_id');
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
    public function intendedGrade(): BelongsTo
    {
        return $this->belongsTo(MembershipGrade::class, 'intended_grade_id');
    }

    /**
     * @return BelongsTo<UpgradeType, $this>
     */
    public function upgradeType(): BelongsTo
    {
        return $this->belongsTo(UpgradeType::class);
    }

    /**
     * Get the member created/matched from this row.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipBatchRowStatus::class,
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
            'registration_date' => 'date',
        ];
    }
}
