<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel con indicadores. Las cifras de ediciones y matrículas respetan el
 * alcance del usuario (el Ponente solo ve las de sus ediciones).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $year = (int) now()->format('Y');

        $visibleEditions = fn () => CourseEdition::query()->visibleTo($user);
        $visibleEnrollments = fn () => Enrollment::query()
            ->whereIn('course_edition_id', $visibleEditions()->select('id'));

        // Indicadores académicos: solo participantes (los ponentes no se califican).
        $byStatus = $visibleEnrollments()->toBase()
            ->where('role', 'participante')
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
                'active_certificates' => Certificate::query()->valid()
                    ->whereIn('enrollment_id', $visibleEnrollments()->select('id'))->count(),
            ],
            'recentEditions' => $visibleEditions()
                ->with('course:id,code,name')
                ->withCount('enrollments')
                ->orderByDesc('year')
                ->orderByRaw('start_date DESC NULLS LAST')
                ->limit(5)
                ->get()
                ->map(fn (CourseEdition $e) => [
                    'id' => $e->id,
                    'label' => $e->label(),
                    'course' => $e->course->name,
                    'enrollments_count' => $e->enrollments_count,
                ]),
            'canSeeAll' => $canSeeAll,
        ]);
    }
}
