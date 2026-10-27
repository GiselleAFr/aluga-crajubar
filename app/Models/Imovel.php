<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property int $id
 * @property string $finalidade
 * @property int|null $user_id
 */
class Imovel extends Model
{
    use HasFactory;
    protected $table = 'imoveis';

    protected $fillable = [
        'titulo',
        'descricao',
        'tipo',
        'finalidade',
        'periodo_locacao',
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

    public function usuariosFavoritaram()
    {
        return $this->belongsToMany(
            User::class,
            'favoritos',
            'imovel_id',
            'user_id'
        )->withTimestamps();
    }
}