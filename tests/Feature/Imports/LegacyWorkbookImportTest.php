<?php

use App\Enums\EnrollmentRole;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\RegistrationCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
| Libro de cursos con el mismo diseño que el Excel histórico real
| (una hoja por curso, panel CONSULTA, filas PONENTE). Datos sintéticos.
*/

/**
 * @param  array<string, list<array<int, mixed>>>  $sheets  título de hoja => filas desde A1
 */
function legacyWorkbook(array $sheets): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $title => $rows) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($rows, null, 'A1', true);

        // Como en el original: título y "APELLIDOS Y NOMBRES" en celdas combinadas.
        if (count($rows) > 1 && ($rows[1][1] ?? null) === 'APELLIDOS Y NOMBRES') {
            $sheet->mergeCells('A1:G1');
            $sheet->mergeCells('B2:D2');
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'horus').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('CURSOS 2024.xlsx', $content);
}

/**
 * @return array<string, list<array<int, mixed>>>
 */
function sampleSheets(): array
{
    return [
        'INICIO¬' => [
            [null],
            [null],
            ['REGISTROS CURSOS HGC - 2024'],
            [null, null, null, 'PRIMEROS AUXILIOS', null, null, 'PA'],
        ],
        'PRIMERO AUXILIOS-2024' => [
            ['PRIMEROS AUXILIOS'],
            ['NRO', 'APELLIDOS Y NOMBRES', null, null, 'NOTA', 'DNI', 'CÓD. REGISTRO', null, '// CONSULTA //'],
            [1, 'Ambrosio Díaz Lorena', null, null, 15, 47925805, 'HGC-PA-01-024', null, 'DNI', 'NRO', 'APELLIDOS Y NOMBRES', null, null, null, 'CÓD. REGISTRO'],
            [2, 'Calua Tanta Maria Elena', null, null, 12, '71983552', 'HGC-PA-02-024', null, 71983552, '=1/0', '=1/0'],
            [5, 'De la Cruz Pérez Juan Carlos', null, null, 16, 70000005, 'HGC-PA-05-024'],
        ],
        'NUTRICION Y DISTEAS' => [
            ['NUTRICIÓN Y DIETAS'],
            ['NRO', 'APELLIDOS Y NOMBRES', null, null, 'DNI', 'CÓD. REGISTRO'],
            [1, 'Santillan Julcamoro Erick Josue', null, null, 77679452, 'HGC-NUT-DIET-01-2024'],
            [2, 'Plasencia Portal Nesthi', null, null, 8474930, 'HGC-NUT-DIET-02-2024'],
            ['PONENTE'],
            [3, 'Sánchez Pizán Cynthia Elizabeth', null, null, 43116454, 'HGC-NUT-DIET-03-2024'],
        ],
        'SALUD MENTAL' => [
            ['SALUD MENTAL'],
            ['NRO', 'APELLIDOS Y NOMBRES', null, null, 'DNI', 'CELULAR', 'CÓD. REGISTRO'],
            [1, 'Casimiro Eustaquio Yessica', null, null, 70085392, '964 587 381', 'HGC-SM-01-2024'],
            [10, 'Rojas Cotrina Nelly', null, null, 71763297, '950 264 210', 'HGC-SM-010-2024'],
            ['PONENTES'],
            [26],
            [11, 'Sánchez Pizán Cynthia Elizabeth', null, null, 43116454, null, 'HGC-SM-011-2024'],
        ],
    ];
}

beforeEach(function () {
    Storage::fake('local');
    actingAsRole('Coordinador Académico');
});

it('previews the historical workbook without writing anything', function () {
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('imports.index'));

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.format', 'legacy')
        ->where('preview.total', 9)
        ->where('preview.invalid', 0)
        ->where('preview.speakers', 2)
        ->where('preview.courses_to_create', [
            ['code' => 'PA', 'name' => 'PRIMEROS AUXILIOS'],
            ['code' => 'NUT-DIET', 'name' => 'NUTRICIÓN Y DIETAS'],
            ['code' => 'SM', 'name' => 'SALUD MENTAL'],
        ])
        ->where('preview.warnings.0.origin', 'Hoja NUTRICION Y DISTEAS, fila 4')
        ->where('preview.warnings.0.warnings.0', 'El DNI tenía 7 dígitos: se completó con un cero (08474930).'));

    expect(Course::count())->toBe(0)->and(Enrollment::count())->toBe(0);
});

it('imports courses, undated editions, approved participants and speakers', function () {
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())]);
    $this->post(route('imports.store'))->assertInertiaFlash('toast.type', 'success');

    expect(Course::pluck('name', 'code')->all())->toEqualCanonicalizing([
        'PA' => 'PRIMEROS AUXILIOS', 'NUT-DIET' => 'NUTRICIÓN Y DIETAS', 'SM' => 'SALUD MENTAL',
    ])
        ->and(CourseEdition::count())->toBe(3)
        ->and(CourseEdition::whereNull('start_date')->where('year', 2024)->count())->toBe(3)
        ->and(Enrollment::count())->toBe(9)
        ->and(Student::count())->toBe(8)
        ->and(Enrollment::where('historical', true)->count())->toBe(9);

    // Nota del libro → estado derivado; sin nota → aprobado (histórico).
    $calua = Enrollment::firstWhere('registration_code', 'HGC-PA-02-024');
    $santillan = Enrollment::firstWhere('registration_code', 'HGC-NUT-DIET-01-2024');
    expect($calua->status->value)->toBe('desaprobado')
        ->and($santillan->status->value)->toBe('aprobado')
        ->and($santillan->grade)->toBeNull();

    // Ponentes: matrícula con rol y registro en teachers, reutilizado entre cursos.
    $speakerCodes = Enrollment::where('role', EnrollmentRole::Ponente)->pluck('registration_code')->sort()->values()->all();
    expect($speakerCodes)->toBe(['HGC-NUT-DIET-03-2024', 'HGC-SM-011-2024'])
        ->and(Teacher::count())->toBe(1)
        ->and(Teacher::sole()->full_name)->toBe('Sánchez Pizán Cynthia Elizabeth')
        ->and(CourseEdition::whereNotNull('teacher_id')->count())->toBe(2);

    // Normalización: apellidos compuestos, DNI de 7 dígitos y celular.
    expect(Student::firstWhere('dni', '70000005'))
        ->last_name->toBe('De la Cruz Pérez')
        ->first_name->toBe('Juan Carlos')
        ->and(Student::where('dni', '08474930')->exists())->toBeTrue()
        ->and(Student::firstWhere('dni', '70085392')->phone)->toBe('964587381');
});

it('continues the correlative after the highest historical number', function () {
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())]);
    $this->post(route('imports.store'));

    $codes = app(RegistrationCodeGenerator::class);
    $sm = CourseEdition::whereHas('course', fn ($q) => $q->where('code', 'SM'))->sole();
    $pa = CourseEdition::whereHas('course', fn ($q) => $q->where('code', 'PA'))->sole();

    expect($codes->next($sm))->toBe('HGC-SM-12-024')
        ->and($codes->next($pa))->toBe('HGC-PA-06-024');
});

it('is idempotent when the workbook is imported twice', function () {
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())]);
    $this->post(route('imports.store'));
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.invalid', 0)
        ->where('preview.courses_to_create', []));

    $this->post(route('imports.store'))->assertInertiaFlash('toast.type', 'success');

    expect(Enrollment::count())->toBe(9)
        ->and(CourseEdition::count())->toBe(3)
        ->and(Course::count())->toBe(3)
        ->and(Teacher::count())->toBe(1);
});

it('reuses existing courses instead of creating them', function () {
    Course::factory()->create(['code' => 'PA', 'name' => 'Primeros Auxilios']);

    $this->post(route('imports.preview'), ['file' => legacyWorkbook(sampleSheets())]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.courses_to_create', fn ($courses) => collect($courses)->pluck('code')->all() === ['NUT-DIET', 'SM']));
});

it('reports the problems found in the real workbook', function (array $row, string $message) {
    $sheets = sampleSheets();
    $sheets['SALUD MENTAL'][] = $row;

    $this->post(route('imports.preview'), ['file' => legacyWorkbook($sheets)]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.invalid', fn ($n) => $n >= 1)
        ->where('preview.errors', fn ($errors) => collect($errors)->pluck('errors')->flatten()->contains(fn ($e) => str_contains($e, $message))));

    $this->post(route('imports.store'))->assertInertiaFlash('toast.type', 'error');
    expect(Enrollment::count())->toBe(0);
})->with([
    'código repetido' => [[24, 'Asencio Castillo Jhon David', null, null, 73929262, null, 'HGC-SM-01-2024'], 'repetido en el archivo'],
    'sin DNI' => [[12, 'Castrejon Herrera Segundo', null, null, null, null, 'HGC-SM-012-2024'], 'Falta el DNI.'],
    'código de otro curso' => [[13, 'Perez Rojas Ana', null, null, 70000013, null, 'HGC-SSO-013-2024'], 'no corresponde al curso SM'],
    'código de otro año' => [[14, 'Perez Rojas Ana', null, null, 70000014, null, 'HGC-SM-014-2023'], 'no corresponde al curso SM del año 2024'],
    'formato irreconocible' => [[15, 'Perez Rojas Ana', null, null, 70000015, null, 'SM-15'], 'formato reconocido'],
]);

it('points to the sheet and row of each error', function () {
    $sheets = sampleSheets();
    $sheets['SALUD MENTAL'][] = [12, 'Castrejon Herrera Segundo', null, null, null, null, 'HGC-SM-012-2024'];

    $this->post(route('imports.preview'), ['file' => legacyWorkbook($sheets)]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.errors.0.origin', 'Hoja SALUD MENTAL, fila 8'));
});

it('rejects a workbook without any course sheet', function () {
    $this->post(route('imports.preview'), ['file' => legacyWorkbook(['INICIO¬' => [['REGISTROS CURSOS HGC - 2024']]])])
        ->assertSessionHasErrors('file');
});
