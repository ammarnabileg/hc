<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'price_coins' => 'decimal:2',
            'price_tickets' => 'decimal:2',
            'starts_at' => 'datetime',
        ];
    }

    public function certificate_type(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }
}
