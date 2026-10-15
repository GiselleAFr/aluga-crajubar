<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'address',
        'place_id',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function openStreetMapUrl(): string
    {
        return 'https://www.openstreetmap.org/?mlat='.$this->latitude.'&mlon='.$this->longitude.'#map=18/'.$this->latitude.'/'.$this->longitude;
    }
}
