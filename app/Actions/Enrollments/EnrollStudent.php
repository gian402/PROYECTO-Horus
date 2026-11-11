<?php

namespace App\Actions\Enrollments;

use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\RegistrationCodeGenerator;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Matricula a un participante en una edición asignando su código de registro
 * en una única transacción (código + matrícula se confirman o revierten juntos).
 */
class EnrollStudent
{
    public function __construct(private readonly RegistrationCodeGenerator $codes) {}

    public function handle(Student $student, CourseEdition $edition): Enrollment
    {
        return DB::transaction(function () use ($student, $edition): Enrollment {
            $alreadyEnrolled = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_edition_id', $edition->id)
                ->exists();

            if ($alreadyEnrolled) {
                throw new DomainException('El participante ya está matriculado en esta edición.');
            }

            return Enrollment::create([
                'student_id' => $student->id,
                'course_edition_id' => $edition->id,
                'registration_code' => $this->codes->next($edition),
            ]);
        });
    }
}
