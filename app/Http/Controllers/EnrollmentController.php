<?php

namespace App\Http\Controllers;

use App\Actions\Enrollments\EnrollStudent;
use App\Enums\EnrollmentRole;
use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    use InteractsWithToasts;

    /**
     * Búsqueda de participantes aún no matriculados en la edición (autocompletar).
     */
    public function searchStudents(Request $request, CourseEdition $edition): JsonResponse
    {
        Gate::authorize('enroll', $edition);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $students = Student::query()
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('course_edition_id', $edition->id))
            ->where(fn ($q) => $q
                ->where('dni', 'ilike', "{$term}%")
                ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$term}%"])
                ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$term}%"]))
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'dni', 'first_name', 'last_name'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'dni' => $s->dni,
                'full_name' => "{$s->last_name}, {$s->first_name}",
            ]);

        return response()->json($students);
    }

    public function store(Request $request, CourseEdition $edition, EnrollStudent $enroll): RedirectResponse
    {
        Gate::authorize('enroll', $edition);

        $data = $request->validate(
            ['student_id' => ['required', 'integer', 'exists:students,id'], 'as_speaker' => ['boolean']],
            [],
            ['student_id' => 'participante', 'as_speaker' => 'ponente'],
        );

        $role = ($data['as_speaker'] ?? false) ? EnrollmentRole::Ponente : EnrollmentRole::Participante;

        try {
            $enrollment = $enroll->handle(Student::findOrFail($data['student_id']), $edition, $role);
        } catch (DomainException $e) {
            return back()->withErrors(['student_id' => $e->getMessage()]);
        }

        $this->toast("Matrícula registrada con el código {$enrollment->registration_code}.");

        return back();
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('delete', $enrollment);

        if ($this->deleteProtected($enrollment, 'No se puede anular: la matrícula tiene certificados emitidos.')) {
            $this->toast("Matrícula {$enrollment->registration_code} anulada.");
        }

        return back();
    }
}
