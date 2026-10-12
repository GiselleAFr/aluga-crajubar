<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => (new UserProfileResource($user))->resolve(),
            'completed_stays_count' => $user->bookings()->where('status', 'completed')->count(),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email', 'phone']))->save();

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => (new UserProfileResource($user->fresh()))->resolve(),
        ]);
    }

    public function stays(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $paginator = $request->user()->bookings()
            ->orderByDesc('check_out')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Booking $booking) => $this->stayData($booking))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function stayData(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'property_name' => $booking->property_name,
            'city' => $booking->city,
            'check_in' => $booking->check_in?->format('Y-m-d'),
            'check_out' => $booking->check_out?->format('Y-m-d'),
            'guests' => $booking->guests,
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'status' => $booking->status,
        ];
    }
}
