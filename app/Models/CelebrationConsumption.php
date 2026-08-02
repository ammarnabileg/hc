<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CelebrationConsumption extends Model
{
    use HasFactory;

    protected $table = 'celebration_consumptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function celebration_event(): BelongsTo
    {
        return $this->belongsTo(CelebrationEvent::class, 'celebration_event_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
