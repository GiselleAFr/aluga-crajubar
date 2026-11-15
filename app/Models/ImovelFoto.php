<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImovelFoto extends Model
{
    protected $table = 'imovel_fotos';

    protected $fillable = [
        'disk',
        'path',
        'position',
        'is_cover',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }
}
