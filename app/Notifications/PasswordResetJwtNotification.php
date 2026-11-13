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
        $params = http_build_query(['token' => $this->token, 'email' => $notifiable->email]);
        $webUrl = (string) config('jwt.reset_web_url');

        // Com a página web configurada, o e-mail traz um link https (clicável em qualquer cliente
        // de e-mail) que abre o app. O token vai no fragmento (#), que não é enviado ao servidor.
        $url = $webUrl !== ''
            ? rtrim($webUrl, '#?').'#'.$params
            : rtrim((string) config('jwt.reset_deep_link'), '?').'?'.$params;

        return (new MailMessage)
            ->subject('Recuperação de senha')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir sua senha.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em '.config('jwt.ttl').' minutos e pode ser usado uma única vez.')
            ->line('Se não foi você, ignore este e-mail.');
    }
}
