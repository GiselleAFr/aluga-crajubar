<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NominatimLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['geocoding.user_agent' => 'AlugaCrajubar tests (tests@example.com)']);
    }

    public function test_it_geocodes_an_address_with_nominatim(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([[
                'place_id' => 123,
                'display_name' => 'Juazeiro do Norte, Ceará, Brasil',
                'lat' => '-7.2122',
                'lon' => '-39.3153',
            ]]),
        ]);

        $this->getJson('/api/maps/geocode?address='.urlencode('Juazeiro do Norte, CE'))
            ->assertOk()
            ->assertJsonPath('location.formatted_address', 'Juazeiro do Norte, Ceará, Brasil')
            ->assertJsonPath('location.place_id', '123')
            ->assertJsonPath('location.latitude', -7.2122)
            ->assertJsonPath('location.longitude', -39.3153)
            ->assertJsonPath('location.provider', 'OpenStreetMap')
            ->assertJsonPath('location.attribution', '© OpenStreetMap contributors');

        Http::assertSentCount(1);
    }

    public function test_it_returns_not_found_when_nominatim_cannot_geocode_address(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([]),
        ]);

        $this->getJson('/api/maps/geocode?address='.urlencode('Endereço inexistente 123'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Endereço não encontrado.');
    }

    public function test_it_exposes_saved_property_location_with_openstreetmap_link(): void
    {
        $property = Property::factory()->create([
            'title' => 'Casa no Cariri',
            'address' => 'Rua das Flores, 100, Crato - CE',
            'place_id' => '123',
            'latitude' => -7.2345678,
            'longitude' => -39.4098765,
        ]);

        $this->getJson("/api/properties/{$property->id}/location")
            ->assertOk()
            ->assertJsonPath('property_id', $property->id)
            ->assertJsonPath('title', 'Casa no Cariri')
            ->assertJsonPath('location.address', 'Rua das Flores, 100, Crato - CE')
            ->assertJsonPath('location.latitude', -7.2345678)
            ->assertJsonPath('location.longitude', -39.4098765)
            ->assertJsonPath('location.place_id', '123')
            ->assertJsonPath('location.provider', 'OpenStreetMap');
    }
}
