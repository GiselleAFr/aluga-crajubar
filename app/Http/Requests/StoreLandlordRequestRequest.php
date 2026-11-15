<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandlordRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['full_name', 'cpf', 'birth_date', 'document_type', 'email'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $normalized[$field] = match ($field) {
                'cpf' => preg_replace('/\D+/', '', $value),
                'document_type' => mb_strtolower($value),
                'email' => $value === '' ? null : mb_strtolower($value),
                default => $value,
            };
        }

        foreach (['primary_phone', 'secondary_phone', 'commercial_phone'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $value);
            if ($digits === '') {
                $normalized[$field] = null;

                continue;
            }

            if (in_array(strlen($digits), [10, 11], true)) {
                $digits = '55'.$digits;
            }

            $normalized[$field] = '+'.$digits;
        }

        if ($this->has('message')) {
            $message = trim((string) $this->input('message'));

            $normalized['message'] = $message === '' ? null : $message;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'regex:/^\d{11}$/'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'document_type' => ['required', 'string', Rule::in(['rg', 'cnh', 'passport'])],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'primary_phone' => ['required', 'string', 'regex:/^\+55\d{10,11}$/'],
            'secondary_phone' => ['nullable', 'string', 'regex:/^\+55\d{10,11}$/'],
            'commercial_phone' => ['nullable', 'string', 'regex:/^\+55\d{10,11}$/'],
            'document_front' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'document_back' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Informe seu nome completo.',
            'full_name.string' => 'Informe um nome válido.',
            'full_name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'cpf.required' => 'Informe seu CPF.',
            'cpf.regex' => 'Informe um CPF com 11 dígitos.',
            'birth_date.required' => 'Informe sua data de nascimento.',
            'birth_date.date_format' => 'Informe a data no formato AAAA-MM-DD.',
            'birth_date.before_or_equal' => 'A data de nascimento não pode ser futura.',
            'document_type.required' => 'Selecione o tipo de documento.',
            'document_type.in' => 'Selecione RG, CNH ou passaporte.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
            'primary_phone.required' => 'Informe seu telefone principal.',
            'primary_phone.regex' => 'Informe um telefone principal válido com DDD.',
            'secondary_phone.regex' => 'Informe um telefone secundário válido com DDD.',
            'commercial_phone.regex' => 'Informe um telefone comercial válido com DDD.',
            'document_front.required' => 'Envie a frente do documento.',
            'document_front.image' => 'A frente do documento deve ser uma imagem.',
            'document_front.mimes' => 'A frente do documento deve estar em JPG ou PNG.',
            'document_front.max' => 'A imagem da frente deve ter no máximo 10 MB.',
            'document_back.required' => 'Envie o verso do documento.',
            'document_back.image' => 'O verso do documento deve ser uma imagem.',
            'document_back.mimes' => 'O verso do documento deve estar em JPG ou PNG.',
            'document_back.max' => 'A imagem do verso deve ter no máximo 10 MB.',
            'message.string' => 'Informe uma mensagem válida.',
            'message.max' => 'A mensagem deve ter no máximo 1000 caracteres.',
        ];
    }
}
