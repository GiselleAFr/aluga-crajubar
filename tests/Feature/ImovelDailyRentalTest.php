<?php

namespace Tests\Feature;

use App\Models\Imovel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImovelDailyRentalTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_rental_requires_a_period_and_is_stored_as_rent(): void
    {
        $this->postJson('/api/imoveis', $this->imovelData(['periodo_locacao' => 'diaria']))
            ->assertCreated()
            ->assertJsonPath('data.finalidade', 'aluguel')
            ->assertJsonPath('data.periodo_locacao', 'diaria');

        $this->postJson('/api/imoveis', $this->imovelData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('periodo_locacao');
    }

    public function test_daily_price_filter_returns_only_daily_rentals_under_the_limit(): void
    {
        $daily = Imovel::create($this->imovelData([
            'titulo' => 'Casa por diária',
            'periodo_locacao' => 'diaria',
            'preco' => 120,
        ]));

        Imovel::create($this->imovelData([
            'titulo' => 'Casa mensal',
            'periodo_locacao' => 'mensal',
            'preco' => 100,
        ]));

        Imovel::create($this->imovelData([
            'titulo' => 'Diária acima do limite',
            'periodo_locacao' => 'diaria',
            'preco' => 200,
        ]));

        $this->getJson('/api/imoveis?preco_dia_max=150')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $daily->id);
    }

    private function imovelData(array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Casa disponível',
            'descricao' => 'Imóvel para locação',
            'tipo' => 'casa',
            'finalidade' => 'aluguel',
            'preco' => 1200,
            'cidade' => 'Juazeiro do Norte',
            'bairro' => 'Centro',
            'endereco' => 'Rua Principal, 10',
            'quartos' => 2,
            'banheiros' => 1,
            'area' => 80,
        ], $overrides);
    }
}