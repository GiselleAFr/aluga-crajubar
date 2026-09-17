<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetJwtNotification extends Notification
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
        $url = rtrim((string) env('MOBILE_RESET_PASSWORD_URL', 'alugacrajubar://reset-password'), '?')
            .'?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Recuperação de senha')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir sua senha.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em '.config('jwt.ttl').' minutos e pode ser usado uma única vez.')
            ->line('Se não foi você, ignore este e-mail.');
    }
}
