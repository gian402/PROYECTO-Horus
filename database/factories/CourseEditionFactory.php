<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEdition>
 */
class CourseEditionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('2024-01-01', '2024-11-30');

        return [
            'course_id' => Course::factory(),
            'teacher_id' => Teacher::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+'.fake()->numberBetween(0, 30).' days')->format('Y-m-d'),
            'year' => (int) $start->format('Y'),
        ];
    }

    public function withoutTeacher(): static
    {
        return $this->state(fn () => ['teacher_id' => null]);
    }
}
