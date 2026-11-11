<?php

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\RegistrationSequence;
use App\Models\Student;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/*
|--------------------------------------------------------------------------
| Concurrencia REAL del correlativo HGC-{CURSO}-{NN}-{AAA}
|--------------------------------------------------------------------------
|
| Cada matrícula se ejecuta en un proceso PHP independiente con su propia
| conexión a PostgreSQL. Los datos se confirman (DatabaseTruncation, no
| RefreshDatabase) para que los procesos los vean.
|
*/

function concurrencyEnv(): array
{
    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => 'horus_prueba_test',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];
}

function workerCommand(int $studentId, int $editionId, int $startAtUs): array
{
    return [PHP_BINARY, base_path('tests/Concurrency/enroll-worker.php'), (string) $studentId, (string) $editionId, (string) $startAtUs];
}

/**
 * @return array<int, array<string, mixed>>
 */
function parseWorkerResults(iterable $results): array
{
    $parsed = [];

    foreach ($results as $result) {
        $line = trim((string) collect(preg_split('/\R/', trim($result->output())))->last());
        $parsed[] = json_decode($line, true) ?? ['ok' => false, 'error' => trim($result->output().' '.$result->errorOutput())];
    }

    return $parsed;
}

/**
 * @return list<string>
 */
function expectedCodes(string $course, int $count, string $year = '024'): array
{
    return array_map(
        fn (int $n) => sprintf('HGC-%s-%02d-%s', $course, $n, $year),
        range(1, $count),
    );
}

beforeEach(function () {
    $this->pa = Course::factory()->create(['code' => 'PA']);
    $this->edition = CourseEdition::factory()->for($this->pa)->create([
        'start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024,
    ]);
});

it('assigns unique consecutive codes to 20 simultaneous processes', function () {
    $students = Student::factory()->count(20)->create();
    // Sin fila contador previa: también compiten en el INSERT ... ON CONFLICT DO NOTHING.
    expect(RegistrationSequence::count())->toBe(0);

    $startAt = (int) (microtime(true) * 1_000_000) + 3_000_000;

    $results = Process::pool(function (Pool $pool) use ($students, $startAt) {
        foreach ($students as $student) {
            $pool->env(concurrencyEnv())->timeout(60)
                ->command(workerCommand($student->id, $this->edition->id, $startAt));
        }
    })->start()->wait();

    $parsed = parseWorkerResults($results);
    $failures = array_values(array_filter($parsed, fn ($r) => ! ($r['ok'] ?? false)));
    $codes = array_column($parsed, 'code');
    sort($codes);

    expect($failures)->toBe([])
        ->and($codes)->toBe(expectedCodes('PA', 20))
        ->and(Enrollment::count())->toBe(20)
        ->and(Enrollment::distinct('registration_code')->count('registration_code'))->toBe(20)
        ->and(RegistrationSequence::sole()->last_number)->toBe(20);
});

it('serializes processes on the locked sequence row until the holder commits', function () {
    $students = Student::factory()->count(10)->create();
    RegistrationSequence::create(['course_id' => $this->pa->id, 'year' => 2024, 'last_number' => 0]);

    // Esta prueba mantiene el bloqueo de la fila contador.
    DB::beginTransaction();
    RegistrationSequence::where('course_id', $this->pa->id)->where('year', 2024)->lockForUpdate()->sole();

    $startAt = (int) (microtime(true) * 1_000_000);
    $pool = Process::pool(function (Pool $pool) use ($students, $startAt) {
        foreach ($students as $student) {
            $pool->env(concurrencyEnv())->timeout(60)
                ->command(workerCommand($student->id, $this->edition->id, $startAt));
        }
    })->start();

    // Tiempo suficiente para que todos arranquen y lleguen al SELECT ... FOR UPDATE.
    usleep(4_000_000);

    $stillRunning = $pool->running()->count();
    $committedWhileLocked = DB::connection()->table('enrollments')->count();

    DB::commit();

    $parsed = parseWorkerResults($pool->wait());
    $codes = array_column($parsed, 'code');
    sort($codes);

    expect($stillRunning)->toBe(10)
        ->and($committedWhileLocked)->toBe(0)
        ->and(array_filter($parsed, fn ($r) => ! ($r['ok'] ?? false)))->toBe([])
        ->and($codes)->toBe(expectedCodes('PA', 10));
});

it('does not block enrollments of a different course', function () {
    $iny = CourseEdition::factory()
        ->for(Course::factory()->create(['code' => 'INY']))
        ->create(['start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024]);
    $student = Student::factory()->create();
    RegistrationSequence::create(['course_id' => $this->pa->id, 'year' => 2024, 'last_number' => 0]);

    DB::beginTransaction();
    RegistrationSequence::where('course_id', $this->pa->id)->lockForUpdate()->sole();

    /** @var InvokedProcess $process */
    $process = Process::env(concurrencyEnv())->timeout(30)
        ->start(workerCommand($student->id, $iny->id, (int) (microtime(true) * 1_000_000)));
    $result = $process->wait();

    DB::commit();

    $parsed = parseWorkerResults([$result]);

    expect($parsed[0]['ok'] ?? false)->toBeTrue()
        ->and($parsed[0]['code'])->toBe('HGC-INY-01-024');
});
