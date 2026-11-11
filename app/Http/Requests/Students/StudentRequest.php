<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación compartida de alta y edición de participantes.
 */
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            ? $this->user()->can('update', $student)
            : $this->user()->can('create', Student::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dni' => mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('dni'))),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'regex:/^[0-9A-Z]{6,15}$/', Rule::unique('students', 'dni')->ignore($this->route('student'))],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\s-]{6,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dni' => 'DNI',
            'first_name' => 'nombres',
            'last_name' => 'apellidos',
            'email' => 'correo',
            'phone' => 'teléfono',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe tener entre 6 y 15 letras o números, sin espacios ni guiones.',
            'phone.regex' => 'El teléfono solo puede contener números, espacios, + y -.',
        ];
    }
}
