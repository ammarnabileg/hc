<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سجلّ تذكيرات الفعاليّة (13.3) — وهو حارس عدم التكرار على المستلِم نفسه */
class EventReminder extends Model
{
    use HasFactory;

    protected $table = 'event_reminders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
