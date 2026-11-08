<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly ?string $code = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) env('MOBILE_CONFIRM_PASSWORD_CHANGE_URL', 'alugacrajubar://confirm-password-change'), '?')
            .'?'.http_build_query(['token' => $this->token]);

        $mail = (new MailMessage)
            ->subject('Confirme a alteração da sua senha')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para alterar a senha da sua conta.');

        if ($this->code !== null) {
            $mail->line('Seu código de verificação é: **'.$this->code.'**')
                ->line('Digite esse código no aplicativo ou, se preferir, use o botão abaixo.');
        }

        return $mail
            ->action('Confirmar alteração de senha', $url)
            ->line('O código e o link expiram em '.config('jwt.password_change_ttl').' minutos e podem ser usados uma única vez.')
            ->line('Se você não solicitou essa alteração, ignore este e-mail.');
    }
}