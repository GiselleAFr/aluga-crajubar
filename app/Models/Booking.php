<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'imovel_id',
        'property_name',
        'city',
        'check_in',
        'check_out',
        'guests',
        'total_amount',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'guests' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class, 'imovel_id');
    }
}