<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipSequence;
use Illuminate\Support\Facades\DB;

class MembershipNumberAllocator
{
    public const DEFAULT_WIDTH = 6;

    public const MAX_SIX_DIGIT_NUMBER = 999_999;

    public const FIRST_SEVEN_DIGIT_NUMBER = 1_000_000;

    /**
     * Allocate the next membership numbers using the confirmed NIM sequence rules:
     * - 6 digits by default, continuing after the highest existing number.
     * - When the highest number has reached 999999, free numbers from 000001 to 999999
     *   are allocated first in ascending order.
     * - Once the six-digit range is exhausted, continue with 7+ digits.
     *
     * @return list<string>
     */
    public function allocate(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        return DB::transaction(function () use ($count): array {
            $this->lockAllocation();

            $taken = $this->existingNumbersByValue();
            $maximum = $taken === [] ? 0 : max(array_keys($taken));

            $numbers = [];

            if ($maximum >= self::MAX_SIX_DIGIT_NUMBER) {
                $numbers = $this->availableSixDigitNumbers($taken, $count);
            }

            $next = $maximum + 1;

            while (count($numbers) < $count) {
                if (isset($taken[$next])) {
                    $next++;

                    continue;
                }

                $width = $next >= self::FIRST_SEVEN_DIGIT_NUMBER
                    ? strlen((string) $next)
                    : self::DEFAULT_WIDTH;

                $numbers[] = str_pad((string) $next, $width, '0', STR_PAD_LEFT);
                $taken[$next] = true;
                $next++;
            }

            return $numbers;
        });
    }

    private function lockAllocation(): void
    {
        MembershipSequence::query()->firstOrCreate(
            ['sequence_name' => 'membership'],
            ['next_number' => '0', 'number_width' => self::DEFAULT_WIDTH],
        );

        MembershipSequence::query()
            ->where('sequence_name', 'membership')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Map every numeric membership number to its integer value.
     *
     * @return array<int, true>
     */
    private function existingNumbersByValue(): array
    {
        $numbers = Member::query()
            ->pluck('membership_number')
            ->filter(static fn (mixed $number): bool => is_string($number) && $number !== '' && ctype_digit($number))
            ->map(static fn (string $number): int => (int) ltrim($number, '0'))
            ->filter(static fn (int $number): bool => $number >= 1)
            ->unique()
            ->all();

        return array_fill_keys($numbers, true);
    }

    /**
     * Collect the lowest available six-digit numbers.
     *
     * @param  array<int, true>  $taken
     * @return list<string>
     */
    private function availableSixDigitNumbers(array $taken, int $count): array
    {
        $numbers = [];

        for ($candidate = 1; $candidate <= self::MAX_SIX_DIGIT_NUMBER; $candidate++) {
            if (isset($taken[$candidate])) {
                continue;
            }

            $numbers[] = str_pad((string) $candidate, self::DEFAULT_WIDTH, '0', STR_PAD_LEFT);

            if (count($numbers) >= $count) {
                break;
            }
        }

        return $numbers;
    }
}
