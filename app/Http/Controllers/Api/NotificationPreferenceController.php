<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, NotificationPreferenceService $service): JsonResponse
    {
        return response()->json(['data' => $service->all($request->user())]);
    }

    public function update(UpdateNotificationPreferencesRequest $request, NotificationPreferenceService $service): JsonResponse
    {
        return response()->json([
            'message' => 'Preferências de notificação atualizadas com sucesso.',
            'data' => $service->update($request->user(), $request->validated('preferences')),
        ]);
    }
}