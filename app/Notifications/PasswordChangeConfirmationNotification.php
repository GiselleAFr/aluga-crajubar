<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) env('MOBILE_CONFIRM_PASSWORD_CHANGE_URL', 'alugacrajubar://confirm-password-change'), '?')
            .'?'.http_build_query(['token' => $this->token]);

        return (new MailMessage)
            ->subject('Confirme a alteração da sua senha')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para alterar a senha da sua conta.')
            ->action('Confirmar alteração de senha', $url)
            ->line('Este link expira em '.config('jwt.password_change_ttl').' minutos e pode ser usado uma única vez.')
            ->line('Se você não solicitou essa alteração, ignore este e-mail.');
    }
}
