<?php

namespace App\Exports;

use App\Services\Import\HistoricalImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de importación histórica con una fila de ejemplo.
 */
class ImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return HistoricalImporter::COLUMNS;
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function array(): array
    {
        return [
            ['70123456', 'Ana María', 'Quispe Rojas', 'ana@ejemplo.com', '987654321', 'PA', '2024-02-01', '2024-02-20', 2024, 'HGC-PA-01-024', 16],
            ['70123457', 'Luis', 'Gómez Ruiz', '', '', 'PA', '2024-02-01', '2024-02-20', 2024, '', ''],
        ];
    }
}
