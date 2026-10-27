<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoritoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('role', 'client')->get();
        $imoveis = Imovel::all();

        if ($clientes->isEmpty() || $imoveis->isEmpty()) {
            return;
        }

        foreach ($clientes as $cliente) {
            $disponiveis = $imoveis
                ->where('user_id', '!=', $cliente->id);

            if ($disponiveis->isEmpty()) {
                continue;
            }

            $ids = $disponiveis
                ->random(min(3, $disponiveis->count()))
                ->pluck('id')
                ->all();

            $cliente->favoritos()->syncWithoutDetaching($ids);
        }
    }
}
