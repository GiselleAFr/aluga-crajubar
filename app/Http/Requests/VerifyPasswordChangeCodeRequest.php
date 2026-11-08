<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordChangeCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'digits:6']];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Informe o código de verificação.',
            'code.digits' => 'O código deve ter 6 dígitos.',
        ];
    }
}