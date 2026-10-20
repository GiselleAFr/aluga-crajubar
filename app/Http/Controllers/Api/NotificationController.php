<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        // Sempre a partir do usuário autenticado: nunca existe caminho para ler notificação alheia.
        $query = $request->boolean('unread')
            ? $user->unreadNotifications()
            : $user->notifications();

        // O desempate por id mantém a ordem estável entre páginas quando created_at coincide.
        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (DatabaseNotification $notification) => (new NotificationResource($notification))->resolve())
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        // Notificação de outro usuário (ou inexistente) cai no mesmo 404.
        $found = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        $found->markAsRead();

        return response()->json([
            'message' => 'Notificação marcada como lida.',
            'data' => (new NotificationResource($found->fresh()))->resolve(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->reorder()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notificações marcadas como lidas.',
            'updated' => $updated,
        ]);
    }
}