<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\UpgradeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Record an immutable audit entry for an operational action.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
        ?string $subjectLabel = null,
    ): AuditLog {
        $actor ??= Auth::user();
        $request = request();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? $this->defaultLabel($subject),
            'metadata' => $metadata === [] ? null : $this->scrub($metadata),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }

    private function defaultLabel(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof MembershipBatch => $subject->batch_reference,
            $subject instanceof Member => $subject->membership_number,
            $subject instanceof UpgradeType => $subject->name,
            $subject instanceof User => $subject->name,
            $subject === null => null,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }

    /**
     * Remove credentials and session secrets from any metadata payload.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function scrub(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }
}
