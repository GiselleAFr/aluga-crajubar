<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('email')) {
            $data['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        if ($this->has('phone')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

            if (in_array(strlen($digits), [10, 11], true)) {
                $digits = '55'.$digits;
            }

            $data['phone'] = $digits === '' ? null : '+'.$digits;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'phone' => ['sometimes', 'required', 'regex:/^\+[1-9]\d{9,14}$/', Rule::unique('users', 'phone')->ignore($this->user())],
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
        ];
    }
}
