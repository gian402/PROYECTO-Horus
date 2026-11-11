<?php

namespace App\Http\Requests\Users;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'role' => ['required', 'string', Rule::in(RoleSeeder::ROLES)],
            'teacher_id' => [
                'nullable',
                'integer',
                // El ponente no debe estar vinculado ya a otra cuenta.
                Rule::exists('teachers', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'rol', 'teacher_id' => 'ponente'];
    }
}
