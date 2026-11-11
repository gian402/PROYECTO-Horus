<?php

use App\Enums\EnrollmentRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/*
| Datos del panel (tendencias, serie mensual, actividad y tabla de ediciones).
| Se congela el reloj a mitad de junio de 2026 para que "este mes" y "el mes
| anterior" sean siempre los mismos.
*/

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0));
    $this->edition = CourseEdition::factory()
        ->for(Course::factory()->create(['code' => 'PA']))
        ->create(['year' => 2026]);
});

it('compares this month with the previous month', function () {
    actingAsRole('Coordinador Académico');
    Student::factory()->count(2)->create(['created_at' => now()->subDays(3)]);
    Student::factory()->count(1)->create(['created_at' => now()->subMonth()]);
    Student::factory()->count(5)->create(['created_at' => now()->subMonths(3)]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('trends.students.current', 2)
        ->where('trends.students.previous', 1)
        ->where('stats.students', 8));
});

it('builds the monthly series of the year with participants only', function () {
    actingAsRole('Coordinador Académico');
    $first = Enrollment::factory()->for($this->edition, 'courseEdition')->approved(16)->create(['created_at' => '2026-03-10 10:00:00']);
    Enrollment::factory()->for($this->edition, 'courseEdition')->create(['created_at' => '2026-03-20 10:00:00']);
    // No cuentan: otro año y ponentes.
    Enrollment::factory()->for($this->edition, 'courseEdition')->create(['created_at' => '2025-03-20 10:00:00']);
    Enrollment::factory()->for($this->edition, 'courseEdition')->create(['role' => EnrollmentRole::Ponente, 'created_at' => '2026-03-11 10:00:00']);
    Certificate::factory()->for($first)->create(['issued_at' => '2026-04-02 09:00:00']);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('monthly', 12)
        ->where('monthly.0.label', 'Ene')
        ->where('monthly.2.enrollments', 2)
        ->where('monthly.2.certificates', 0)
        ->where('monthly.3.enrollments', 0)
        ->where('monthly.3.certificates', 1));
});

it('lists the five latest enrollments, speakers included but not counted', function () {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->count(6)->for($this->edition, 'courseEdition')->create();
    Enrollment::factory()->for($this->edition, 'courseEdition')->create(['role' => EnrollmentRole::Ponente]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('recentEnrollments', 5)
        ->where('recentEnrollments.0.role', 'ponente')
        ->where('stats.enrollments', 6));
});

it('shows participants and approval rate for each recent edition', function () {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->count(2)->for($this->edition, 'courseEdition')->approved(16)->create();
    Enrollment::factory()->for($this->edition, 'courseEdition')->failed(9)->create();
    // Cursando (sin nota) y ponente: no entran en el porcentaje.
    Enrollment::factory()->for($this->edition, 'courseEdition')->create();
    Enrollment::factory()->for($this->edition, 'courseEdition')->create(['role' => EnrollmentRole::Ponente]);
    CourseEdition::factory()->for(Course::factory()->create(['code' => 'INY']))->create(['year' => 2025]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('recentEditions', 2)
        ->where('recentEditions.0.code', 'PA')
        ->where('recentEditions.0.participants_count', 4)
        ->where('recentEditions.0.approval_rate', 67)
        ->where('recentEditions.1.code', 'INY')
        ->where('recentEditions.1.approval_rate', null));
});

it('limits every figure of a Ponente to their own editions', function () {
    $user = actingAsRole('Ponente');
    $this->edition->update(['teacher_id' => Teacher::factory()->create(['user_id' => $user->id])->id]);
    $other = CourseEdition::factory()->for(Course::factory()->create(['code' => 'INY']))->create(['year' => 2026]);
    Enrollment::factory()->count(2)->for($this->edition, 'courseEdition')->create(['created_at' => now()]);
    Enrollment::factory()->count(5)->for($other, 'courseEdition')->create(['created_at' => now()]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('stats.enrollments', 2)
        ->where('trends.enrollments.current', 2)
        ->where('monthly.5.enrollments', 2)
        ->has('recentEnrollments', 2)
        ->has('recentEditions', 1)
        ->where('recentEditions.0.code', 'PA'));
});

it('runs the same number of queries however much data there is', function () {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->count(2)->for($this->edition, 'courseEdition')->create();

    // La primera petición también carga los permisos (luego quedan en caché): se descarta.
    $this->get(route('dashboard'))->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('dashboard'))->assertOk();
    $small = $queries;

    foreach (range(1, 6) as $i) {
        $edition = CourseEdition::factory()->for(Course::factory()->create(['code' => "C{$i}"]))->create(['year' => 2026]);
        Enrollment::factory()->count(3)->for($edition, 'courseEdition')->approved(15)->create();
    }

    $queries = 0;
    $this->get(route('dashboard'))->assertOk();

    expect($queries)->toBe($small)->and($small)->toBeLessThan(40);
});
