<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

        if (in_array(strlen($digits), [10, 11], true)) {
            $digits = '55'.$digits;
        }

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => $digits === '' ? null : '+'.$digits,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{9,14}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'phone.required' => 'Informe seu telefone.',
            'phone.regex' => 'Informe um telefone válido.',
            'phone.unique' => 'Este telefone já está cadastrado.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }
}
