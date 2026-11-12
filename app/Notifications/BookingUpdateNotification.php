<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Support\Carbon;

/**
 * Aviso sobre uma reserva ("Atualização de reserva").
 *
 * Eventos: requested (para o locador), confirmed, rejected (para o hóspede)
 * e cancelled (para o locador).
 */
class BookingUpdateNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly int $bookingId,
        private readonly string $event,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public static function notificationType(): NotificationType
    {
        return NotificationType::BOOKING_UPDATE;
    }

    public static function fromBooking(Booking $booking, string $event): self
    {
        $booking->loadMissing('user');

        $imovel = '"'.$booking->property_name.'"';
        $periodo = Carbon::parse($booking->check_in)->format('d/m/Y')
            .' a '.Carbon::parse($booking->check_out)->format('d/m/Y');
        $hospede = $booking->user?->name ?? 'Um hóspede';

        [$title, $message] = match ($event) {
            'requested' => ['Nova solicitação de reserva', "{$hospede} quer reservar {$imovel} ({$periodo})."],
            'confirmed' => ['Reserva confirmada', "O locador confirmou sua reserva em {$imovel} ({$periodo})."],
            'rejected' => ['Solicitação recusada', "O locador recusou sua solicitação para {$imovel} ({$periodo})."],
            'cancelled' => ['Reserva cancelada', "{$hospede} cancelou a reserva em {$imovel} ({$periodo})."],
            default => ['Atualização de reserva', "Há uma novidade sobre {$imovel}."],
        };

        return new self($booking->id, $event, $title, $message);
    }

    /**
     * Conteúdo gravado na tabela notifications (title e message viram os textos do aviso no app).
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'booking_id' => $this->bookingId,
            'event' => $this->event,
        ];
    }
}