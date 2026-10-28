<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Booking;

/**
 * @property int $id
 * @property string $finalidade
 * @property int|null $user_id
 */
class Imovel extends Model
{
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'imovel_id');
    }

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}