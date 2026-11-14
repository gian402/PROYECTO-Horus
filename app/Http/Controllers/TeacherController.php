<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Teachers\TeacherRequest;
use App\Models\Teacher;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ponentes. El vínculo con una cuenta de usuario se gestiona en el módulo Usuarios.
 */
class TeacherController extends Controller
{
    use InteractsWithToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Teacher::class);

        $search = trim((string) $request->query('search', ''));

        $teachers = Teacher::query()
            ->with('user:id,email')
            ->withCount('editions')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('dni', Sql::likeOperator(), "{$search}%")
                ->orWhere('full_name', Sql::likeOperator(), "%{$search}%")
                ->orWhere('specialty', Sql::likeOperator(), "%{$search}%")))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Teacher $t) => [
                'id' => $t->id,
                'dni' => $t->dni,
                'full_name' => $t->full_name,
                'specialty' => $t->specialty,
                'editions_count' => $t->editions_count,
                'user_email' => $t->user?->email,
            ]);

        return Inertia::render('teachers/Index', [
            'teachers' => $teachers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Teacher::class);

        return Inertia::render('teachers/Form', ['teacher' => null]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $teacher = Teacher::create($request->validated());

        $this->toast("Ponente {$teacher->full_name} registrado.");

        return to_route('teachers.index');
    }

    public function edit(Teacher $teacher): Response
    {
        Gate::authorize('update', $teacher);

        return Inertia::render('teachers/Form', [
            'teacher' => $teacher->only(['id', 'dni', 'full_name', 'specialty']),
        ]);
    }

    public function update(TeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($request->validated());

        $this->toast('Ponente actualizado.');

        return to_route('teachers.index');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        Gate::authorize('delete', $teacher);

        if ($this->deleteProtected($teacher, 'No se puede eliminar: el ponente está asignado a ediciones.')) {
            $this->toast('Ponente eliminado.');
        }

        return to_route('teachers.index');
    }
}
