<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as Arquivos;
use Illuminate\Support\Facades\Storage;

/**
 * 10 imóveis fixos de demonstração, cada um com foto.
 * Fotos em database/seeders/fotos: casa01.jpg ... casa10.png
 * (fotos extras: casa08-2.jpg, casa08-3.jpg ...). A primeira é a capa.
 */
class ImovelSeeder extends Seeder
{
    private const DONOS = [
        'proprietario@alugacrajubar.test',
        'proprietario1@alugacrajubar.test',
        'proprietario2@alugacrajubar.test',
    ];

    public function run(): void
    {
        $donos = User::whereIn('email', self::DONOS)->get()->keyBy('email');

        if ($donos->count() !== count(self::DONOS)) {
            $this->command?->warn('Proprietários de demonstração não encontrados. Rode o UserSeeder antes.');

            return;
        }

        $disco = Storage::disk('public');
        $disco->deleteDirectory('imoveis'); // limpa fotos de seeds anteriores

        $pastaFotos = database_path('seeders/fotos');
        $arquivos = Arquivos::isDirectory($pastaFotos) ? collect(Arquivos::files($pastaFotos)) : collect();

        foreach ($this->imoveis() as $i => $dados) {
            $numero = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            $imovel = new Imovel($dados);
            $imovel->user_id = $donos[self::DONOS[$i % count(self::DONOS)]]->id;
            $imovel->status = 'available';
            $imovel->save();

            $fotos = $arquivos
                ->filter(fn ($f) => preg_match("/^casa{$numero}(-\d+)?\.(jpe?g|png)$/i", $f->getFilename()))
                ->sortBy(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
                ->values();

            foreach ($fotos as $posicao => $foto) {
                $path = $disco->putFileAs(
                    "imoveis/{$imovel->id}",
                    new File($foto->getPathname()),
                    strtolower($foto->getFilename())
                );

                $imovel->fotos()->create([
                    'disk' => 'public',
                    'path' => $path,
                    'position' => $posicao,
                    'is_cover' => $posicao === 0,
                ]);
            }

            if ($fotos->isEmpty()) {
                $this->command?->warn("Sem foto para casa{$numero} ({$imovel->titulo}).");
            }
        }

        $this->command?->info('Imóveis de demonstração criados: '.count($this->imoveis()));
    }

    private function imoveis(): array
    {
        $base = ['tipo' => 'casa', 'finalidade' => 'aluguel', 'periodo_locacao' => 'diaria'];

        return array_map(fn ($i) => $base + $i, [
            [ // casa01
                'titulo' => 'Casa aconchegante perto do Centro de Juazeiro',
                'descricao' => 'Casa ampla e ventilada, a poucos minutos da Praça Padre Cícero e do comércio central. Ideal para famílias e romeiros.',
                'preco' => 220.00, 'cidade' => 'Juazeiro do Norte', 'bairro' => 'Centro',
                'endereco' => 'Rua São Pedro, 450', 'quartos' => 3, 'banheiros' => 2, 'area' => 140.00,
                'latitude' => -7.2131, 'longitude' => -39.3151,
            ],
            [ // casa02
                'titulo' => 'Casa com piscina na Lagoa Seca',
                'descricao' => 'Casa com área gourmet e piscina, em bairro tranquilo e próximo aos principais restaurantes da cidade.',
                'preco' => 450.00, 'cidade' => 'Juazeiro do Norte', 'bairro' => 'Lagoa Seca',
                'endereco' => 'Rua Tenente Raimundo Rocha, 1200', 'quartos' => 4, 'banheiros' => 3, 'area' => 260.00,
                'latitude' => -7.2290, 'longitude' => -39.3205,
            ],
            [ // casa03
                'titulo' => 'Casa próxima ao Horto do Padre Cícero',
                'descricao' => 'Ótima opção para quem visita o Horto e a estátua do Padre Cícero. Garagem para dois carros.',
                'preco' => 180.00, 'cidade' => 'Juazeiro do Norte', 'bairro' => 'Horto',
                'endereco' => 'Rua do Horto, 85', 'quartos' => 2, 'banheiros' => 1, 'area' => 95.00,
                'latitude' => -7.1985, 'longitude' => -39.3290,
            ],
            [ // casa04
                'titulo' => 'Casa moderna no Triângulo',
                'descricao' => 'Casa nova, mobiliada e com ar-condicionado em todos os quartos. Perto do Cariri Shopping.',
                'preco' => 320.00, 'cidade' => 'Juazeiro do Norte', 'bairro' => 'Triângulo',
                'endereco' => 'Avenida Padre Cícero, 3100', 'quartos' => 3, 'banheiros' => 3, 'area' => 180.00,
                'latitude' => -7.2380, 'longitude' => -39.3450,
            ],
            [ // casa05
                'titulo' => 'Casa colonial no Centro do Crato',
                'descricao' => 'Casa de estilo colonial, perto da Praça da Sé e da Estação. Ambiente charmoso e bem localizado.',
                'preco' => 200.00, 'cidade' => 'Crato', 'bairro' => 'Centro',
                'endereco' => 'Rua Santos Dumont, 210', 'quartos' => 3, 'banheiros' => 2, 'area' => 150.00,
                'latitude' => -7.2340, 'longitude' => -39.4095,
            ],
            [ // casa06
                'titulo' => 'Casa de campo no pé da Chapada do Araripe',
                'descricao' => 'Casa cercada de verde, com clima ameno e vista para a Chapada. Perfeita para descansar no fim de semana.',
                'preco' => 380.00, 'cidade' => 'Crato', 'bairro' => 'Lameiro',
                'endereco' => 'Estrada do Lameiro, km 3', 'quartos' => 4, 'banheiros' => 2, 'area' => 300.00,
                'latitude' => -7.2620, 'longitude' => -39.4280,
            ],
            [ // casa07
                'titulo' => 'Casa familiar no Pimenta',
                'descricao' => 'Casa espaçosa com quintal e rede na varanda, em bairro residencial e perto da URCA.',
                'preco' => 160.00, 'cidade' => 'Crato', 'bairro' => 'Pimenta',
                'endereco' => 'Rua Coronel Antônio Luiz, 640', 'quartos' => 3, 'banheiros' => 2, 'area' => 130.00,
                'latitude' => -7.2410, 'longitude' => -39.4150,
            ],
            [ // casa08 (com mais de uma foto)
                'titulo' => 'Casarão no Centro de Barbalha',
                'descricao' => 'Casarão amplo e reformado no centro histórico de Barbalha, ideal para grupos na Festa do Pau da Bandeira.',
                'preco' => 520.00, 'cidade' => 'Barbalha', 'bairro' => 'Centro',
                'endereco' => 'Rua da Matriz, 77', 'quartos' => 5, 'banheiros' => 4, 'area' => 320.00,
                'latitude' => -7.3058, 'longitude' => -39.3042,
            ],
            [ // casa09
                'titulo' => 'Casa perto do Balneário do Caldas',
                'descricao' => 'Casa simples e confortável a poucos minutos do Balneário do Caldas e das trilhas da Chapada.',
                'preco' => 250.00, 'cidade' => 'Barbalha', 'bairro' => 'Caldas',
                'endereco' => 'Sítio Caldas, s/n', 'quartos' => 2, 'banheiros' => 2, 'area' => 110.00,
                'latitude' => -7.3530, 'longitude' => -39.3410,
            ],
            [ // casa10
                'titulo' => 'Chalé no Arajara',
                'descricao' => 'Chalé aconchegante próximo ao Arajara Park, com área verde e churrasqueira.',
                'preco' => 300.00, 'cidade' => 'Barbalha', 'bairro' => 'Arajara',
                'endereco' => 'Estrada do Arajara, 1500', 'quartos' => 2, 'banheiros' => 1, 'area' => 90.00,
                'latitude' => -7.3420, 'longitude' => -39.4100,
            ],
        ]);
    }
}