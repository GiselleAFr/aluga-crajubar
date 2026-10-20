<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Base das notificações que o usuário pode desativar (#49) e que aparecem na caixa de entrada (#50).
 *
 * É o ÚNICO ponto que consulta as preferências: se o tipo estiver desativado, via() devolve
 * lista vazia e o Laravel simplesmente não envia nada.
 */
abstract class PreferenceAwareNotification extends Notification
{
    abstract public static function notificationType(): NotificationType;

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification(static::notificationType())) {
            return [];
        }

        return ['database'];
    }

    /**
     * Grava na coluna "type" um identificador estável (em vez do nome da classe PHP).
     */
    public function databaseType(object $notifiable): string
    {
        return static::notificationType()->value;
    }
}