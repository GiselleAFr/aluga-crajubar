<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $finalidade
 * @property string $status
 * @property int|null $user_id
 */
class Imovel extends Model
{
    use HasFactory;

    protected $table = 'imoveis';

    protected $attributes = [
        'status' => 'available',
    ];

    protected $with = ['proprietario:id,name,phone'];

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
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'area' => 'decimal:2',
            'quartos' => 'integer',
            'banheiros' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
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

    public function fotos(): HasMany
    {
        return $this->hasMany(ImovelFoto::class, 'imovel_id');
    }

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
