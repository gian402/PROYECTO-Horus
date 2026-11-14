<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Editions\EditionRequest;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseEditionController extends Controller
{
    use InteractsWithToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CourseEdition::class);

        $filters = [
            'course_id' => $request->integer('course_id') ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'year' => $request->integer('year') ?: null,
            'status' => in_array($request->string('status')->toString(), ['en_curso', 'finalizada'], true)
                ? $request->string('status')->toString()
                : null,
        ];

        $editions = CourseEdition::query()
            ->visibleTo($request->user())
            ->with(['course:id,code,name', 'teacher:id,full_name'])
            ->withCount('enrollments')
            ->when($filters['course_id'], fn ($q, $id) => $q->where('course_id', $id))
            ->when($filters['teacher_id'], fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($filters['year'], fn ($q, $year) => $q->where('year', $year))
            ->when($filters['status'], fn ($q, $status) => $status === 'finalizada'
                ? $q->whereFinished()
                : $q->whereNotFinished())
            // Las ediciones históricas sin fechas van al final de su año.
            ->orderByDesc('year')
            ->orderByRaw(Sql::orderByDescNullsLast('start_date'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CourseEdition $e) => [
                'id' => $e->id,
                'label' => $e->label(),
                'course' => $e->course->only(['id', 'code', 'name']),
                'teacher' => $e->teacher?->only(['id', 'full_name']),
                'start_date' => $e->start_date?->toDateString(),
                'end_date' => $e->end_date?->toDateString(),
                'year' => $e->year,
                'enrollments_count' => $e->enrollments_count,
                'finished' => $e->isFinished(),
            ]);

        return Inertia::render('editions/Index', [
            'editions' => $editions,
            'filters' => $filters,
            'options' => $this->options(),
            'years' => CourseEdition::query()->visibleTo($request->user())
                ->distinct()->orderByDesc('year')->pluck('year'),
            // Cuántas ediciones ya finalizó cada curso: ayuda a ver de un vistazo
            // qué talleres se repiten más seguido, respetando el alcance del Ponente.
            'finishedByCourse' => Course::query()
                ->withCount(['editions as finished_editions_count' => fn ($q) => $q
                    ->visibleTo($request->user())
                    ->whereFinished()])
                ->get(['id', 'code', 'name'])
                ->filter(fn (Course $c) => $c->finished_editions_count > 0)
                ->sortByDesc('finished_editions_count')
                ->values()
                ->map(fn (Course $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'finished_count' => $c->finished_editions_count,
                ]),
            'can' => ['create' => $request->user()->can('create', CourseEdition::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', CourseEdition::class);

        return Inertia::render('editions/Form', [
            'edition' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(EditionRequest $request): RedirectResponse
    {
        $edition = CourseEdition::create($request->validated());

        $this->toast('Edición creada.');

        return to_route('editions.show', $edition);
    }

    public function show(Request $request, CourseEdition $edition): Response
    {
        Gate::authorize('view', $edition);

        $edition->load([
            'course:id,code,name',
            'teacher:id,full_name',
            'enrollments' => fn ($q) => $q->orderBy('registration_code'),
            'enrollments.student:id,dni,first_name,last_name',
            'enrollments.activeCertificate:id,enrollment_id,issued_at',
        ]);

        $user = $request->user();

        return Inertia::render('editions/Show', [
            'edition' => [
                'id' => $edition->id,
                'label' => $edition->label(),
                'course' => $edition->course->only(['id', 'code', 'name']),
                'teacher' => $edition->teacher?->only(['id', 'full_name']),
                'start_date' => $edition->start_date?->toDateString(),
                'end_date' => $edition->end_date?->toDateString(),
                'year' => $edition->year,
            ],
            'enrollments' => $edition->enrollments->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'registration_code' => $e->registration_code,
                'grade' => $e->grade,
                'attended' => $e->attended,
                'status' => $e->status->value,
                'role' => $e->role->value,
                'historical' => $e->historical,
                'student' => [
                    'id' => $e->student->id,
                    'dni' => $e->student->dni,
                    'full_name' => "{$e->student->last_name}, {$e->student->first_name}",
                ],
                'certificate' => $e->activeCertificate ? [
                    'id' => $e->activeCertificate->id,
                    'issued_at' => $e->activeCertificate->issued_at?->toDateString(),
                ] : null,
            ])->values(),
            'passingGrade' => (float) config('academic.passing_grade'),
            'can' => [
                'update' => $user->can('update', $edition),
                'delete' => $user->can('delete', $edition),
                'enroll' => $user->can('enroll', $edition),
                'grade' => $user->can('grade', $edition),
                'issueCertificates' => $user->can('certificates.issue'),
                'downloadCertificates' => $user->canAny(['certificates.issue', 'certificates.view']),
            ],
        ]);
    }

    public function edit(CourseEdition $edition): Response
    {
        Gate::authorize('update', $edition);

        return Inertia::render('editions/Form', [
            'edition' => [
                'id' => $edition->id,
                'course_id' => $edition->course_id,
                'teacher_id' => $edition->teacher_id,
                'start_date' => $edition->start_date?->toDateString(),
                'end_date' => $edition->end_date?->toDateString(),
                'year' => $edition->year,
                'locked' => $edition->enrollments()->exists(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(EditionRequest $request, CourseEdition $edition): RedirectResponse
    {
        $edition->update($request->validated());

        $this->toast('Edición actualizada.');

        return to_route('editions.show', $edition);
    }

    public function destroy(CourseEdition $edition): RedirectResponse
    {
        Gate::authorize('delete', $edition);

        if (! $this->deleteProtected($edition, 'No se puede eliminar: la edición tiene matrículas.')) {
            return back();
        }

        $this->toast('Edición eliminada.');

        return to_route('editions.index');
    }

    /**
     * @return array{courses: mixed, teachers: mixed}
     */
    private function options(): array
    {
        return [
            'courses' => Course::query()->orderBy('code')->get(['id', 'code', 'name']),
            'teachers' => Teacher::query()->orderBy('full_name')->get(['id', 'full_name']),
        ];
    }
}
