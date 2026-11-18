<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LandlordRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LandlordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Números da visão geral do painel administrativo.
 */
class OverviewController extends Controller
{
    /** Prazo para analisar uma solicitação (regra de negócio nº 4). */
    private const REVIEW_DEADLINE_HOURS = 48;

    public function __invoke(): JsonResponse
    {
        $porStatus = LandlordRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendentes = LandlordRequest::query()
            ->where('status', LandlordRequestStatus::PENDING->value);

        $atrasadas = (clone $pendentes)
            ->where('created_at', '<=', now()->subHours(self::REVIEW_DEADLINE_HOURS))
            ->count();

        $maisAntiga = (clone $pendentes)->min('created_at');

        return response()->json([
            'data' => [
                'landlord_requests' => [
                    'pending' => (int) ($porStatus[LandlordRequestStatus::PENDING->value] ?? 0),
                    'approved' => (int) ($porStatus[LandlordRequestStatus::APPROVED->value] ?? 0),
                    'rejected' => (int) ($porStatus[LandlordRequestStatus::REJECTED->value] ?? 0),
                    'overdue' => $atrasadas,
                    'oldest_pending_at' => $maisAntiga
                        ? Carbon::parse($maisAntiga)->toISOString()
                        : null,
                ],
                'review_deadline_hours' => self::REVIEW_DEADLINE_HOURS,
            ],
        ]);
    }
}