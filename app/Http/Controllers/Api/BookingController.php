<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    /**
     * Criar uma reserva.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'imovel_id' => ['required', 'integer', 'exists:imoveis,id'],
            'modalidade' => ['required', Rule::in(['diaria', 'mensal'])],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $imovel = Imovel::findOrFail($validated['imovel_id']);

        // Imóveis à venda não aceitam reservas.
        if ($imovel->finalidade !== 'aluguel') {
            return response()->json([
                'message' => 'Este imóvel não está disponível para reserva.',
            ], 422);
        }

        // A modalidade escolhida precisa corresponder ao imóvel.
        if ($imovel->periodo_locacao !== $validated['modalidade']) {
            return response()->json([
                'message' => 'A modalidade escolhida não corresponde à modalidade cadastrada para este imóvel.',
                'modalidade_disponivel' => $imovel->periodo_locacao,
            ], 422);
        }

        if ($imovel->preco === null || (float) $imovel->preco <= 0) {
            return response()->json([
                'message' => 'O imóvel não possui um preço válido para reserva.',
            ], 422);
        }

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        if ($validated['modalidade'] === 'mensal') {
            // A reserva mensal deve terminar na mesma data do mês.
            $dataEsperada = $checkIn->copy()->addMonthNoOverflow();

            while ($dataEsperada->lt($checkOut)) {
                $dataEsperada->addMonthNoOverflow();
            }

            if (!$dataEsperada->equalTo($checkOut)) {
                return response()->json([
                    'message' => 'A reserva mensal deve corresponder a um ou mais meses completos.',
                ], 422);
            }

            // Calcula a quantidade de meses completos.
            $meses = 0;
            $dataAtual = $checkIn->copy();

            while ($dataAtual->lt($checkOut)) {
                $dataAtual->addMonthNoOverflow();
                $meses++;
            }

            $valorTotal = (float) $imovel->preco * $meses;
        } else {
            // Na modalidade diária, cada dia é cobrado separadamente.
            $dias = $checkIn->diffInDays($checkOut);

            if ($dias < 1) {
                return response()->json([
                    'message' => 'A reserva deve ter pelo menos uma diária.',
                ], 422);
            }

            $valorTotal = (float) $imovel->preco * $dias;
        }

        // Impede reservas com períodos sobrepostos.
        $conflito = Booking::where('imovel_id', $imovel->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->exists();

        if ($conflito) {
            return response()->json([
                'message' => 'O imóvel já possui uma reserva para esse período.',
            ], 409);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'imovel_id' => $imovel->id,
            'property_name' => $imovel->titulo,
            'city' => $imovel->cidade,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'guests' => $validated['guests'],
            'total_amount' => $valorTotal,
            'currency' => 'BRL',
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Reserva criada com sucesso.',
            'booking' => $booking,
        ], 201);
    }

    /**
     * Consultar as reservas do usuário autenticado.
     */
    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with('imovel')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($bookings);
    }

    /**
     * Visualizar os detalhes de uma reserva.
     */
    public function show(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Reserva não encontrada.',
            ], 404);
        }

        return response()->json([
            'booking' => $booking->load('imovel'),
        ]);
    }

    /**
     * Consultar o status de uma reserva.
     */
    public function status(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Reserva não encontrada.',
            ], 404);
        }

        return response()->json([
            'booking_id' => $booking->id,
            'status' => $booking->status,
        ]);
    }

    /**
     * Verificar a disponibilidade de um imóvel em um período.
     */
    public function disponibilidade(Request $request)
    {
        $validated = $request->validate([
            'imovel_id' => ['required', 'integer', 'exists:imoveis,id'],
            'modalidade' => ['required', Rule::in(['diaria', 'mensal'])],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $imovel = Imovel::findOrFail($validated['imovel_id']);

        if (
            $imovel->finalidade !== 'aluguel'
            || $imovel->periodo_locacao !== $validated['modalidade']
        ) {
            return response()->json([
                'imovel_id' => $imovel->id,
                'disponivel' => false,
                'message' => 'Este imóvel não aceita reservas nessa modalidade.',
            ]);
        }

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        if ($validated['modalidade'] === 'mensal') {
            $dataEsperada = $checkIn->copy()->addMonthNoOverflow();

            while ($dataEsperada->lt($checkOut)) {
                $dataEsperada->addMonthNoOverflow();
            }

            if (!$dataEsperada->equalTo($checkOut)) {
                return response()->json([
                    'imovel_id' => $imovel->id,
                    'disponivel' => false,
                    'message' => 'A reserva mensal deve corresponder a um ou mais meses completos.',
                ]);
            }
        }

        $conflito = Booking::where('imovel_id', $imovel->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->exists();

        return response()->json([
            'imovel_id' => $imovel->id,
            'modalidade' => $validated['modalidade'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'disponivel' => !$conflito,
        ]);
    }
}
