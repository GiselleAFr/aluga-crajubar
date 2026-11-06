<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Reparo dos imóveis de teste:
 *  - vincula cada imóvel a um locador (com telefone);
 *  - preenche latitude/longitude quando estiverem vazias.
 *
 * Pode ser executado várias vezes. Só mexe em imóveis sem dono ou que
 * pertencem às contas de teste, nunca em imóveis de locadores reais.
 *
 *   php artisan db:seed --class=ImovelRepairSeeder
 */
class ImovelRepairSeeder extends Seeder
{
    /** Centro aproximado de cada cidade: [latitude, longitude]. */
    private const CENTROS = [
        'crato' => [-7.2342, -39.4094],
        'juazeiro do norte' => [-7.2130, -39.3153],
        'barbalha' => [-7.3019, -39.3028],
    ];

    /** Variação em graus em torno do centro (aprox. 1,3 km). */
    private const RAIO = 0.012;

    public function run(): void
    {
        // Garante que os locadores de teste existem e têm telefone.
        $this->call(UserSeeder::class);

        $locadorTeste = User::where('email', 'proprietario@alugacrajubar.test')->first();

        if (! $locadorTeste) {
            $this->command?->warn('Locador de teste não encontrado. Nada foi alterado.');

            return;
        }

        $outros = User::whereIn('email', [
            'proprietario1@alugacrajubar.test',
            'proprietario2@alugacrajubar.test',
            'proprietario3@alugacrajubar.test',
            'proprietario4@alugacrajubar.test',
        ])->orderBy('id')->get()->values();

        $idsDeTeste = $outros->pluck('id')->push($locadorTeste->id)->all();

        $imoveis = Imovel::query()
            ->where(function ($query) use ($idsDeTeste) {
                $query->whereNull('user_id')->orWhereIn('user_id', $idsDeTeste);
            })
            ->orderBy('id')
            ->get();

        $vinculados = 0;
        $comCoordenadas = 0;

        foreach ($imoveis as $posicao => $imovel) {
            // Posições pares ficam com o locador de teste (seu número);
            // as ímpares se dividem entre os outros quatro.
            $dono = ($posicao % 2 === 0 || $outros->isEmpty())
                ? $locadorTeste
                : $outros[intdiv($posicao, 2) % $outros->count()];

            if ($imovel->user_id !== $dono->id) {
                $imovel->user_id = $dono->id;
                $vinculados++;
            }

            if ($imovel->latitude === null || $imovel->longitude === null) {
                $coordenadas = $this->coordenadas($imovel);

                if ($coordenadas !== null) {
                    [$imovel->latitude, $imovel->longitude] = $coordenadas;
                    $comCoordenadas++;
                }
            }

            $imovel->save(); // só grava se algo mudou
        }

        $this->command?->info(
            "Imóveis analisados: {$imoveis->count()} | vinculados a locadores: {$vinculados} | coordenadas preenchidas: {$comCoordenadas}"
        );
    }

    /**
     * Coordenada aproximada dentro da cidade do imóvel.
     * O deslocamento é calculado a partir do id, então é sempre o mesmo.
     */
    private function coordenadas(Imovel $imovel): ?array
    {
        $centro = self::CENTROS[mb_strtolower(trim((string) $imovel->cidade))] ?? null;

        if ($centro === null) {
            return null;
        }

        $a = (crc32('lat'.$imovel->id) % 2001) / 1000 - 1; // de -1 a 1
        $b = (crc32('lng'.$imovel->id) % 2001) / 1000 - 1;

        return [
            round($centro[0] + $a * self::RAIO, 7),
            round($centro[1] + $b * self::RAIO, 7),
        ];
    }
}