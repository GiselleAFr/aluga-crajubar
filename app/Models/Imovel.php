<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Imovel extends Model
{
    protected $table = 'imoveis';

    protected $fillable = [
        'titulo',
        'descricao',
        'tipo',
        'finalidade',
        'preco',
        'cidade',
        'bairro',
        'endereco',
        'quartos',
        'banheiros',
        'area',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'area' => 'decimal:2',
            'quartos' => 'integer',
            'banheiros' => 'integer',
        ];
    }
}