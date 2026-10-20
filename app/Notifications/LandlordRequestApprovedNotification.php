<?php

namespace App\Notifications;

use App\Enums\NotificationType;

class LandlordRequestApprovedNotification extends PreferenceAwareNotification
{
    public function __construct(public readonly int $landlordRequestId)
    {
    }

    public static function notificationType(): NotificationType
    {
        return NotificationType::LANDLORD_REQUEST_APPROVED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Solicitação aprovada',
            'message' => 'Sua solicitação para se tornar locador foi aprovada. Agora você pode acessar a aba Locador.',
            'landlord_request_id' => $this->landlordRequestId,
        ];
    }
}