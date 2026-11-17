<?php

namespace App\Services;

use App\Models\SystemSetting;

class SystemSettings
{
    public const IMPORT_MAX_KILOBYTES = 'import.max_kilobytes';

    public const IMPORT_MAX_ROWS = 'import.max_rows';

    public const IMPORT_CHUNK_SIZE = 'import.chunk_size';

    /**
     * @var list<string>
     */
    public const KEYS = [
        self::IMPORT_MAX_KILOBYTES,
        self::IMPORT_MAX_ROWS,
        self::IMPORT_CHUNK_SIZE,
    ];

    /**
     * Get all effective values, with database overrides applied.
     *
     * @return array<string, int>
     */
    public function values(): array
    {
        $values = $this->defaults();
        $overrides = SystemSetting::query()
            ->whereIn('key', self::KEYS)
            ->get(['key', 'value']);

        foreach ($overrides as $setting) {
            if (in_array($setting->key, self::KEYS, true)) {
                $values[$setting->key] = max(1, (int) $setting->value);
            }
        }

        return $values;
    }

    public function getInt(string $key): int
    {
        return $this->values()[$key] ?? 1;
    }

    /**
     * Persist authorised overrides and return the changed values.
     *
     * @param  array<string, int>  $values
     * @return array{before: array<string, int>, after: array<string, int>}
     */
    public function update(array $values): array
    {
        $before = $this->values();

        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => max(1, (int) $value)],
            );
        }

        $after = $this->values();

        return [
            'before' => array_intersect_key($before, $values),
            'after' => array_intersect_key($after, $values),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function defaults(): array
    {
        return [
            self::IMPORT_MAX_KILOBYTES => max(1, (int) config('membership.import.max_kilobytes', 10240)),
            self::IMPORT_MAX_ROWS => max(1, (int) config('membership.import.max_rows', 10000)),
            self::IMPORT_CHUNK_SIZE => max(1, (int) config('membership.import.chunk_size', 500)),
        ];
    }
}
