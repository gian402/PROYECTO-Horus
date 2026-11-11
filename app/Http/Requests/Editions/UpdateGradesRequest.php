<?php

namespace App\Http\Requests\Editions;

use App\Enums\EnrollmentRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registro masivo de notas de una edición: grades[enrollment_id] = nota | null.
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

        $this->merge(['grades' => $grades]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,20'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ids = array_map('intval', array_keys((array) $this->input('grades', [])));
                $valid = $this->route('edition')->enrollments()->whereIn('id', $ids)->count();

                if ($valid !== count(array_unique($ids))) {
                    $validator->errors()->add('grades', 'Hay matrículas que no pertenecen a esta edición.');
                }

                // Los ponentes no se califican.
                if ($this->route('edition')->enrollments()->whereIn('id', $ids)->where('role', EnrollmentRole::Ponente)->exists()) {
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
        return ['grades' => 'notas', 'grades.*' => 'nota'];
    }
}
