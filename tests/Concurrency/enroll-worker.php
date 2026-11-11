<?php

/*
|--------------------------------------------------------------------------
| Proceso trabajador para la prueba de concurrencia real
|--------------------------------------------------------------------------
|
| Uso: php tests/Concurrency/enroll-worker.php {student_id} {edition_id} {start_at_us}
|
| Cada proceso tiene su propia conexión PostgreSQL. Espera hasta el instante
| común start_at (microsegundos) para maximizar la colisión y luego matricula
| con App\Actions\Enrollments\EnrollStudent. Imprime una línea JSON.
|
*/

use App\Actions\Enrollments\EnrollStudent;
use App\Models\CourseEdition;
use App\Models\Student;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $studentId, $editionId, $startAt] = array_pad($argv, 4, null);

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) DB::connection()->getDatabaseName();

if (DB::connection()->getDriverName() !== 'pgsql' || ! str_ends_with($database, '_test')) {
    fwrite(STDERR, "Worker abortado: base no segura ({$database}).\n");
    exit(2);
}

$student = Student::findOrFail((int) $studentId);
$edition = CourseEdition::findOrFail((int) $editionId);

// Conexión ya abierta antes del instante común, para que solo compitan las transacciones.
DB::connection()->getPdo();

$wait = (int) $startAt - (int) (microtime(true) * 1_000_000);
if ($wait > 0) {
    usleep($wait);
}

$startedAt = microtime(true);

try {
    $enrollment = app(EnrollStudent::class)->handle($student, $edition);

    echo json_encode([
        'ok' => true,
        'code' => $enrollment->registration_code,
        'student_id' => $student->id,
        'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ]), PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class.': '.$e->getMessage()]), PHP_EOL;
    exit(1);
}
