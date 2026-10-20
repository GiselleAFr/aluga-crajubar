<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectLandlordRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejection_reason')) {
            $reason = trim((string) $this->input('rejection_reason'));

            $this->merge(['rejection_reason' => $reason === '' ? null : $reason]);
        }
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.max' => 'A justificativa deve ter no máximo 500 caracteres.',
        ];
    }
}