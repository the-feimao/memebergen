<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

return new class extends Migration
{
    /**
     * Registration-date headings understood by the import, most specific first.
     *
     * @var list<string>
     */
    private array $dateHeadings = [
        'registration_date',
        'date_registered',
        'registered_on',
        'registered_date',
        'date_of_registration',
        'induction_date',
        'date_of_induction',
        'date_of_election',
        'date',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $disk = (string) config('membership.import.disk');

        DB::table('membership_batches')
            ->where('batch_type', 'new_registration')
            ->whereNotNull('original_file_path')
            ->orderBy('id')
            ->get(['id', 'original_file_path', 'original_filename'])
            ->each(function (object $batch) use ($disk): void {
                try {
                    $this->backfillBatch($batch, $disk);
                } catch (Throwable $exception) {
                    Log::warning('Unable to backfill registration dates from a stored membership workbook.', [
                        'batch_id' => $batch->id,
                        'path' => $batch->original_file_path,
                        'exception' => $exception,
                    ]);
                }
            });

        $this->backfillSeededRoster();

        DB::table('members')
            ->whereNull('registration_date')
            ->update(['registration_date' => DB::raw('DATE(created_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing registration dates are retained; the backfill is not destructive.
    }

    private function backfillBatch(object $batch, string $disk): void
    {
        if (! Storage::disk($disk)->exists($batch->original_file_path)) {
            return;
        }

        $absolutePath = Storage::disk($disk)->path($batch->original_file_path);
        $sheet = IOFactory::createReaderForFile($absolutePath)
            ->load($absolutePath)
            ->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $headings = [];
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false, false)[0] ?? [];

        foreach ($headerRow as $index => $heading) {
            $key = $this->normaliseHeading((string) $heading);

            if ($key !== '' && ! array_key_exists($key, $headings)) {
                $headings[$key] = $index;
            }
        }

        $dateColumn = $this->dateColumn($headings);
        $filenameDate = $this->dateFromFilename((string) $batch->original_filename);

        if ($dateColumn === null && $filenameDate === null) {
            return;
        }

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false, false)[0] ?? [];
            $date = $dateColumn === null
                ? null
                : $this->parseDate($row[$dateColumn] ?? null);
            $date ??= $filenameDate;

            if ($date === null) {
                continue;
            }

            DB::table('membership_batch_rows')
                ->where('membership_batch_id', $batch->id)
                ->where('row_number', $rowNumber)
                ->update(['registration_date' => $date]);
        }

        $linkedRows = DB::table('membership_batch_rows')
            ->where('membership_batch_id', $batch->id)
            ->whereNotNull('member_id')
            ->whereNotNull('registration_date')
            ->get(['member_id', 'registration_date']);

        foreach ($linkedRows as $row) {
            DB::table('members')
                ->where('id', $row->member_id)
                ->whereNull('registration_date')
                ->update(['registration_date' => $row->registration_date]);
        }
    }

    /**
     * The only members without a batch are the seeded MPE April 2022 induction roster,
     * whose source workbook has no date column.
     */
    private function backfillSeededRoster(): void
    {
        DB::table('members')
            ->whereNull('registration_date')
            ->whereNotIn('id', function ($query): void {
                $query->select('member_id')
                    ->from('membership_batch_rows')
                    ->whereNotNull('member_id');
            })
            ->update(['registration_date' => '2022-04-01']);
    }

    /**
     * @param  array<string, int>  $headings
     */
    private function dateColumn(array $headings): ?int
    {
        foreach ($this->dateHeadings as $candidate) {
            if (array_key_exists($candidate, $headings)) {
                return $headings[$candidate];
            }
        }

        return null;
    }

    private function normaliseHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));
        $heading = preg_replace('/[^a-z0-9]+/', '_', $heading) ?? $heading;

        return trim($heading, '_');
    }

    private function dateFromFilename(string $filename): ?string
    {
        if (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})\b/i', $filename, $matches) !== 1) {
            return null;
        }

        return Carbon::parse("1 {$matches[1]} {$matches[2]}")->format('Y-m-d');
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $raw) ?? $raw;
        $raw = str_replace(',', ' ', $raw);
        $raw = preg_replace('/\s+/u', ' ', trim($raw)) ?? $raw;

        try {
            if (is_numeric($raw)) {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            }

            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
};
