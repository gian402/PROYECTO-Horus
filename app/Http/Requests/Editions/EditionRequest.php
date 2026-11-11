<?php

namespace App\Http\Requests\Editions;

use App\Models\CourseEdition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $edition = $this->route('edition');

        return $edition instanceof CourseEdition
            ? $this->user()->can('update', $edition)
            : $this->user()->can('create', CourseEdition::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Las fechas son obligatorias, salvo al editar una edición histórica que
        // se importó sin fechas: entonces ambas pueden quedar vacías (o ambas completas).
        $edition = $this->route('edition');
        $datesOptional = $edition instanceof CourseEdition && $edition->start_date === null;

        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'start_date' => $datesOptional ? ['nullable', 'required_with:end_date', 'date'] : ['required', 'date'],
            'end_date' => $datesOptional ? ['nullable', 'required_with:start_date', 'date', 'after_or_equal:start_date'] : ['required', 'date', 'after_or_equal:start_date'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * Curso y año forman parte de los códigos de registro ya asignados
     * (HGC-{curso}-NN-{año}): no se cambian si la edición tiene matrículas.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $edition = $this->route('edition');

                if (! $edition instanceof CourseEdition || ! $edition->enrollments()->exists()) {
                    return;
                }

                if ((int) $this->input('course_id') !== $edition->course_id) {
                    $validator->errors()->add('course_id', 'No se puede cambiar el curso: la edición ya tiene matrículas con código asignado.');
                }

                if ((int) $this->input('year') !== $edition->year) {
                    $validator->errors()->add('year', 'No se puede cambiar el año: la edición ya tiene matrículas con código asignado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'course_id' => 'curso',
            'teacher_id' => 'ponente',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'year' => 'año',
        ];
    }
}
