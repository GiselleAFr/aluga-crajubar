<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Dados de demonstração. NUNCA rodar em produção. */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ImovelSeeder::class,
            FavoritoSeeder::class,
        ]);
    }
}