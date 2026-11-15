<?php

namespace Tests\Feature;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandlordImovelPhotosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_upload_select_and_delete_photos(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $imovel = $this->createProperty($landlord);
        Sanctum::actingAs($landlord);

        $upload = $this->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [
                $this->png('frente.png'),
                $this->png('sala.png'),
            ],
        ]);

        $upload->assertCreated()
            ->assertJsonCount(2, 'data.fotos')
            ->assertJsonPath('data.fotos.0.is_cover', true)
            ->assertJsonPath('data.fotos.1.is_cover', false);

        $fotos = $upload->json('data.fotos');
        $primeiraFoto = $imovel->fotos()->findOrFail($fotos[0]['id']);
        $segundaFoto = $imovel->fotos()->findOrFail($fotos[1]['id']);

        Storage::disk('public')->assertExists($primeiraFoto->path);
        Storage::disk('public')->assertExists($segundaFoto->path);

        $this->patchJson(
            '/api/imoveis/'.$imovel->id.'/fotos/'.$segundaFoto->id.'/principal'
        )
            ->assertOk()
            ->assertJsonPath('data.imagem', Storage::disk('public')->url($segundaFoto->path))
            ->assertJsonPath('data.fotos.1.is_cover', true);

        $this->deleteJson('/api/imoveis/'.$imovel->id.'/fotos/'.$segundaFoto->id)
            ->assertOk()
            ->assertJsonPath('data.fotos.0.is_cover', true);

        Storage::disk('public')->assertMissing($segundaFoto->path);
        $this->assertDatabaseMissing('imovel_fotos', ['id' => $segundaFoto->id]);
    }

    public function test_photo_routes_require_a_landlord_and_property_ownership(): void
    {
        Storage::fake('public');
        $owner = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $imovel = $this->createProperty($owner);
        $foto = $imovel->fotos()->create([
            'path' => 'imoveis/'.$imovel->id.'/privada.png',
            'position' => 0,
            'is_cover' => true,
        ]);

        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [$this->png('sem-login.png')],
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [$this->png('cliente.png')],
        ])->assertForbidden();

        Sanctum::actingAs($otherLandlord);
        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [$this->png('outro-locador.png')],
        ])->assertNotFound();

        $this->patchJson(
            '/api/imoveis/'.$imovel->id.'/fotos/'.$foto->id.'/principal'
        )->assertNotFound();
        $this->deleteJson('/api/imoveis/'.$imovel->id.'/fotos/'.$foto->id)
            ->assertNotFound();

        $this->assertDatabaseCount('imovel_fotos', 1);
    }

    public function test_upload_enforces_the_ten_photo_limit_and_image_validation(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $imovel = $this->createProperty($landlord);
        Sanctum::actingAs($landlord);

        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [UploadedFile::fake()->createWithContent('documento.txt', 'não é uma imagem')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fotos.0');

        for ($position = 0; $position < 10; $position++) {
            $imovel->fotos()->create([
                'path' => 'imoveis/'.$imovel->id.'/foto-'.$position.'.png',
                'position' => $position,
                'is_cover' => $position === 0,
            ]);
        }

        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [$this->png('extra.png')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fotos');

        $this->assertDatabaseCount('imovel_fotos', 10);
    }

    public function test_public_property_responses_include_photo_urls_and_property_deletion_cleans_files(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $imovel = $this->createProperty($landlord);
        Sanctum::actingAs($landlord);

        $this->withHeader('Accept', 'application/json')->post('/api/imoveis/'.$imovel->id.'/fotos', [
            'fotos' => [$this->png('capa.png')],
        ])->assertCreated();

        $foto = $imovel->fotos()->firstOrFail();

        $this->getJson('/api/imoveis/'.$imovel->id)
            ->assertOk()
            ->assertJsonPath('data.imagem', Storage::disk('public')->url($foto->path))
            ->assertJsonPath('data.fotos.0.url', Storage::disk('public')->url($foto->path));

        $this->deleteJson('/api/imoveis/'.$imovel->id)->assertOk();

        Storage::disk('public')->assertMissing($foto->path);
        $this->assertDatabaseMissing('imovel_fotos', ['id' => $foto->id]);
    }

    private function createProperty(User $owner): Imovel
    {
        return $owner->imoveis()->create([
            'titulo' => 'Casa de exemplo',
            'descricao' => 'Imóvel para locação',
            'tipo' => 'casa',
            'finalidade' => 'aluguel',
            'periodo_locacao' => 'diaria',
            'preco' => 120,
            'cidade' => 'Crato',
            'bairro' => 'Centro',
            'endereco' => 'Rua Principal, 10',
        ]);
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r9sAAAAASUVORK5CYII=')
        );
    }
}
