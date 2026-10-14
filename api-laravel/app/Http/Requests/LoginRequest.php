<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Checks the login form: email and password must be present and not too long. */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
