<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Logging in is a public endpoint; there is nothing to authorize.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shape only. Whether the credentials are correct is decided by the guard,
     * never by validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
        ];
    }
}
