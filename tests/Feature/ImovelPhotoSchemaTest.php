<?php

namespace Tests\Feature;

use App\Models\Imovel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImovelPhotoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_properties_default_to_available_and_have_photo_metadata_relation(): void
    {
        $imovel = Imovel::create([
            'titulo' => 'Casa de exemplo',
            'tipo' => 'outro',
            'finalidade' => 'aluguel',
            'periodo_locacao' => 'diaria',
            'preco' => 120,
            'cidade' => 'Crato',
            'bairro' => 'Centro',
            'endereco' => 'Rua Principal, 10',
        ]);

        $this->assertSame('available', $imovel->status);

        $foto = $imovel->fotos()->create([
            'path' => 'imoveis/1/foto.jpg',
            'position' => 0,
            'is_cover' => true,
        ]);

        $this->assertDatabaseHas('imovel_fotos', [
            'id' => $foto->id,
            'imovel_id' => $imovel->id,
            'disk' => 'public',
            'path' => 'imoveis/1/foto.jpg',
            'position' => 0,
            'is_cover' => true,
        ]);
        $this->assertTrue($foto->is_cover);
        $this->assertSame($imovel->id, $foto->imovel->id);
    }
}
