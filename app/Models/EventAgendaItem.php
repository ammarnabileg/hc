<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAgendaItem extends Model
{
    use HasFactory;

    protected $table = 'event_agenda_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
