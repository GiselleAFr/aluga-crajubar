<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandlordRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message')) {
            $message = trim((string) $this->input('message'));

            $this->merge(['message' => $message === '' ? null : $message]);
        }
    }

    public function rules(): array
    {
        // Os campos exigidos pela issue #53 ainda não foram definidos.
        // Novos campos devem ser acrescentados aqui, na migration e no $fillable do model.
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.string' => 'Informe uma mensagem válida.',
            'message.max' => 'A mensagem deve ter no máximo 1000 caracteres.',
        ];
    }
}