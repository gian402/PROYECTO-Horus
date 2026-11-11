<?php

namespace Database\Factories;

use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El código de registro de la factory es ficticio y no consume el correlativo.
 * Para el flujo real usar App\Actions\Enrollments\EnrollStudent.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_edition_id' => CourseEdition::factory(),
            'registration_code' => 'HGC-TEST-'.fake()->unique()->numerify('######').'-024',
            'grade' => null,
        ];
    }

    public function approved(float $grade = 16): static
    {
        return $this->state(fn () => ['grade' => $grade]);
    }

    public function failed(float $grade = 10): static
    {
        return $this->state(fn () => ['grade' => $grade]);
    }
}
