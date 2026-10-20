<?php

namespace App\Notifications;

use App\Enums\NotificationType;

class LandlordRequestRejectedNotification extends PreferenceAwareNotification
{
    public function __construct(
        public readonly int $landlordRequestId,
        public readonly ?string $reason = null,
    ) {
    }

    public static function notificationType(): NotificationType
    {
        return NotificationType::LANDLORD_REQUEST_REJECTED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Solicitação recusada',
            'message' => 'Sua solicitação para se tornar locador foi recusada.',
            'landlord_request_id' => $this->landlordRequestId,
            'rejection_reason' => $this->reason,
        ];
    }
}