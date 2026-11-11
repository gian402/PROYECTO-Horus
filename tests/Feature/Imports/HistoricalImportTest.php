<?php

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\RegistrationSequence;
use App\Models\Student;
use App\Services\Import\HistoricalImporter;
use App\Services\RegistrationCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<array<int, string|int|float|null>>  $rows
 */
function importCsv(array $rows, array $header = HistoricalImporter::COLUMNS): UploadedFile
{
    $lines = [implode(',', $header)];
    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? "\"{$v}\"" : (string) $v, $row));
    }

    return UploadedFile::fake()->createWithContent('historial.csv', implode("\n", $lines));
}

/**
 * @return array<int, string|int|null>
 */
function importRow(array $overrides = []): array
{
    $row = array_merge([
        'dni' => '70123456', 'nombres' => 'Ana', 'apellidos' => 'Quispe', 'email' => 'ana@ejemplo.com',
        'telefono' => '987654321', 'codigo_curso' => 'PA', 'fecha_inicio' => '2024-02-01', 'fecha_fin' => '2024-02-20',
        'anio' => 2024, 'codigo_registro' => '', 'nota' => '',
    ], $overrides);

    return array_values($row);
}

beforeEach(function () {
    Storage::fake('local');
    $this->pa = Course::factory()->create(['code' => 'PA']);
});

it('deletes the uploaded file after importing or discarding', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([importRow()])]);
    expect(Storage::disk('local')->files('imports'))->toHaveCount(1);

    $this->delete(route('imports.destroy'));
    expect(Storage::disk('local')->files('imports'))->toBeEmpty();

    $this->post(route('imports.preview'), ['file' => importCsv([importRow()])]);
    $this->post(route('imports.store'));
    expect(Storage::disk('local')->files('imports'))->toBeEmpty();
});

it('prunes abandoned previews older than a day', function () {
    Storage::disk('local')->put('imports/old.csv', 'x');
    Storage::disk('local')->put('imports/new.csv', 'x');
    touch(Storage::disk('local')->path('imports/old.csv'), now()->subDays(2)->getTimestamp());

    $this->artisan('horus:prune-imports')->assertSuccessful();

    expect(Storage::disk('local')->exists('imports/old.csv'))->toBeFalse()
        ->and(Storage::disk('local')->exists('imports/new.csv'))->toBeTrue();
});

describe('access', function () {
    it('allows only roles with imports.run', function (?string $role, int $status) {
        actingAsRole($role);

        $this->get(route('imports.index'))->assertStatus($status);
    })->with([
        ['Coordinador Académico', 200],
        ['Ponente', 403],
    ]);

    it('downloads the template', function () {
        actingAsRole('Coordinador Académico');

        $this->get(route('imports.template'))
            ->assertOk()
            ->assertDownload('plantilla-importacion-horus.xlsx');
    });
});

it('validates the whole file without writing anything (dry run)', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([
        importRow(),
        importRow(['dni' => '70000002', 'codigo_curso' => 'XYZ']),
        importRow(['dni' => '70000003', 'nota' => '25']),
        importRow(['dni' => '70000004', 'fecha_fin' => '2024-01-01']),
    ])])->assertRedirect(route('imports.index'));

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.total', 4)
        ->where('preview.valid', 1)
        ->where('preview.invalid', 3)
        ->where('preview.errors.0.line', 3)
        ->where('preview.errors.0.errors.0', 'El curso XYZ no existe. Créalo antes de importar.'));

    expect(Student::count())->toBe(0)
        ->and(Enrollment::count())->toBe(0)
        ->and(CourseEdition::count())->toBe(0);
});

it('refuses to import while there are errors (all or nothing)', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([
        importRow(),
        importRow(['dni' => '70000002', 'codigo_curso' => 'XYZ']),
    ])]);

    $this->post(route('imports.store'))->assertInertiaFlash('toast.type', 'error');

    expect(Enrollment::count())->toBe(0);
});

it('imports students, editions, enrollments and grades', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([
        importRow(['codigo_registro' => 'HGC-PA-05-024', 'nota' => '16']),
        importRow(['dni' => '70000002', 'nombres' => 'Luis', 'nota' => '12,5']),
        importRow(['dni' => '70000003', 'nombres' => 'Rosa', 'fecha_inicio' => '01/08/2024', 'fecha_fin' => '20/08/2024']),
    ])])->assertSessionHasNoErrors();

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page->where('preview.errors', []));

    $this->post(route('imports.store'))->assertInertiaFlash('toast.type', 'success');

    expect(Student::count())->toBe(3)
        ->and(CourseEdition::count())->toBe(2)
        ->and(Enrollment::count())->toBe(3)
        ->and(Enrollment::firstWhere('registration_code', 'HGC-PA-05-024')->status->value)->toBe('aprobado')
        ->and(Student::firstWhere('dni', '70000002')->enrollments()->first()->status->value)->toBe('desaprobado');

    // Los códigos generados continúan después del histórico más alto (05).
    $generated = Enrollment::where('registration_code', '!=', 'HGC-PA-05-024')->pluck('registration_code')->sort()->values()->all();
    expect($generated)->toBe(['HGC-PA-06-024', 'HGC-PA-07-024'])
        ->and(RegistrationSequence::sole()->last_number)->toBe(7);
});

it('keeps generating codes after the imported history', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([importRow(['codigo_registro' => 'HGC-PA-40-024'])])]);
    $this->post(route('imports.store'));

    $edition = CourseEdition::sole();

    expect(app(RegistrationCodeGenerator::class)->next($edition))->toBe('HGC-PA-41-024');
});

it('is idempotent when the same file is imported twice', function () {
    actingAsRole('Coordinador Académico');
    $file = fn () => importCsv([
        importRow(['codigo_registro' => 'HGC-PA-01-024', 'nota' => '15']),
        importRow(['dni' => '70000002']),
    ]);

    $this->post(route('imports.preview'), ['file' => $file()]);
    $this->post(route('imports.store'));
    $this->post(route('imports.preview'), ['file' => $file()]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page->where('preview.invalid', 0));

    $this->post(route('imports.store'));

    expect(Student::count())->toBe(2)
        ->and(Enrollment::count())->toBe(2)
        ->and(CourseEdition::count())->toBe(1);
});

it('does not overwrite current student data with historical data', function () {
    actingAsRole('Coordinador Académico');
    Student::factory()->create(['dni' => '70123456', 'first_name' => 'Ana Lucía', 'email' => 'actual@ejemplo.com', 'phone' => null]);

    $this->post(route('imports.preview'), ['file' => importCsv([importRow()])]);
    $this->post(route('imports.store'));

    expect(Student::sole())
        ->first_name->toBe('Ana Lucía')
        ->email->toBe('actual@ejemplo.com')
        ->phone->toBe('987654321');
});

it('detects registration code conflicts', function (array $rows, string $message) {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->create(['registration_code' => 'HGC-PA-09-024']);

    $this->post(route('imports.preview'), ['file' => importCsv($rows)]);

    $this->get(route('imports.index'))->assertInertia(fn ($page) => $page
        ->where('preview.invalid', fn ($n) => $n >= 1)
        ->where('preview.errors', fn ($errors) => collect($errors)->pluck('errors')->flatten()->contains(fn ($e) => str_contains($e, $message))));
})->with([
    'código de otra matrícula' => [[importRow(['codigo_registro' => 'HGC-PA-09-024'])], 'ya pertenece a otra matrícula'],
    'código repetido en el archivo' => [[importRow(['codigo_registro' => 'HGC-PA-02-024']), importRow(['dni' => '70000002', 'codigo_registro' => 'HGC-PA-02-024'])], 'repetido en el archivo'],
    'formato o año incorrecto' => [[importRow(['codigo_registro' => 'HGC-PA-02-025'])], 'no coincide con el formato'],
    'fila duplicada' => [[importRow(), importRow()], 'Fila duplicada'],
]);

it('reads DD/MM/YYYY dates as day/month and keeps DNI leading zeros', function () {
    actingAsRole('Coordinador Académico');

    // 05/08/2024 debe ser 5 de agosto, nunca 8 de mayo.
    $this->post(route('imports.preview'), ['file' => importCsv([
        importRow(['dni' => '00123456', 'fecha_inicio' => '05/08/2024', 'fecha_fin' => '12/08/2024']),
    ])]);
    $this->post(route('imports.store'));

    $edition = CourseEdition::sole();

    expect(Student::sole()->dni)->toBe('00123456')
        ->and($edition->start_date->toDateString())->toBe('2024-08-05')
        ->and($edition->end_date->toDateString())->toBe('2024-08-12');
});

it('rejects files without the required columns', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => importCsv([['x', 'y']], ['foo', 'bar'])])
        ->assertSessionHasErrors('file');
});

it('rejects non spreadsheet files', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('imports.preview'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');
});
