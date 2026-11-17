<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Collection;

class MembershipGradeResolver
{
    /**
     * Confirmed NIM grade aliases, keyed by a normalised (lowercase, space-separated) value.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'member research' => 'member',
        'member exam' => 'associate',
        'member exams' => 'associate',
        'member associate' => 'associate',
        'associate member' => 'associate',
        'member graduate' => 'graduate',
        'member grad' => 'graduate',
        'member nysc' => 'graduate',
        'graduate member' => 'graduate',
    ];

    /**
     * Resolve a supplied grade name (including confirmed aliases) to a configured grade.
     *
     * @param  Collection<string, array{id:int,name:string}>  $gradesByName
     * @return array{id:int,name:string}|null
     */
    public function resolve(string $value, Collection $gradesByName): ?array
    {
        $direct = $gradesByName->get(Member::normaliseName($value));

        if ($direct !== null) {
            return $direct;
        }

        $normalised = mb_strtolower(trim($value));
        $normalised = preg_replace('/[\s_\-]+/u', ' ', $normalised) ?? $normalised;
        $normalised = trim($normalised);

        $target = self::ALIASES[$normalised] ?? null;

        if ($target === null && in_array($normalised, ['graduate', 'associate', 'member', 'fellow'], true)) {
            $target = $normalised;
        }

        if ($target === null) {
            return null;
        }

        return $gradesByName->get(Member::normaliseName(ucfirst($target)));
    }

    /**
     * Get the confirmed one-step upgrade target for a grade.
     */
    public function nextGradeName(?string $currentGradeName): ?string
    {
        if ($currentGradeName === null) {
            return null;
        }

        return match (Member::normaliseName($currentGradeName)) {
            'graduate' => 'Associate',
            'associate' => 'Member',
            'member' => 'Fellow',
            default => null,
        };
    }

    /**
     * Determine whether moving between two grades is exactly one confirmed step.
     */
    public function isOneStepUp(?string $from, ?string $to): bool
    {
        $next = $this->nextGradeName($from);

        if ($next === null || $to === null) {
            return false;
        }

        return Member::normaliseName($next) === Member::normaliseName($to);
    }
}
