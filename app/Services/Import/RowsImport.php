<?php

namespace App\Services\Import;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Lector simple: devuelve las filas de la primera hoja con encabezados
 * convertidos a slug (p. ej. "Código curso" → "codigo_curso").
 *
 * StringValueBinder: todas las celdas se leen como texto. Evita que el lector
 * CSV "adivine" fechas como 01/08/2024 en formato estadounidense (mes/día);
 * las fechas reales de Excel llegan como número de serie y se convierten en
 * HistoricalImporter. También conserva ceros a la izquierda en los DNI.
 */
class RowsImport extends StringValueBinder implements Import, SkipsEmptyRows, WithCalculatedFormulas, WithCustomValueBinder, WithHeadingRow {}
