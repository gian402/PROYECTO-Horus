<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentRole;
use App\Models\Certificate;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel con indicadores. Las cifras de ediciones, matrículas y certificados
 * respetan el alcance del usuario (el Ponente solo ve las de sus ediciones).
 * Todo sale de consultas agregadas: no hay datos de relleno.
 */
class DashboardController extends Controller
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $now = now();
        $year = (int) $now->format('Y');
        $monthStart = $now->copy()->startOfMonth();
        $previousStart = $monthStart->copy()->subMonth();

        $visibleEditions = fn () => CourseEdition::query()->visibleTo($user);
        $visibleEnrollments = fn () => Enrollment::query()
            ->whereIn('course_edition_id', $visibleEditions()->select('id'));
        $visibleParticipants = fn () => $visibleEnrollments()->where('role', EnrollmentRole::Participante->value);
        $visibleCertificates = fn () => Certificate::query()
            ->whereIn('enrollment_id', $visibleEnrollments()->select('id'));

        // Indicadores académicos: solo participantes (los ponentes no se califican).
        $byStatus = $visibleParticipants()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $canSeeAll = $user->can('students.view') || $user->can('students.manage');

        return Inertia::render('Dashboard', [
            'year' => $year,
            'stats' => [
                'students' => $canSeeAll ? Student::count() : null,
                'editions_this_year' => $visibleEditions()->where('year', $year)->count(),
                'enrollments' => (int) $byStatus->sum(),
                'cursando' => (int) ($byStatus['cursando'] ?? 0),
                'aprobado' => (int) ($byStatus['aprobado'] ?? 0),
                'desaprobado' => (int) ($byStatus['desaprobado'] ?? 0),
                'active_certificates' => $visibleCertificates()->valid()->count(),
            ],
            // Este mes frente al mes anterior (para las flechas de tendencia).
            'trends' => [
                'students' => $canSeeAll ? $this->trend(Student::query(), 'created_at', $monthStart, $previousStart) : null,
                'editions' => $this->trend($visibleEditions(), 'created_at', $monthStart, $previousStart),
                'enrollments' => $this->trend($visibleParticipants(), 'created_at', $monthStart, $previousStart),
                'certificates' => $this->trend($visibleCertificates(), 'issued_at', $monthStart, $previousStart),
            ],
            'monthly' => $this->monthlySeries($visibleParticipants(), $visibleCertificates(), $year),
            'recentEnrollments' => $visibleEnrollments()
                ->with(['student:id,first_name,last_name', 'courseEdition:id,course_id', 'courseEdition.course:id,code,name'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Enrollment $e) => [
                    'id' => $e->id,
                    'student' => "{$e->student->first_name} {$e->student->last_name}",
                    'course' => $e->courseEdition->course->name,
                    'role' => $e->role->value,
                    'registration_code' => $e->registration_code,
                    'created_at' => $e->created_at?->toIso8601String(),
                ]),
            'recentEditions' => $visibleEditions()
                ->with(['course:id,code,name', 'teacher:id,full_name'])
                ->withCount([
                    'enrollments as participants_count' => fn ($q) => $q->where('role', EnrollmentRole::Participante->value),
                    'enrollments as approved_count' => fn ($q) => $q->where('role', EnrollmentRole::Participante->value)->where('status', 'aprobado'),
                    'enrollments as graded_count' => fn ($q) => $q->where('role', EnrollmentRole::Participante->value)->whereIn('status', ['aprobado', 'desaprobado']),
                ])
                ->orderByDesc('year')
                ->orderByRaw('start_date DESC NULLS LAST')
                ->limit(5)
                ->get()
                ->map(fn (CourseEdition $e) => [
                    'id' => $e->id,
                    'label' => $e->label(),
                    'course' => $e->course->name,
                    'code' => $e->course->code,
                    'teacher' => $e->teacher?->full_name,
                    'start_date' => $e->start_date?->toDateString(),
                    'end_date' => $e->end_date?->toDateString(),
                    'year' => $e->year,
                    'participants_count' => $e->participants_count,
                    // Porcentaje de aprobados entre quienes ya tienen nota; null si aún no hay notas.
                    'approval_rate' => $e->graded_count > 0 ? (int) round($e->approved_count / $e->graded_count * 100) : null,
                ]),
            'canSeeAll' => $canSeeAll,
        ]);
    }

    /**
     * Registros creados este mes y el mes anterior.
     *
     * @param  Builder<*>  $query
     * @return array{current: int, previous: int}
     */
    private function trend(Builder $query, string $column, CarbonInterface $monthStart, CarbonInterface $previousStart): array
    {
        return [
            'current' => (clone $query)->where($column, '>=', $monthStart)->count(),
            'previous' => (clone $query)->where($column, '>=', $previousStart)->where($column, '<', $monthStart)->count(),
        ];
    }

    /**
     * Matrículas y certificados emitidos en cada mes del año (siempre 12 puntos).
     *
     * @param  Builder<Enrollment>  $participants
     * @param  Builder<Certificate>  $certificates
     * @return list<array{month: int, label: string, enrollments: int, certificates: int}>
     */
    private function monthlySeries(Builder $participants, Builder $certificates, int $year): array
    {
        $from = now()->setYear($year)->startOfYear();
        $to = $from->copy()->endOfYear();

        $enrollments = $participants->toBase()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('extract(month from created_at)::int as month, count(*) as total')
            ->groupByRaw('extract(month from created_at)')
            ->pluck('total', 'month');

        $issued = $certificates->toBase()
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw('extract(month from issued_at)::int as month, count(*) as total')
            ->groupByRaw('extract(month from issued_at)')
            ->pluck('total', 'month');

        return collect(self::MONTHS)->map(fn (string $label, int $i) => [
            'month' => $i + 1,
            'label' => $label,
            'enrollments' => (int) ($enrollments[$i + 1] ?? 0),
            'certificates' => (int) ($issued[$i + 1] ?? 0),
        ])->all();
    }
}
