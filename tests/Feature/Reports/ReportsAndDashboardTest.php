<?php

use App\Enums\EnrollmentRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Teacher;

beforeEach(function () {
    $pa = Course::factory()->create(['code' => 'PA']);
    $nut = Course::factory()->create(['code' => 'NUT']);
    $this->pa2024 = CourseEdition::factory()->for($pa)->create(['start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024]);
    $this->nut2025 = CourseEdition::factory()->for($nut)->create(['start_date' => '2025-03-01', 'end_date' => '2025-03-20', 'year' => 2025]);

    $approved = Enrollment::factory()->for($this->pa2024, 'courseEdition')->approved(17)->create();
    Certificate::factory()->for($approved)->create();
    Enrollment::factory()->for($this->pa2024, 'courseEdition')->failed(9)->create();
    Enrollment::factory()->for($this->nut2025, 'courseEdition')->create();
});

describe('reports', function () {
    it('allows only roles with reports.view', function (?string $role, int $status) {
        actingAsRole($role);

        $this->get(route('reports.index'))->assertStatus($status);
        $this->get(route('reports.export'))->assertStatus($status);
    })->with([
        ['Coordinador Académico', 200],
        ['Ponente', 403],
    ]);

    it('computes totals for the whole data set', function () {
        actingAsRole('Coordinador Académico');

        $this->get(route('reports.index'))->assertInertia(fn ($page) => $page
            ->component('reports/Index')
            ->where('totals.total', 3)
            ->where('totals.aprobado', 1)
            ->where('totals.desaprobado', 1)
            ->where('totals.cursando', 1)
            ->where('totals.certificates', 1)
            ->where('totals.speakers', 0)
            ->has('rows.data', 3));
    });

    it('counts speakers apart so they do not inflate the approval rate', function () {
        // Hallado en el recorrido E2E: el ponente (aprobado sin nota) se sumaba a "Aprobados".
        actingAsRole('Coordinador Académico');
        Enrollment::factory()->for($this->pa2024, 'courseEdition')->create(['role' => EnrollmentRole::Ponente]);

        $this->get(route('reports.index'))->assertInertia(fn ($page) => $page
            ->where('totals.total', 4)
            ->where('totals.speakers', 1)
            ->where('totals.aprobado', 1)
            ->where('totals.desaprobado', 1)
            ->where('totals.cursando', 1)
            ->has('rows.data', 4));
    });

    it('filters by year, course, status and certificate', function (array $filters, int $expected) {
        actingAsRole('Coordinador Académico');

        $this->get(route('reports.index', $filters))
            ->assertInertia(fn ($page) => $page->where('totals.total', $expected)->has('rows.data', $expected));
    })->with([
        'año 2024' => [['year' => 2024], 2],
        'curso NUT' => [fn () => ['course_id' => Course::firstWhere('code', 'NUT')->id], 1],
        'aprobados' => [['status' => 'aprobado'], 1],
        'con certificado' => [['certificate' => 'with'], 1],
        'sin certificado' => [['certificate' => 'without'], 2],
        'combinado' => [['year' => 2024, 'status' => 'desaprobado'], 1],
    ]);

    it('rejects invalid filters', function () {
        actingAsRole('Coordinador Académico');

        $this->get(route('reports.index', ['status' => 'retirado']))->assertSessionHasErrors('status');
    });

    it('exports the filtered report to Excel', function () {
        actingAsRole('Coordinador Académico');

        $this->get(route('reports.export', ['year' => 2024]))
            ->assertOk()
            ->assertDownload('reporte-matriculas-'.now()->format('Y-m-d').'.xlsx');
    });
});

describe('dashboard', function () {
    it('shows global indicators to the coordination', function () {
        actingAsRole('Coordinador Académico');

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.enrollments', 3)
            ->where('stats.aprobado', 1)
            ->where('stats.active_certificates', 1)
            ->has('recentEditions', 2));
    });

    it('limits the indicators of a Ponente to their editions', function () {
        $user = actingAsRole('Ponente');
        $this->nut2025->update(['teacher_id' => Teacher::factory()->create(['user_id' => $user->id])->id]);

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('stats.enrollments', 1)
            ->where('stats.active_certificates', 0)
            ->has('recentEditions', 1));
    });

    it('works for a user without role', function () {
        actingAsRole(null);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('stats.enrollments', 0)
            ->where('stats.students', null));
    });
});
