<?php

namespace App\Services\Import;

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\RegistrationSequence;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\RegistrationCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Importación histórica desde Excel. Acepta dos formatos:
 *  - la plantilla plana del CRM (analyze), y
 *  - el libro de cursos usado antes del CRM, leído por LegacyWorkbookReader
 *    (analyzeRows). Sus filas son "históricas": sin fechas de edición, sin nota
 *    se consideran aprobadas y los cursos que falten se crean con el nombre de la hoja.
 *
 * Flujo en dos pasos:
 *  1. analyze*(): normaliza y valida TODAS las filas sin escribir nada.
 *  2. import(): si no hay errores, importa todo en una sola transacción
 *     ("todo o nada"). Es idempotente: reimportar el mismo archivo no duplica.
 *
 * Los códigos de registro históricos se respetan y el contador
 * registration_sequences se ajusta al máximo importado para que los códigos
 * generados después no colisionen.
 */
class HistoricalImporter
{
    public const MAX_ROWS = 5000;

    public const COLUMNS = [
        'dni', 'nombres', 'apellidos', 'email', 'telefono', 'codigo_curso',
        'fecha_inicio', 'fecha_fin', 'anio', 'codigo_registro', 'nota',
    ];

    public function __construct(private readonly RegistrationCodeGenerator $codes) {}

    /**
     * Plantilla plana del CRM.
     *
     * @param  array<int, array<string, mixed>>  $rawRows  filas con encabezados (WithHeadingRow)
     * @return array{rows: list<ImportRow>, valid: int, invalid: int, fatal: ?string, courses_to_create: list<array{code: string, name: string}>}
     */
    public function analyze(array $rawRows): array
    {
        $rawRows = array_values(array_filter($rawRows, fn ($r) => collect($r)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()));

        if ($rawRows === []) {
            return $this->fatal('El archivo no contiene filas con datos.');
        }

        if (count($rawRows) > self::MAX_ROWS) {
            return $this->fatal('El archivo supera el máximo de '.self::MAX_ROWS.' filas.');
        }

        $missing = array_diff(['dni', 'nombres', 'apellidos', 'codigo_curso', 'fecha_inicio', 'fecha_fin'], array_keys($rawRows[0]));
        if ($missing !== []) {
            return $this->fatal('Faltan columnas obligatorias: '.implode(', ', $missing).'. Usa la plantilla o el libro de cursos.');
        }

        $rows = [];
        foreach ($rawRows as $i => $raw) {
            // +2: la fila 1 es el encabezado y las filas de Excel empiezan en 1.
            $rows[] = $this->normalize($i + 2, $raw);
        }

        return $this->analyzeRows($rows);
    }

    /**
     * Valida filas ya normalizadas (por ejemplo, las del libro histórico).
     *
     * @param  list<ImportRow>  $rows
     * @return array{rows: list<ImportRow>, valid: int, invalid: int, fatal: ?string, courses_to_create: list<array{code: string, name: string}>}
     */
    public function analyzeRows(array $rows): array
    {
        if ($rows === []) {
            return $this->fatal('No se encontraron datos. El archivo debe ser la plantilla del CRM o el libro de cursos (hojas con encabezados "APELLIDOS Y NOMBRES" y "DNI").');
        }

        if (count($rows) > self::MAX_ROWS) {
            return $this->fatal('El archivo supera el máximo de '.self::MAX_ROWS.' filas.');
        }

        foreach ($rows as $row) {
            $this->validateFields($row);
        }

        $this->validateAgainstDatabase($rows);

        $invalid = count(array_filter($rows, fn (ImportRow $r) => ! $r->isValid()));

        return [
            'rows' => $rows,
            'valid' => count($rows) - $invalid,
            'invalid' => $invalid,
            'fatal' => null,
            'courses_to_create' => $this->coursesToCreate($rows)->values()->all(),
        ];
    }

    /**
     * Importa filas ya analizadas y sin errores. Todo en una sola transacción.
     *
     * @param  list<ImportRow>  $rows
     * @return array{students_created: int, courses_created: int, editions_created: int, enrollments_created: int, enrollments_existing: int, grades_updated: int}
     */
    public function import(array $rows): array
    {
        if (collect($rows)->contains(fn (ImportRow $r) => ! $r->isValid())) {
            throw new \DomainException('El archivo tiene errores: corrígelos antes de importar.');
        }

        return DB::transaction(function () use ($rows): array {
            $summary = ['students_created' => 0, 'courses_created' => 0, 'editions_created' => 0, 'enrollments_created' => 0, 'enrollments_existing' => 0, 'grades_updated' => 0];

            // 0) Cursos del libro histórico que aún no existen.
            foreach ($this->coursesToCreate($rows) as $course) {
                Course::create($course);
                $summary['courses_created']++;
            }

            $courses = Course::whereIn('code', collect($rows)->pluck('courseCode')->unique())->get()->keyBy('code');

            // 1) Ajustar los correlativos ANTES de generar códigos nuevos.
            $this->bumpSequences($rows, $courses);

            foreach ($rows as $row) {
                $course = $courses[$row->courseCode];

                $student = Student::firstOrNew(['dni' => $row->dni]);
                if (! $student->exists) {
                    $student->fill(['first_name' => $row->firstName, 'last_name' => $row->lastName]);
                    $summary['students_created']++;
                }
                // No se sobrescriben datos actuales con históricos: solo se completan vacíos.
                $student->email ??= $row->email;
                $student->phone ??= $row->phone;
                $student->save();

                $edition = $this->findEdition($course->id, $row) ?? CourseEdition::create([
                    'course_id' => $course->id, 'start_date' => $row->startDate, 'end_date' => $row->endDate, 'year' => $row->year,
                ]);
                if ($edition->wasRecentlyCreated) {
                    $summary['editions_created']++;
                }
                $edition->setRelation('course', $course);

                if ($row->isSpeaker()) {
                    $this->linkTeacher($edition, $row);
                }

                $enrollment = Enrollment::where('student_id', $student->id)->where('course_edition_id', $edition->id)->first();

                if ($enrollment === null) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'course_edition_id' => $edition->id,
                        'registration_code' => $row->registrationCode ?? $this->codes->next($edition),
                        'grade' => $row->grade,
                        'role' => $row->role,
                        'historical' => $row->historical,
                    ]);
                    $summary['enrollments_created']++;

                    continue;
                }

                $summary['enrollments_existing']++;

                // Reimportación: solo completa la nota si aún no existe.
                if ($row->grade !== null && $enrollment->grade === null && ! $enrollment->isSpeaker()) {
                    $enrollment->update(['grade' => $row->grade]);
                    $summary['grades_updated']++;
                }
            }

            return $summary;
        });
    }

    /**
     * @return array{rows: list<ImportRow>, valid: int, invalid: int, fatal: string, courses_to_create: list<array{code: string, name: string}>}
     */
    private function fatal(string $message): array
    {
        return ['rows' => [], 'valid' => 0, 'invalid' => 0, 'fatal' => $message, 'courses_to_create' => []];
    }

    /**
     * Edición a la que pertenece la fila: por curso y fechas o, si es histórica
     * sin fechas, por curso y año.
     */
    private function findEdition(int $courseId, ImportRow $row): ?CourseEdition
    {
        $query = CourseEdition::where('course_id', $courseId);

        return $row->startDate === null
            ? $query->whereNull('start_date')->where('year', $row->year)->first()
            : $query->whereDate('start_date', $row->startDate)->whereDate('end_date', $row->endDate)->first();
    }

    /**
     * El ponente del libro histórico se registra (o reutiliza) en teachers por DNI
     * y se asigna a la edición si esta aún no tiene ponente.
     */
    private function linkTeacher(CourseEdition $edition, ImportRow $row): void
    {
        $teacher = Teacher::firstOrCreate(['dni' => $row->dni], ['full_name' => trim("{$row->lastName} {$row->firstName}")]);

        if ($edition->teacher_id === null) {
            $edition->update(['teacher_id' => $teacher->id]);
        }
    }

    /**
     * Cursos del libro histórico que no existen todavía (se crearán al importar).
     *
     * @param  list<ImportRow>  $rows
     * @return Collection<int, array{code: string, name: string}>
     */
    private function coursesToCreate(array $rows): Collection
    {
        $candidates = collect($rows)
            ->filter(fn (ImportRow $r) => $r->historical && $r->courseName !== null && $r->courseCode !== '')
            ->unique('courseCode');

        $existing = Course::whereIn('code', $candidates->pluck('courseCode'))->pluck('code');

        return $candidates
            ->reject(fn (ImportRow $r) => $existing->contains($r->courseCode))
            ->map(fn (ImportRow $r) => ['code' => $r->courseCode, 'name' => $r->courseName])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function normalize(int $line, array $raw): ImportRow
    {
        $text = fn (string $key) => ($v = trim((string) ($raw[$key] ?? ''))) === '' ? null : $v;

        $start = $this->parseDate($raw['fecha_inicio'] ?? null);
        $end = $this->parseDate($raw['fecha_fin'] ?? null);
        $year = $text('anio') !== null ? (int) $text('anio') : ($start ? (int) substr($start, 0, 4) : null);

        $row = new ImportRow(
            line: $line,
            dni: mb_strtoupper(preg_replace('/\s+/', '', (string) $text('dni'))),
            firstName: (string) $text('nombres'),
            lastName: (string) $text('apellidos'),
            email: $text('email') !== null ? mb_strtolower($text('email')) : null,
            phone: $text('telefono'),
            courseCode: mb_strtoupper((string) $text('codigo_curso')),
            startDate: $start,
            endDate: $end,
            year: $year,
            registrationCode: $text('codigo_registro') !== null ? mb_strtoupper($text('codigo_registro')) : null,
            grade: $text('nota') !== null ? str_replace(',', '.', $text('nota')) : null,
        );

        // En la plantilla, el código histórico debe tener el formato actual.
        if ($row->registrationCode !== null) {
            $expected = sprintf('/^%s-%s-\d{2,}-%03d$/', preg_quote((string) config('academic.registration_prefix', 'HGC'), '/'), preg_quote($row->courseCode, '/'), ($row->year ?? 0) % 1000);
            if (! preg_match($expected, $row->registrationCode)) {
                $row->errors[] = 'El código de registro no coincide con el formato HGC-'.$row->courseCode.'-NN-'.sprintf('%03d', ($row->year ?? 0) % 1000).'.';
            }
        }

        return $row;
    }

    /**
     * Validación de campos de una fila (ambos formatos). Las filas históricas
     * no exigen fechas.
     */
    private function validateFields(ImportRow $row): void
    {
        $dateRules = $row->historical
            ? ['fecha_inicio' => ['nullable', 'date'], 'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio']]
            : ['fecha_inicio' => ['required', 'date'], 'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio']];

        $validator = Validator::make([
            'dni' => $row->dni,
            'nombres' => $row->firstName,
            'apellidos' => $row->lastName,
            'email' => $row->email,
            'telefono' => $row->phone,
            'codigo_curso' => $row->courseCode,
            'fecha_inicio' => $row->startDate,
            'fecha_fin' => $row->endDate,
            'anio' => $row->year,
            'nota' => $row->grade,
        ], [
            'dni' => ['required', 'regex:/^[0-9A-Z]{6,15}$/'],
            'nombres' => ['required', 'max:100'],
            'apellidos' => ['required', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'regex:/^[0-9+\s-]{6,20}$/'],
            'codigo_curso' => ['required', 'max:10', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/'],
            ...$dateRules,
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'nota' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,20'],
        ], [
            'dni.required' => 'Falta el DNI.',
            'dni.regex' => 'DNI inválido (6 a 15 letras o números).',
            'nombres.required' => 'Faltan los nombres (en el libro: "APELLIDOS Y NOMBRES" necesita al menos dos palabras).',
            'telefono.regex' => 'Teléfono inválido.',
            'codigo_curso.regex' => 'Código de curso inválido.',
            'fecha_inicio.required' => 'Fecha de inicio vacía o con formato no reconocido (usa AAAA-MM-DD o DD/MM/AAAA).',
            'fecha_fin.required' => 'Fecha de fin vacía o con formato no reconocido (usa AAAA-MM-DD o DD/MM/AAAA).',
        ]);

        array_push($row->errors, ...$validator->errors()->all());

        if ($row->isSpeaker() && $row->grade !== null) {
            $row->errors[] = 'Los ponentes no llevan nota.';
        }
    }

    /**
     * Comprobaciones que requieren la base de datos o el archivo completo.
     *
     * @param  list<ImportRow>  $rows
     */
    private function validateAgainstDatabase(array $rows): void
    {
        $courses = Course::whereIn('code', collect($rows)->pluck('courseCode')->unique()->filter())->pluck('id', 'code');

        // Códigos de registro: únicos dentro del archivo y respecto a la BD.
        $fileCodes = collect($rows)->pluck('registrationCode')->filter();
        $codeLines = collect($rows)->filter(fn (ImportRow $r) => $r->registrationCode !== null)->groupBy('registrationCode');
        $existing = Enrollment::query()
            ->whereIn('registration_code', $fileCodes->unique())
            ->with('student:id,dni', 'courseEdition:id,course_id,start_date,end_date,year')
            ->get()
            ->keyBy('registration_code');

        // Mismo participante dos veces en la misma edición dentro del archivo.
        $seen = [];

        foreach ($rows as $row) {
            if ($row->courseCode !== '' && ! $courses->has($row->courseCode) && ! ($row->historical && $row->courseName !== null)) {
                $row->errors[] = "El curso {$row->courseCode} no existe. Créalo antes de importar.";
            }

            $key = "{$row->dni}|{$row->courseCode}|{$row->year}|{$row->startDate}|{$row->endDate}";
            if ($row->dni !== '' && isset($seen[$key])) {
                $row->errors[] = "Fila duplicada: el mismo participante y edición ya aparece en {$seen[$key]}.";
            }
            $seen[$key] ??= mb_strtolower($row->origin());

            if ($row->registrationCode === null) {
                continue;
            }

            $sameCode = $codeLines->get($row->registrationCode);
            if ($sameCode->count() > 1) {
                $others = $sameCode->reject(fn (ImportRow $r) => $r === $row)->map(fn (ImportRow $r) => mb_strtolower($r->origin()))->implode(', ');
                $row->errors[] = "El código {$row->registrationCode} está repetido en el archivo (también en {$others}).";
            }

            $match = $existing->get($row->registrationCode);
            // Se permite si es exactamente la misma matrícula (reimportación).
            if ($match && ! ($match->student->dni === $row->dni
                && $match->courseEdition->course_id === $courses->get($row->courseCode)
                && $match->courseEdition->year === $row->year
                && $match->courseEdition->start_date?->toDateString() === $row->startDate
                && $match->courseEdition->end_date?->toDateString() === $row->endDate)) {
                $row->errors[] = "El código {$row->registrationCode} ya pertenece a otra matrícula.";
            }
        }

        // Una edición existente con las mismas fechas pero otro año sería incoherente.
        foreach ($rows as $row) {
            if (! $row->isValid() || $row->startDate === null || ! $courses->has($row->courseCode)) {
                continue;
            }

            $edition = CourseEdition::where('course_id', $courses[$row->courseCode])
                ->whereDate('start_date', $row->startDate)
                ->whereDate('end_date', $row->endDate)
                ->first(['id', 'year']);

            if ($edition && $edition->year !== $row->year) {
                $row->errors[] = "Ya existe esa edición con año {$edition->year}, distinto del indicado ({$row->year}).";
            }
        }
    }

    /**
     * Ajusta last_number de cada (curso, año) al mayor NN histórico del archivo.
     *
     * @param  list<ImportRow>  $rows
     * @param  Collection<string, Course>  $courses
     */
    private function bumpSequences(array $rows, Collection $courses): void
    {
        collect($rows)
            ->filter(fn (ImportRow $r) => $r->registrationNumber() !== null)
            ->groupBy(fn (ImportRow $r) => $r->courseCode.'|'.$r->year)
            ->each(function (Collection $group) use ($courses): void {
                /** @var ImportRow $first */
                $first = $group->first();
                $courseId = $courses[$first->courseCode]->id;
                $max = $group->max(fn (ImportRow $r) => $r->registrationNumber());

                RegistrationSequence::query()->insertOrIgnore([
                    'course_id' => $courseId, 'year' => $first->year, 'last_number' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $sequence = RegistrationSequence::where('course_id', $courseId)
                    ->where('year', $first->year)->lockForUpdate()->firstOrFail();

                if ($max > $sequence->last_number) {
                    $sequence->update(['last_number' => $max]);
                }
            });
    }

    /**
     * Acepta fechas de Excel (número de serie), AAAA-MM-DD y DD/MM/AAAA.
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        // Formato peruano DD/MM/AAAA (nunca MM/DD). Carbon 3 lanza excepción si el
        // formato no coincide, por eso cada intento va en su propio try.
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }
}
