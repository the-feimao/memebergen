<?php

namespace App\Models;

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use Database\Factories\MembershipBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $batch_reference
 * @property MembershipBatchType $batch_type
 * @property MembershipBatchStatus $status
 * @property array<int, string>|null $detected_headings
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $failed_rows
 * @property int $warning_rows
 * @property string|null $failure_reason
 * @property string $original_filename
 * @property Carbon $uploaded_at
 * @property Carbon|null $processed_at
 */
class MembershipBatch extends Model
{
    /** @use HasFactory<MembershipBatchFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'batch_reference';
    }

    protected $fillable = [
        'batch_reference',
        'batch_type',
        'upgrade_type_id',
        'uploaded_by',
        'original_filename',
        'original_file_path',
        'file_sha256',
        'status',
        'detected_headings',
        'total_rows',
        'valid_rows',
        'failed_rows',
        'warning_rows',
        'successful_rows',
        'failure_reason',
        'uploaded_at',
        'processed_at',
    ];

    /**
     * Get this batch's imported rows.
     *
     * @return HasMany<MembershipBatchRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(MembershipBatchRow::class);
    }

    /**
     * Get the user who uploaded this batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<UpgradeType, $this>
     */
    public function upgradeType(): BelongsTo
    {
        return $this->belongsTo(UpgradeType::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'batch_type' => MembershipBatchType::class,
            'status' => MembershipBatchStatus::class,
            'detected_headings' => 'array',
            'uploaded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
