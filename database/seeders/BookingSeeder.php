<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::exists()) {
            return;
        }

        $clientes = User::where('role', 'client')->get();
        $imoveis = Imovel::where('finalidade', 'aluguel')->get();

        if ($clientes->isEmpty() || $imoveis->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 10; $i++) {
            $imovel = $imoveis->random();

            Booking::factory()->create([
                'user_id' => $clientes->random()->id,
                'property_name' => $imovel->titulo,
                'city' => $imovel->cidade,
            ]);
        }
    }
}
