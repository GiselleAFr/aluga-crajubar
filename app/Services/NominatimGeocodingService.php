<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use App\Exceptions\LocationNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class NominatimGeocodingService
{
    public function geocode(string $address): array
    {
        $cacheKey = 'nominatim:geocode:'.hash('sha256', mb_strtolower(trim($address)));

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (! Cache::add('nominatim:request-lock', true, now()->addSecond())) {
            throw new GeocodingException('Aguarde um instante antes de fazer outra busca de localização.');
        }

        $location = $this->requestNominatim($address);
        Cache::put($cacheKey, $location, now()->addMinutes(config('geocoding.cache_ttl')));

        return $location;
    }

    private function requestNominatim(string $address): array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('geocoding.user_agent'))
                ->timeout(config('geocoding.timeout'))
                ->get(config('geocoding.nominatim_url'), [
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'accept-language' => 'pt-BR,pt',
                ]);
        } catch (Throwable) {
            throw new GeocodingException('Não foi possível consultar o serviço de localização agora.');
        }

        if (! $response->successful()) {
            throw new GeocodingException('Não foi possível consultar o serviço de localização agora.');
        }

        $result = $response->json('0');
        $latitude = data_get($result, 'lat');
        $longitude = data_get($result, 'lon');

        if (! $result || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw new LocationNotFoundException('Endereço não encontrado.');
        }

        return [
            'formatted_address' => data_get($result, 'display_name'),
            'place_id' => (string) data_get($result, 'place_id'),
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'map_url' => $this->mapUrl($latitude, $longitude),
            'provider' => 'OpenStreetMap',
            'attribution' => '© OpenStreetMap contributors',
        ];
    }

    private function mapUrl(string|float $latitude, string|float $longitude): string
    {
        return 'https://www.openstreetmap.org/?mlat='.$latitude.'&mlon='.$longitude.'#map=18/'.$latitude.'/'.$longitude;
    }
}
