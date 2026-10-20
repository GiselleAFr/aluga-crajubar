<?php

namespace Tests\Feature;

use App\Models\LandlordRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImovelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_imoveis_remains_public(): void
    {
        $this->getJson('/api/imoveis')->assertOk();
    }

    public function test_guest_cannot_create_an_imovel(): void
    {
        $this->postJson('/api/imoveis', [])->assertUnauthorized();
    }

    public function test_client_cannot_create_an_imovel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/imoveis', [])->assertForbidden();
    }

    public function test_client_with_a_pending_request_cannot_create_an_imovel(): void
    {
        $user = User::factory()->create();
        LandlordRequest::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/imoveis', [])->assertForbidden();
    }

    public function test_landlord_reaches_the_validation_instead_of_being_blocked(): void
    {
        Sanctum::actingAs(User::factory()->landlord()->create());

        $this->postJson('/api/imoveis', [])->assertUnprocessable();
    }
}