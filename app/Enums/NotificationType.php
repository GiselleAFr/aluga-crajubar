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
    // Opções da tela "Notificações" do app.
    case PUSH = 'push';
    case BOOKING_UPDATE = 'booking_update';
    case TRIP_REMINDER = 'trip_reminder';
    case HOST_MESSAGES = 'host_messages';
    case REVIEWS = 'reviews';
    case PROMOTIONS = 'promotions';
    case PROMOTIONAL_EMAILS = 'promotional_emails';

    // Avisos do fluxo de solicitação de locador.
    case LANDLORD_REQUEST_APPROVED = 'landlord_request_approved';
    case LANDLORD_REQUEST_REJECTED = 'landlord_request_rejected';

    public function label(): string
    {
        return match ($this) {
            self::PUSH => 'Notificações push',
            self::BOOKING_UPDATE => 'Atualização de reserva',
            self::TRIP_REMINDER => 'Lembrete de viagem',
            self::HOST_MESSAGES => 'Mensagens de anfitriões',
            self::REVIEWS => 'Avaliações',
            self::PROMOTIONS => 'Notificações e promoções',
            self::PROMOTIONAL_EMAILS => 'E-mails promocionais',
            self::LANDLORD_REQUEST_APPROVED => 'Solicitação de locador aprovada',
            self::LANDLORD_REQUEST_REJECTED => 'Solicitação de locador recusada',
        };
    }

    /**
     * Padrão para quem nunca mexeu na preferência.
     * Avisos de uso do app começam ativados (opt-out); marketing só com consentimento (opt-in).
     */
    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::PROMOTIONS, self::PROMOTIONAL_EMAILS => false,
            default => true,
        };
    }
}