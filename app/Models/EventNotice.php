<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** «إشعار المسجّلين» (24.3) — الآن أو مجدول، وله مستقرٌّ ومُلتقِط */
class EventNotice extends Model
{
    use HasFactory;

    protected $table = 'event_notices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'recipients' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /** الشريحة المحفوظة إن كان الإشعار دعوة شريحةٍ لا إشعار مسجّلين عاديّ (12.11) */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AdAudience::class, 'segment_id');
    }
}
