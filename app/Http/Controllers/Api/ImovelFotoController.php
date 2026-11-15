<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use App\Models\ImovelFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImovelFotoController extends Controller
{
    private const MAX_PHOTOS = 10;

    private const MAX_FILE_SIZE_KB = 10240;

    public function store(Request $request, Imovel $imovel): JsonResponse
    {
        $this->ensureOwner($request, $imovel);

        $validated = $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_FILE_SIZE_KB],
        ]);

        $storedPaths = [];

        try {
            $imovel = DB::transaction(function () use ($imovel, $validated, &$storedPaths): Imovel {
                $lockedImovel = Imovel::query()
                    ->whereKey($imovel->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $fotosExistentes = $lockedImovel->fotos()->count();
                $novasFotos = $validated['fotos'];

                if ($fotosExistentes + count($novasFotos) > self::MAX_PHOTOS) {
                    throw ValidationException::withMessages([
                        'fotos' => ['O imóvel pode possuir no máximo 10 fotos.'],
                    ]);
                }

                $temCapa = $lockedImovel->fotos()->where('is_cover', true)->exists();
                $posicao = ($lockedImovel->fotos()->max('position') ?? -1) + 1;

                foreach ($novasFotos as $indice => $foto) {
                    $path = $foto->store('imoveis/'.$lockedImovel->id, 'public');
                    if ($path === false) {
                        throw new RuntimeException('Não foi possível armazenar a foto enviada.');
                    }

                    $storedPaths[] = $path;
                    $lockedImovel->fotos()->create([
                        'disk' => 'public',
                        'path' => $path,
                        'position' => $posicao + $indice,
                        'is_cover' => ! $temCapa && $indice === 0,
                    ]);
                }

                return $lockedImovel->load('fotos');
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== [] && ! Storage::disk('public')->delete($storedPaths)) {
                throw new RuntimeException(
                    'Não foi possível limpar os arquivos após a falha no cadastro das fotos.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Fotos adicionadas com sucesso.',
            'data' => new ImovelResource($imovel),
        ], 201);
    }

    public function setCover(Request $request, Imovel $imovel, ImovelFoto $foto): JsonResponse
    {
        $this->ensureOwner($request, $imovel);

        DB::transaction(function () use ($imovel, $foto): void {
            $lockedImovel = Imovel::query()
                ->whereKey($imovel->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $fotoSelecionada = $lockedImovel->fotos()
                ->whereKey($foto->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedImovel->fotos()->update(['is_cover' => false]);
            $fotoSelecionada->update(['is_cover' => true]);
        });

        return $this->propertyResponse($imovel, 'Foto principal atualizada com sucesso.');
    }

    public function destroy(Request $request, Imovel $imovel, ImovelFoto $foto): JsonResponse
    {
        $this->ensureOwner($request, $imovel);

        $foto = $imovel->fotos()
            ->whereKey($foto->getKey())
            ->firstOrFail();
        $disk = $foto->disk;
        $path = $foto->path;
        $eraCapa = DB::transaction(function () use ($imovel, $foto): bool {
            $lockedImovel = Imovel::query()
                ->whereKey($imovel->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $fotoBloqueada = $lockedImovel->fotos()
                ->whereKey($foto->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $eraCapa = $fotoBloqueada->is_cover;

            $fotoBloqueada->delete();

            if ($eraCapa) {
                $proximaCapa = $lockedImovel->fotos()
                    ->orderBy('position')
                    ->orderBy('id')
                    ->first();

                if ($proximaCapa) {
                    $lockedImovel->fotos()->update(['is_cover' => false]);
                    $proximaCapa->update(['is_cover' => true]);
                }
            }

            return $eraCapa;
        });

        if (! Storage::disk($disk)->delete($path)) {
            throw new RuntimeException('Não foi possível excluir o arquivo da foto.');
        }

        return $this->propertyResponse($imovel, 'Foto excluída com sucesso.');
    }

    private function propertyResponse(Imovel $imovel, string $message): JsonResponse
    {
        $imovel->load('fotos');

        return response()->json([
            'message' => $message,
            'data' => new ImovelResource($imovel),
        ]);
    }

    private function ensureOwner(Request $request, Imovel $imovel): void
    {
        abort_unless((int) $imovel->user_id === (int) $request->user()->id, 404);
    }
}
