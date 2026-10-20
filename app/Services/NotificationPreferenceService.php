<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * Todos os tipos, com o valor gravado pelo usuário ou o padrão do tipo.
     *
     * @return list<array{type: string, label: string, enabled: bool}>
     */
    public function all(User $user): array
    {
        $stored = $user->notificationPreferences()->pluck('enabled', 'type');

        return array_map(
            fn (NotificationType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'enabled' => (bool) ($stored[$type->value] ?? $type->defaultEnabled()),
            ],
            NotificationType::cases(),
        );
    }

    /**
     * Grava as preferências do PRÓPRIO usuário (user_id vem sempre do usuário autenticado).
     * Os tipos já chegam validados e sem repetição (ver UpdateNotificationPreferencesRequest).
     *
     * @param  list<array{type: string, enabled: mixed}>  $preferences
     * @return list<array{type: string, label: string, enabled: bool}>
     */
    public function update(User $user, array $preferences): array
    {
        $now = now();

        $rows = array_map(fn (array $preference): array => [
            'user_id' => $user->id,
            'type' => $preference['type'],
            'enabled' => filter_var($preference['enabled'], FILTER_VALIDATE_BOOLEAN),
            'created_at' => $now,
            'updated_at' => $now,
        ], $preferences);

        // Uma única instrução atômica (INSERT ... ON CONFLICT DO UPDATE), segura contra corridas.
        NotificationPreference::upsert($rows, ['user_id', 'type'], ['enabled', 'updated_at']);

        return $this->all($user);
    }
}