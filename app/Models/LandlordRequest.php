<?php

namespace App\Models;

use App\Enums\LandlordRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordRequest extends Model
{
    use HasFactory;

    /**
     * Somente dados informados pelo solicitante. Status, revisor e datas de análise
     * são definidos exclusivamente pelo LandlordRequestService.
     *
     * @var list<string>
     */
    protected $fillable = [
        'message',
    ];

    protected function casts(): array
    {
        return [
            'status' => LandlordRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}