<?php

namespace App\Http\Requests;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1', 'max:'.count(NotificationType::cases())],
            'preferences.*' => ['array'],
            'preferences.*.type' => ['required', 'string', 'distinct', Rule::enum(NotificationType::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'preferences.required' => 'Informe as preferências de notificação.',
            'preferences.array' => 'As preferências devem ser enviadas em uma lista.',
            'preferences.min' => 'Informe ao menos uma preferência.',
            'preferences.max' => 'Há mais preferências do que tipos de notificação existentes.',
            'preferences.*.array' => 'Cada preferência deve conter o tipo e o valor.',
            'preferences.*.type.required' => 'Informe o tipo da notificação.',
            'preferences.*.type.string' => 'O tipo da notificação é inválido.',
            'preferences.*.type.distinct' => 'O mesmo tipo de notificação foi enviado mais de uma vez.',
            'preferences.*.type.enum' => 'O tipo de notificação informado não existe.',
            'preferences.*.enabled.required' => 'Informe se a notificação deve ficar ativada.',
            'preferences.*.enabled.boolean' => 'O valor de ativação deve ser verdadeiro ou falso.',
        ];
    }
}
