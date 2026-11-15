<?php

namespace Tests\Feature;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandlordImovelManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_list_only_their_own_properties(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $firstProperty = $this->createProperty($landlord);
        $secondProperty = $this->createProperty($landlord, ['titulo' => 'Outro imóvel']);
        $this->createProperty($otherLandlord, ['titulo' => 'Imóvel de outro']);
        Sanctum::actingAs($landlord);

        $response = $this->getJson('/api/landlord/imoveis?per_page=100');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 100);
        $this->assertEqualsCanonicalizing(
            [$firstProperty->id, $secondProperty->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_only_landlords_can_list_their_properties(): void
    {
        $this->getJson('/api/landlord/imoveis')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/landlord/imoveis')->assertForbidden();
    }

    public function test_landlord_can_create_and_update_property_status(): void
    {
        $landlord = User::factory()->landlord()->create();
        Sanctum::actingAs($landlord);

        $this->postJson('/api/imoveis', $this->propertyData([
            'status' => 'rented',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rented');

        $property = $landlord->imoveis()->firstOrFail();

        $this->patchJson("/api/imoveis/{$property->id}", [
            'status' => 'unavailable',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'unavailable');

        $this->patchJson("/api/imoveis/{$property->id}", [
            'status' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_landlord_cannot_update_or_delete_another_owners_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $property = $this->createProperty($otherLandlord);
        Sanctum::actingAs($landlord);

        $this->patchJson("/api/imoveis/{$property->id}", [
            'titulo' => 'Tentativa de alteração',
        ])->assertNotFound();

        $this->deleteJson("/api/imoveis/{$property->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('imoveis', [
            'id' => $property->id,
            'titulo' => 'Casa de exemplo',
            'user_id' => $otherLandlord->id,
        ]);
    }

    public function test_landlord_can_delete_their_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = $this->createProperty($landlord);
        Sanctum::actingAs($landlord);

        $this->deleteJson("/api/imoveis/{$property->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Imóvel excluído com sucesso.');

        $this->assertDatabaseMissing('imoveis', ['id' => $property->id]);
    }

    private function createProperty(User $owner, array $overrides = []): Imovel
    {
        return $owner->imoveis()->create($this->propertyData($overrides));
    }

    private function propertyData(array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Casa de exemplo',
            'descricao' => 'Imóvel para locação',
            'tipo' => 'casa',
            'finalidade' => 'aluguel',
            'periodo_locacao' => 'diaria',
            'preco' => 120,
            'cidade' => 'Crato',
            'bairro' => 'Centro',
            'endereco' => 'Rua Principal, 10',
        ], $overrides);
    }
}
