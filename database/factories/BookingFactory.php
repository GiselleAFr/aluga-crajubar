<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-12 months', '-2 days');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'user_id' => User::factory(),
            'property_name' => 'Hospedagem '.fake()->streetName(),
            'city' => fake()->city(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => fake()->numberBetween(1, 6),
            'total_amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'BRL',
            'status' => 'completed',
        ];
    }
}
