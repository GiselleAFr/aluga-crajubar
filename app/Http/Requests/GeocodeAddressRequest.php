<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['address' => trim((string) $this->input('address'))]);
    }

    public function rules(): array
    {
        return ['address' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Informe o endereço do imóvel.',
            'address.min' => 'Informe um endereço mais completo.',
        ];
    }
}
