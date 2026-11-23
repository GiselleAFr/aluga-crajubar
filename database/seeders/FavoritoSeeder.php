<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Favoritos fixos (posição do imóvel na lista do ImovelSeeder, começando em 1). */
class FavoritoSeeder extends Seeder
{
    private const FAVORITOS = [
        'cliente@alugacrajubar.test' => [1, 5, 8],
        'cliente1@alugacrajubar.test' => [2, 6],
        'cliente2@alugacrajubar.test' => [3, 8, 10],
    ];

    public function run(): void
    {
        $imoveis = Imovel::orderBy('id')->pluck('id')->values();

        foreach (self::FAVORITOS as $email => $posicoes) {
            $cliente = User::where('email', $email)->first();

            if (! $cliente) {
                continue;
            }

            $ids = collect($posicoes)->map(fn ($p) => $imoveis[$p - 1] ?? null)->filter()->all();
            $cliente->favoritos()->syncWithoutDetaching($ids);
        }
    }
}