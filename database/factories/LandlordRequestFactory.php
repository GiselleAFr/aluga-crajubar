<?php

namespace Database\Factories;

use App\Enums\LandlordRequestStatus;
use App\Models\LandlordRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LandlordRequest> */
class LandlordRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => LandlordRequestStatus::PENDING,
            'message' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LandlordRequestStatus::APPROVED,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LandlordRequestStatus::REJECTED,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Dados insuficientes.',
        ]);
    }
}