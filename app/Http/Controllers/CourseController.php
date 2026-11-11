<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Courses\CourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    use InteractsWithToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Course::class);

        $search = trim((string) $request->query('search', ''));

        $courses = Course::query()
            ->withCount('editions')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'ilike', "{$search}%")
                ->orWhere('name', 'ilike', "%{$search}%")))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Course $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'description' => $c->description,
                'editions_count' => $c->editions_count,
            ]);

        return Inertia::render('courses/Index', [
            'courses' => $courses,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Course::class);

        return Inertia::render('courses/Form', ['course' => null]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $course = Course::create($request->validated());

        $this->toast("Curso {$course->code} creado.");

        return to_route('courses.index');
    }

    public function edit(Course $course): Response
    {
        Gate::authorize('update', $course);

        return Inertia::render('courses/Form', [
            'course' => [
                ...$course->only(['id', 'code', 'name', 'description']),
                'code_locked' => $course->editions()->exists(),
            ],
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        $this->toast("Curso {$course->code} actualizado.");

        return to_route('courses.index');
    }

    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('delete', $course);

        if ($this->deleteProtected($course, 'No se puede eliminar: el curso tiene ediciones registradas.')) {
            $this->toast("Curso {$course->code} eliminado.");
        }

        return to_route('courses.index');
    }
}
