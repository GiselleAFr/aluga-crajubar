<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LandlordRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectLandlordRequestRequest;
use App\Http\Resources\LandlordRequestResource;
use App\Models\LandlordRequest;
use App\Services\LandlordRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(LandlordRequestStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = LandlordRequest::query()
            ->with('user:id,name,email,phone')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'data' => LandlordRequestResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function document(LandlordRequest $landlordRequest, string $side): StreamedResponse
    {
        abort_unless($landlordRequest->status === LandlordRequestStatus::PENDING, 404);

        $path = match ($side) {
            'front' => $landlordRequest->document_front_path,
            'back' => $landlordRequest->document_back_path,
            default => null,
        };
        $disk = Storage::disk('local');

        abort_unless(is_string($path) && $disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approve(Request $request, LandlordRequest $landlordRequest, LandlordRequestService $service): JsonResponse
    {
        $approved = $service->approve($landlordRequest, $request->user())->load('user:id,name,email,phone');

        return response()->json([
            'message' => 'Solicitação aprovada. O usuário agora é locador.',
            'data' => (new LandlordRequestResource($approved))->resolve(),
        ]);
    }

    public function reject(RejectLandlordRequestRequest $request, LandlordRequest $landlordRequest, LandlordRequestService $service): JsonResponse
    {
        $rejected = $service->reject(
            $landlordRequest,
            $request->user(),
            $request->validated('rejection_reason'),
        )->load('user:id,name,email,phone');

        return response()->json([
            'message' => 'Solicitação recusada.',
            'data' => (new LandlordRequestResource($rejected))->resolve(),
        ]);
    }
}
