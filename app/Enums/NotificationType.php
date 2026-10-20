<?php

namespace App\Enums;

/**
 * Tipos de notificação que o usuário pode ativar/desativar.
 *
 * Notificações de segurança de conta (recuperar/confirmar troca de senha) NÃO entram aqui
 * de propósito: elas continuam sempre sendo enviadas.
 */
enum NotificationType: string
{
    case LANDLORD_REQUEST_APPROVED = 'landlord_request_approved';
    case LANDLORD_REQUEST_REJECTED = 'landlord_request_rejected';

    public function label(): string
    {
        return match ($this) {
            self::LANDLORD_REQUEST_APPROVED => 'Solicitação de locador aprovada',
            self::LANDLORD_REQUEST_REJECTED => 'Solicitação de locador recusada',
        };
    }

    /**
     * Padrão para quem nunca mexeu na preferência (opt-out: tudo ativado).
     */
    public function defaultEnabled(): bool
    {
        return true;
    }
}