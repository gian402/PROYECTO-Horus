<?php

namespace App\Http\Requests\Teachers;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $teacher = $this->route('teacher');

        return $teacher instanceof Teacher
            ? $this->user()->can('update', $teacher)
            : $this->user()->can('create', Teacher::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dni' => mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('dni'))),
            'full_name' => trim((string) $this->input('full_name')),
            'specialty' => $this->filled('specialty') ? trim((string) $this->input('specialty')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'regex:/^[0-9A-Z]{6,15}$/', Rule::unique('teachers', 'dni')->ignore($this->route('teacher'))],
            'full_name' => ['required', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dni' => 'DNI', 'full_name' => 'nombre completo', 'specialty' => 'especialidad'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['dni.regex' => 'El DNI debe tener entre 6 y 15 letras o números, sin espacios ni guiones.'];
    }
}
