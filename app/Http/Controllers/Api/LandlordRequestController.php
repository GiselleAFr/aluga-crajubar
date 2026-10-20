<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLandlordRequestRequest;
use App\Http\Resources\LandlordRequestResource;
use App\Services\LandlordRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandlordRequestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $landlordRequest = $request->user()->latestLandlordRequest;

        return response()->json([
            'data' => $landlordRequest ? (new LandlordRequestResource($landlordRequest))->resolve() : null,
        ]);
    }

    public function store(StoreLandlordRequestRequest $request, LandlordRequestService $service): JsonResponse
    {
        $landlordRequest = $service->submit($request->user(), $request->validated());

        return response()->json([
            'message' => 'Solicitação enviada com sucesso. Aguarde a análise.',
            'data' => (new LandlordRequestResource($landlordRequest))->resolve(),
        ], 201);
    }
}