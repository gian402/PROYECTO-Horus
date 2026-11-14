<?php

use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseEditionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Public\ParticipantPortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureParticipantSession;
use Illuminate\Support\Facades\Route;

// El CRM en sí (/dashboard y todo lo demás) es solo para el personal.
// Lo público son dos cosas sin cuenta: verificar un certificado, y el
// portal del participante para consultar lo suyo.
Route::redirect('/', '/dashboard')->name('home');

// Verificación pública de certificados (solo lectura, con límite de consultas).
Route::get('validar/{hash}', CertificateVerificationController::class)
    ->where('hash', '[0-9a-f]{64}')
    ->middleware('throttle:verification')
    ->name('certificates.verify');

// Portal del participante: identificación con DNI + código de registro, sin cuenta.
Route::prefix('mi-cuenta')->name('portal.')->group(function () {
    Route::get('/', [ParticipantPortalController::class, 'create'])->name('login');
    Route::post('/', [ParticipantPortalController::class, 'store'])
        ->middleware('throttle:portal-login')
        ->name('authenticate');
    Route::post('salir', [ParticipantPortalController::class, 'destroy'])->name('logout');

    Route::middleware(EnsureParticipantSession::class)->group(function () {
        Route::get('panel', [ParticipantPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('certificados/{certificate}', [ParticipantPortalController::class, 'downloadCertificate'])
            ->name('certificates.download');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // La autorización fina se aplica en Policies (Gate::authorize / Form Requests).
    Route::resource('students', StudentController::class);
    Route::resource('courses', CourseController::class)->except('show');
    Route::resource('teachers', TeacherController::class)->except('show');
    Route::resource('editions', CourseEditionController::class);

    // Matrículas y notas (desde la página de la edición).
    Route::get('editions/{edition}/students/search', [EnrollmentController::class, 'searchStudents'])
        ->name('editions.students.search');
    Route::post('editions/{edition}/enrollments', [EnrollmentController::class, 'store'])
        ->name('editions.enrollments.store');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])
        ->name('enrollments.destroy');
    Route::put('editions/{edition}/grades', [GradeController::class, 'update'])
        ->name('editions.grades.update');

    // Certificados.
    Route::post('enrollments/{enrollment}/certificate', [CertificateController::class, 'store'])
        ->name('certificates.store');
    Route::post('enrollments/{enrollment}/certificate/reissue', [CertificateController::class, 'reissue'])
        ->name('certificates.reissue');
    Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])
        ->name('certificates.revoke');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');

    // Importación histórica (subir y validar → confirmar).
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports/preview', [ImportController::class, 'preview'])->name('imports.preview');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::delete('imports', [ImportController::class, 'destroy'])->name('imports.destroy');

    // Reportes.
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
});

require __DIR__.'/settings.php';
