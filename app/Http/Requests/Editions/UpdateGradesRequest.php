<?php

namespace App\Http\Requests\Editions;

use App\Enums\EnrollmentRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registro masivo de notas y asistencia de una edición:
 * grades[enrollment_id] = nota | null, attendance[enrollment_id] = asistió | null.
 * El estado académico NO se recibe: lo deriva el backend a partir de la nota.
 */
class UpdateGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('grade', $this->route('edition'));
    }

    protected function prepareForValidation(): void
    {
        $grades = collect((array) $this->input('grades', []))
            ->map(fn ($g) => is_string($g) ? (trim($g) === '' ? null : str_replace(',', '.', trim($g))) : $g)
            ->all();

        $attendance = collect((array) $this->input('attendance', []))
            ->map(fn ($a) => $a === '' || $a === null ? null : filter_var($a, FILTER_VALIDATE_BOOLEAN))
            ->all();

        $this->merge(['grades' => $grades, 'attendance' => $attendance]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,20'],
            'attendance' => ['sometimes', 'array'],
            'attendance.*' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $gradeIds = array_map('intval', array_keys((array) $this->input('grades', [])));
                $attendanceIds = array_map('intval', array_keys((array) $this->input('attendance', [])));
                $ids = array_unique([...$gradeIds, ...$attendanceIds]);

                $valid = $this->route('edition')->enrollments()->whereIn('id', $ids)->count();

                if ($valid !== count($ids)) {
                    $validator->errors()->add('grades', 'Hay matrículas que no pertenecen a esta edición.');
                }

                // Los ponentes no se califican.
                if ($this->route('edition')->enrollments()->whereIn('id', $gradeIds)->where('role', EnrollmentRole::Ponente)->exists()) {
                    $validator->errors()->add('grades', 'Los ponentes no se califican.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['grades' => 'notas', 'grades.*' => 'nota', 'attendance' => 'asistencia', 'attendance.*' => 'asistencia'];
    }
}
