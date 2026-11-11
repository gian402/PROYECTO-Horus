<?php

namespace App\Services\Import;

use App\Enums\EnrollmentRole;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Lee el libro histórico de cursos tal como se usaba antes del CRM
 * (p. ej. "CURSOS 2024.xlsx"):
 *
 *  - una hoja por curso; las hojas sin tabla (menú INICIO) se omiten;
 *  - fila de título con el nombre del curso y, debajo, los encabezados
 *    NRO | APELLIDOS Y NOMBRES | [NOTA] | DNI | [CELULAR] | CÓD. REGISTRO;
 *  - una fila "PONENTE" / "PONENTES" separa a los ponentes, que también
 *    tienen código de registro;
 *  - a la derecha puede haber un panel "CONSULTA" con encabezados repetidos:
 *    se ignora porque cada columna se toma de su PRIMERA aparición.
 *
 * El código de curso y el año salen de los códigos de registro de la hoja
 * (HGC-NUT-DIET-01-2024 → NUT-DIET, 2024). Solo lee: la validación contra la
 * base la hace HistoricalImporter.
 */
class LegacyWorkbookReader
{
    private const HEADER_SCAN_ROWS = 15;

    private const MAX_COLUMNS = 40;

    /** Partículas que forman parte de un apellido compuesto (De la Cruz). */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa', 'da', 'di', 'van', 'von'];

    /**
     * @return list<ImportRow>
     */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $rows = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            array_push($rows, ...$this->readSheet($sheet));
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    /**
     * Separa "APELLIDOS Y NOMBRES": las dos primeras palabras son los apellidos
     * (uniendo partículas como "De la") y el resto, los nombres.
     *
     * @return array{0: string, 1: string} [apellidos, nombres]
     */
    public static function splitName(string $fullName): array
    {
        $words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $surnames = [];
        $i = 0;

        // Siempre queda al menos una palabra para los nombres.
        while (count($surnames) < 2 && $i < count($words) - 1) {
            $surname = [$words[$i++]];

            while (in_array(mb_strtolower(end($surname)), self::PARTICLES, true) && $i < count($words) - 1) {
                $surname[] = $words[$i++];
            }

            $surnames[] = implode(' ', $surname);
        }

        return [implode(' ', $surnames), implode(' ', array_slice($words, $i))];
    }

    /**
     * @return list<ImportRow>
     */
    private function readSheet(Worksheet $sheet): array
    {
        $header = $this->findHeader($sheet);

        if ($header === null) {
            return [];
        }

        [$headerRow, $columns] = $header;
        $title = $sheet->getTitle();
        $courseName = $this->findTitle($sheet, $headerRow, $columns['name']) ?? $title;
        $lastRow = $sheet->getHighestDataRow();

        // 1) Lectura cruda de las filas de la tabla.
        $raw = [];
        $role = EnrollmentRole::Participante;

        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $cells = [];
            foreach ($columns as $key => $col) {
                $cells[$key] = $this->text($sheet, $col, $r);
            }
            $first = $this->text($sheet, 1, $r);

            // Separador "PONENTE" / "PONENTES": las filas siguientes son ponentes.
            $marker = collect([$first, ...array_values($cells)])->first(fn ($v) => $v !== null && str_starts_with($this->normalize($v), 'PONENTE'));
            if ($marker !== null) {
                $role = EnrollmentRole::Ponente;

                continue;
            }

            if (($cells['name'] ?? null) === null && ($cells['dni'] ?? null) === null && ($cells['code'] ?? null) === null) {
                continue;
            }

            $raw[] = ['line' => $r, 'role' => $role, 'cells' => $cells];
        }

        if ($raw === []) {
            return [];
        }

        // 2) Curso y año de la hoja: los más frecuentes entre sus códigos.
        $parsed = collect($raw)->map(fn ($row) => ImportRow::parseCode($this->upper($row['cells']['code'] ?? null)))->filter();
        $courseCode = $parsed->countBy('course')->sortDesc()->keys()->first();
        $year = $parsed->countBy('year')->sortDesc()->keys()->first();
        $prefix = (string) config('academic.registration_prefix', 'HGC');

        // 3) Normalización fila por fila.
        return array_map(function (array $row) use ($title, $courseName, $courseCode, $year, $prefix): ImportRow {
            $cells = $row['cells'];
            $errors = [];
            $warnings = [];

            [$lastName, $firstName] = self::splitName((string) ($cells['name'] ?? ''));

            $dni = mb_strtoupper(preg_replace('/[\s.\-]+/', '', (string) ($cells['dni'] ?? '')));
            if (preg_match('/^\d{7}$/', $dni)) {
                $dni = '0'.$dni;
                $warnings[] = "El DNI tenía 7 dígitos: se completó con un cero ({$dni}).";
            }

            $code = $this->upper($cells['code'] ?? null);
            $codeParts = ImportRow::parseCode($code);
            if ($code !== null && $codeParts === null) {
                $errors[] = "El código {$code} no tiene un formato reconocido (HGC-CURSO-NN-AÑO).";
            } elseif ($codeParts !== null && ($codeParts['prefix'] !== $prefix || $codeParts['course'] !== $courseCode || $codeParts['year'] !== $year)) {
                $errors[] = "El código {$code} no corresponde al curso {$courseCode} del año {$year} de esta hoja.";
            }

            if ($courseCode === null) {
                $errors[] = 'No se pudo deducir el código del curso: la hoja no tiene códigos de registro válidos.';
            }

            $phone = isset($cells['phone']) ? (preg_replace('/[^\d+]/', '', $cells['phone']) ?: null) : null;
            $grade = isset($cells['grade']) ? str_replace(',', '.', $cells['grade']) : null;

            return new ImportRow(
                line: $row['line'],
                dni: $dni,
                firstName: $firstName,
                lastName: $lastName,
                email: null,
                phone: $phone,
                courseCode: (string) $courseCode,
                startDate: null,
                endDate: null,
                year: $year,
                registrationCode: $code,
                grade: $grade,
                errors: $errors,
                sheet: $title,
                courseName: $courseName,
                role: $row['role'],
                historical: true,
                warnings: $warnings,
            );
        }, $raw);
    }

    /**
     * Primera fila con "APELLIDOS Y NOMBRES" y "DNI"; cada columna se toma de su
     * primera aparición de izquierda a derecha.
     *
     * @return array{0: int, 1: array<string, int>}|null
     */
    private function findHeader(Worksheet $sheet): ?array
    {
        $maxRow = min(self::HEADER_SCAN_ROWS, $sheet->getHighestDataRow());

        for ($r = 1; $r <= $maxRow; $r++) {
            $columns = [];

            for ($c = 1; $c <= self::MAX_COLUMNS; $c++) {
                $value = $this->text($sheet, $c, $r);
                $key = $value === null ? null : match (true) {
                    $this->normalize($value) === 'APELLIDOS Y NOMBRES' => 'name',
                    $this->normalize($value) === 'DNI' => 'dni',
                    $this->normalize($value) === 'NOTA' => 'grade',
                    in_array($this->normalize($value), ['CELULAR', 'TELEFONO'], true) => 'phone',
                    str_contains($this->normalize($value), 'REGISTRO') => 'code',
                    default => null,
                };

                if ($key !== null && ! isset($columns[$key])) {
                    $columns[$key] = $c;
                }
            }

            if (isset($columns['name'], $columns['dni'])) {
                return [$r, $columns];
            }
        }

        return null;
    }

    /**
     * Título del curso: primer texto encima de la fila de encabezados.
     */
    private function findTitle(Worksheet $sheet, int $headerRow, int $nameColumn): ?string
    {
        for ($r = 1; $r < $headerRow; $r++) {
            for ($c = 1; $c <= max($nameColumn, 8); $c++) {
                if (($value = $this->text($sheet, $c, $r)) !== null) {
                    return Str::limit(preg_replace('/\s+/u', ' ', $value), 150, '');
                }
            }
        }

        return null;
    }

    /**
     * Valor de la celda como texto. Para fórmulas usa el valor guardado por Excel;
     * los números enteros se escriben sin decimales (DNI, teléfono).
     */
    private function text(Worksheet $sheet, int $column, int $row): ?string
    {
        $cell = $sheet->getCell([$column, $row]);
        $value = $this->value($cell);

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            $value = (string) (int) $value;
        }

        $value = trim((string) $value);

        return $value === '' || str_starts_with($value, '#') ? null : $value;
    }

    private function value(Cell $cell): mixed
    {
        if (! $cell->isFormula()) {
            return $cell->getValue();
        }

        $cached = $cell->getOldCalculatedValue();
        if ($cached !== null) {
            return $cached;
        }

        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return null;
        }
    }

    private function upper(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper(preg_replace('/\s+/', '', $value));
    }

    /**
     * Mayúsculas, sin tildes ni signos: "CÓD. REGISTRO" → "COD REGISTRO".
     */
    private function normalize(string $value): string
    {
        $ascii = mb_strtoupper(Str::ascii($value));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', $ascii)));
    }
}
