<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Students\StudentRequest;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    use InteractsWithToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        $search = trim((string) $request->query('search', ''));

        $students = Student::query()
            ->withCount('enrollments')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('dni', Sql::likeOperator(), "{$search}%")
                ->orWhere('first_name', Sql::likeOperator(), "%{$search}%")
                ->orWhere('last_name', Sql::likeOperator(), "%{$search}%")
                ->orWhereRaw(Sql::concatWithSpace('first_name', 'last_name').' '.Sql::likeOperator().' ?', ["%{$search}%"])))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Student $s) => [
                'id' => $s->id,
                'dni' => $s->dni,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'email' => $s->email,
                'phone' => $s->phone,
                'enrollments_count' => $s->enrollments_count,
            ]);

        return Inertia::render('students/Index', [
            'students' => $students,
            'filters' => ['search' => $search],
            'can' => ['create' => $request->user()->can('create', Student::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('students/Create');
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = Student::create($request->validated());

        $this->toast("Participante {$student->full_name} registrado.");

        return to_route('students.show', $student);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load([
            'enrollments' => fn ($q) => $q->latest('id'),
            'enrollments.courseEdition.course:id,code,name',
            'enrollments.activeCertificate:id,enrollment_id,issued_at',
        ]);

        return Inertia::render('students/Show', [
            'student' => [
                'id' => $student->id,
                'dni' => $student->dni,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'phone' => $student->phone,
            ],
            'enrollments' => $student->enrollments->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'registration_code' => $e->registration_code,
                'grade' => $e->grade,
                'status' => $e->status->value,
                'role' => $e->role->value,
                'edition' => [
                    'id' => $e->courseEdition->id,
                    'label' => $e->courseEdition->label(),
                    'year' => $e->courseEdition->year,
                    'course' => $e->courseEdition->course->only(['id', 'code', 'name']),
                ],
                'certificate' => $e->activeCertificate ? [
                    'id' => $e->activeCertificate->id,
                    'issued_at' => $e->activeCertificate->issued_at?->toDateString(),
                ] : null,
            ])->values(),
            'can' => [
                'update' => $request->user()->can('update', $student),
                'delete' => $request->user()->can('delete', $student),
            ],
        ]);
    }

    public function edit(Student $student): Response
    {
        Gate::authorize('update', $student);

        return Inertia::render('students/Edit', [
            'student' => $student->only(['id', 'dni', 'first_name', 'last_name', 'email', 'phone']),
        ]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        $this->toast('Datos del participante actualizados.');

        return to_route('students.show', $student);
    }

    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        if (! $this->deleteProtected($student, 'No se puede eliminar: el participante tiene matrículas registradas (historial académico).')) {
            return back();
        }

        $this->toast('Participante eliminado.');

        return to_route('students.index');
    }
}
