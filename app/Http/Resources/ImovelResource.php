<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ImovelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $fotos = $this->resource->relationLoaded('fotos')
            ? $this->resource->getRelation('fotos')->sortBy('position')->values()
            : collect();
        $capa = $fotos->firstWhere('is_cover', true) ?? $fotos->first();

        $data['imagem'] = $capa
            ? Storage::disk($capa->disk)->url($capa->path)
            : null;
        $data['fotos'] = ImovelFotoResource::collection($fotos)->resolve($request);

        return $data;
    }
}
