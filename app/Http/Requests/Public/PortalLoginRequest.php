<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Identificación del participante en el portal público: DNI + un código de
 * registro suyo (sin contraseña). El formato se valida aquí; la existencia
 * y correspondencia real se verifica en el controlador.
 */
class PortalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dni' => mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('dni'))),
            'registration_code' => mb_strtoupper(trim((string) $this->input('registration_code'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'regex:/^[0-9A-Z]{6,15}$/'],
            'registration_code' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dni' => 'DNI',
            'registration_code' => 'código de registro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe tener entre 6 y 15 letras o números, sin espacios ni guiones.',
        ];
    }
}
