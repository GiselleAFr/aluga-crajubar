<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imovel>
 */
class ImovelFactory extends Factory
{
    protected $model = Imovel::class;

    public function definition(): array
    {
        $finalidade = fake()->randomElement([
            'venda',
            'aluguel',
        ]);

        $periodoLocacao = $finalidade === 'aluguel'
            ? fake()->randomElement(['diaria', 'mensal'])
            : null;

        $tipo = fake()->randomElement([
            'casa',
            'apartamento',
            'terreno',
            'comercial',
            'outro',
        ]);

        $cidade = fake()->randomElement([
            'Crato',
            'Juazeiro do Norte',
            'Barbalha',
        ]);

        return [
            'user_id' => User::factory()->landlord(),

            'titulo' => ucfirst($tipo) . ' para '
                . ($finalidade === 'venda' ? 'venda' : 'aluguel')
                . ' em ' . $cidade,

            'descricao' => fake()->paragraph(),

            'tipo' => $tipo,
            'finalidade' => $finalidade,
            'periodo_locacao' => $periodoLocacao,

            'preco' => $finalidade === 'venda'
                ? fake()->randomFloat(2, 80000, 900000)
                : fake()->randomFloat(2, 60, 900),

            'cidade' => $cidade,
            'bairro' => fake()->streetName(),
            'endereco' => fake()->address(),

            'quartos' => $tipo === 'terreno'
                ? 0
                : fake()->numberBetween(1, 5),

            'banheiros' => $tipo === 'terreno'
                ? 0
                : fake()->numberBetween(1, 4),

            'area' => fake()->randomFloat(2, 30, 500),
        ];
    }
}
