<?php

namespace App\Http\Requests\Courses;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            ? $this->user()->can('update', $course)
            : $this->user()->can('create', Course::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/', Rule::unique('courses', 'code')->ignore($this->route('course'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * El código forma parte de los códigos de registro ya emitidos (HGC-{code}-NN-AAA):
     * no puede cambiarse cuando el curso tiene ediciones.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $course = $this->route('course');

                if ($course instanceof Course
                    && $this->input('code') !== $course->code
                    && $course->editions()->exists()) {
                    $validator->errors()->add('code', 'No se puede cambiar el código: el curso ya tiene ediciones y códigos de registro emitidos.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'código', 'name' => 'nombre', 'description' => 'descripción'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'El código solo admite letras, números y guiones internos (ej. NUT-DIET), hasta 10 caracteres.'];
    }
}
