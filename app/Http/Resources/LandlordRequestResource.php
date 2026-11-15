<?php

namespace App\Http\Resources;

use App\Enums\LandlordRequestStatus;
use App\Models\LandlordRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LandlordRequest */
class LandlordRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'full_name' => $this->full_name,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->toDateString(),
            'document_type' => $this->document_type,
            'email' => $this->email,
            'primary_phone' => $this->primary_phone,
            'secondary_phone' => $this->secondary_phone,
            'commercial_phone' => $this->commercial_phone,
            'message' => $this->message,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'documents' => $this->when(
                $request->user()?->isAdmin() && $this->status === LandlordRequestStatus::PENDING,
                fn () => [
                    'front' => $this->document_front_path === null
                        ? null
                        : route('admin.landlord-requests.documents.show', [
                            'landlordRequest' => $this->id,
                            'side' => 'front',
                        ]),
                    'back' => $this->document_back_path === null
                        ? null
                        : route('admin.landlord-requests.documents.show', [
                            'landlordRequest' => $this->id,
                            'side' => 'back',
                        ]),
                ],
            ),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
        ];
    }
}
