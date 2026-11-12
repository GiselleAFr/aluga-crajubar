<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Todas as preferências do usuário autenticado (com o padrão do tipo quando nunca alteradas).
     */
    public function index(Request $request, NotificationPreferenceService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->all($request->user()),
        ]);
    }

    /**
     * Grava uma ou mais preferências do PRÓPRIO usuário.
     * Corpo: {"preferences": [{"type": "push", "enabled": false}]}
     */
    public function update(
        UpdateNotificationPreferencesRequest $request,
        NotificationPreferenceService $service,
    ): JsonResponse {
        return response()->json([
            'message' => 'Preferências de notificação atualizadas.',
            'data' => $service->update($request->user(), $request->validated('preferences')),
        ]);
    }
}