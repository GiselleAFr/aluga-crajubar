<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;

class ImovelSeeder extends Seeder
{
    public function run(): void
    {
        // Não cria anúncios duplicados ao executar novamente.
        if (Imovel::exists()) {
            return;
        }

        $proprietarios = User::where('role', 'landlord')->get();

        if ($proprietarios->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            Imovel::factory()->create([
                'user_id' => $proprietarios->random()->id,
            ]);
        }
    }
}
