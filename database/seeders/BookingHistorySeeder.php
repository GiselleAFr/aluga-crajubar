<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Imovel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Cria até 3 hospedagens passadas e confirmadas, para testar o Histórico
 * do perfil. O app não consegue criar reservas no passado.
 *
 *   php artisan db:seed --class=BookingHistorySeeder
 *
 * Por padrão usa o cliente de teste. Para usar a sua conta, defina no .env:
 *   SEED_HISTORY_EMAIL=seu-email@exemplo.com
 */
class BookingHistorySeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_HISTORY_EMAIL', 'cliente@alugacrajubar.test');
        $cliente = User::where('email', $email)->first();

        if (! $cliente) {
            $this->command?->warn("Conta {$email} não encontrada. Nada foi criado.");

            return;
        }

        $hoje = Carbon::today();

        $jaTem = Booking::where('user_id', $cliente->id)
            ->where('status', 'confirmed')
            ->whereDate('check_out', '<', $hoje)
            ->exists();

        if ($jaTem) {
            $this->command?->info("{$email} já possui hospedagens passadas. Nada foi criado.");

            return;
        }

        $imoveis = Imovel::where('finalidade', 'aluguel')
            ->whereIn('periodo_locacao', ['diaria', 'mensal'])
            ->where('preco', '>', 0)
            ->orderBy('id')
            ->limit(3)
            ->get();

        if ($imoveis->isEmpty()) {
            $this->command?->warn('Nenhum imóvel para aluguel encontrado.');

            return;
        }

        // Há quantos dias cada estadia terminou.
        $recuos = [35, 80, 140];

        foreach ($imoveis as $indice => $imovel) {
            $checkOut = $hoje->copy()->subDays($recuos[$indice]);

            if ($imovel->periodo_locacao === 'mensal') {
                $checkIn = $checkOut->copy()->subMonthNoOverflow();
                $total = (float) $imovel->preco;
            } else {
                $noites = 3;
                $checkIn = $checkOut->copy()->subDays($noites);
                $total = (float) $imovel->preco * $noites;
            }

            Booking::create([
                'user_id' => $cliente->id,
                'imovel_id' => $imovel->id,
                'property_name' => $imovel->titulo,
                'city' => $imovel->cidade,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'guests' => 2,
                'total_amount' => $total,
                'currency' => 'BRL',
                'status' => 'confirmed',
            ]);
        }

        $this->command?->info("Hospedagens passadas criadas para {$email}: {$imoveis->count()}.");
    }
}