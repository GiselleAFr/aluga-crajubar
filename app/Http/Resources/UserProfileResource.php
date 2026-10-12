<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'is_landlord' => $this->isLandlord(),
            'available_tabs' => $this->isLandlord() ? ['cliente', 'locador'] : ['cliente'],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
