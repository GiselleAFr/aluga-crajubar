<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Solicitar uma reserva.
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

        if ($imovel->finalidade !== 'aluguel') {
            return response()->json([
                'message' => 'Este imóvel não está disponível para reserva.',
            ], 422);
        }

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
            $dataEsperada = $checkIn->copy()->addMonthNoOverflow();

            while ($dataEsperada->lt($checkOut)) {
                $dataEsperada->addMonthNoOverflow();
            }

            if (!$dataEsperada->equalTo($checkOut)) {
                return response()->json([
                    'message' => 'A reserva mensal deve corresponder a um ou mais meses completos.',
                ], 422);
            }

            $meses = 0;
            $dataAtual = $checkIn->copy();

            while ($dataAtual->lt($checkOut)) {
                $dataAtual->addMonthNoOverflow();
                $meses++;
            }

            $valorTotal = (float) $imovel->preco * $meses;
        } else {
            $dias = $checkIn->diffInDays($checkOut);

            if ($dias < 1) {
                return response()->json([
                    'message' => 'A reserva deve ter pelo menos uma diária.',
                ], 422);
            }

            $valorTotal = (float) $imovel->preco * $dias;
        }

        $conflito = Booking::where('imovel_id', $imovel->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->exists();

        if ($conflito) {
            return response()->json([
                'message' => 'O imóvel já possui uma reserva ou solicitação para esse período.',
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
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Solicitação de reserva enviada. Aguarde a análise do locador.',
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
     * Listar solicitações dos imóveis do locatário autenticado.
     */
    public function solicitacoes(Request $request)
    {
        $bookings = Booking::whereHas('imovel', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
            ->with('user:id,name,email,phone', 'imovel')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($bookings);
    }

    /**
     * Confirmar uma solicitação de reserva.
     */
    public function confirmar(Request $request, Booking $booking)
    {
        $booking->loadMissing('imovel');

        if (
            !$booking->imovel
            || $booking->imovel->user_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Solicitação de reserva não encontrada.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Somente solicitações pendentes podem ser confirmadas.',
            ], 422);
        }

        $resultado = DB::transaction(function () use ($booking) {
            $reserva = Booking::whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->status !== 'pending') {
                return [
                    'error' => 'Somente solicitações pendentes podem ser confirmadas.',
                    'code' => 422,
                ];
            }

            $conflito = Booking::where('imovel_id', $reserva->imovel_id)
                ->where('id', '<>', $reserva->id)
                ->where('status', 'confirmed')
                ->where('check_in', '<', $reserva->check_out)
                ->where('check_out', '>', $reserva->check_in)
                ->exists();

            if ($conflito) {
                return [
                    'error' => 'Já existe uma reserva confirmada para esse período.',
                    'code' => 409,
                ];
            }

            $reserva->update([
                'status' => 'confirmed',
            ]);

            return [
                'booking' => $reserva->fresh()->load('imovel'),
            ];
        });

        if (isset($resultado['error'])) {
            return response()->json([
                'message' => $resultado['error'],
            ], $resultado['code']);
        }

        return response()->json([
            'message' => 'Reserva confirmada com sucesso.',
            'booking' => $resultado['booking'],
        ]);
    }

    /**
     * Recusar uma solicitação de reserva.
     */
    public function recusar(Request $request, Booking $booking)
    {
        $booking->loadMissing('imovel');

        if (
            !$booking->imovel
            || $booking->imovel->user_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Solicitação de reserva não encontrada.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Somente solicitações pendentes podem ser recusadas.',
            ], 422);
        }

        $atualizada = DB::transaction(function () use ($booking) {
            $reserva = Booking::whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->status !== 'pending') {
                return false;
            }

            $reserva->update([
                'status' => 'rejected',
            ]);

            return $reserva->fresh()->load('imovel');
        });

        if ($atualizada === false) {
            return response()->json([
                'message' => 'Esta solicitação já foi processada.',
            ], 422);
        }

        return response()->json([
            'message' => 'Solicitação de reserva recusada.',
            'booking' => $atualizada,
        ]);
    }

    /**
     * Visualizar os detalhes de uma reserva própria.
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
     * Consultar o status de uma reserva própria.
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
     * Cancelar uma reserva própria antes do início da estadia.
     */
    public function cancelar(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Reserva não encontrada.',
            ], 404);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Esta reserva já foi cancelada.',
            ], 422);
        }

        if (Carbon::parse($booking->check_in)->startOfDay()->lte(now()->startOfDay())) {
            return response()->json([
                'message' => 'Não é possível cancelar uma reserva cuja data de entrada já chegou ou passou.',
            ], 422);
        }

        if (!in_array($booking->status, ['confirmed', 'pending'], true)) {
            return response()->json([
                'message' => 'Esta reserva não pode ser cancelada no status atual.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Reserva cancelada com sucesso.',
            'booking' => $booking->fresh(),
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