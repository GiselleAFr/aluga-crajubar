<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GeocodingException;
use App\Exceptions\LocationNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeocodeAddressRequest;
use App\Models\Property;
use App\Services\NominatimGeocodingService;
use Illuminate\Http\JsonResponse;

class PropertyLocationController extends Controller
{
    public function geocode(GeocodeAddressRequest $request, NominatimGeocodingService $geocoding): JsonResponse
    {
        try {
            return response()->json([
                'location' => $geocoding->geocode($request->string('address')->toString()),
            ]);
        } catch (LocationNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (GeocodingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function show(Property $property): JsonResponse
    {
        return response()->json([
            'property_id' => $property->id,
            'title' => $property->title,
            'location' => [
                'address' => $property->address,
                'place_id' => $property->place_id,
                'latitude' => $property->latitude,
                'longitude' => $property->longitude,
                'map_url' => $property->openStreetMapUrl(),
                'provider' => 'OpenStreetMap',
                'attribution' => '© OpenStreetMap contributors',
            ],
        ]);
    }
}
