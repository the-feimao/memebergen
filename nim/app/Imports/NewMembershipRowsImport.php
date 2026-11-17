<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class NewMembershipRowsImport implements OnEachRow, SkipsEmptyRows, WithChunkReading, WithHeadingRow, WithLimit, WithMultipleSheets
{
    /**
     * @var list<array{row_number: int, values: array<array-key, mixed>}>
     */
    private array $rows = [];

    public function __construct(
        private readonly int $maximumRows,
        private readonly int $chunkSize,
    ) {}

    public function onRow(Row $row): void
    {
        $this->rows[] = [
            'row_number' => $row->getIndex(),
            'values' => $row->toArray(null, false, false),
        ];
    }

    /**
     * Get imported rows with their source worksheet row numbers.
     *
     * @return list<array{row_number: int, values: array<array-key, mixed>}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    public function limit(): int
    {
        return $this->maximumRows;
    }

    /**
     * Import only the first worksheet after the service verifies the workbook shape.
     *
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }
}
