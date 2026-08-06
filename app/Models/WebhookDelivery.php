<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * محاولة إرسال ويب-هوك واحدة (12.15-ب) — سجلّ Deliveries: الحدث والحمولة
 * والحالة (pending·success·failed·exhausted) وعدد المحاولات وموعد إعادة
 * المحاولة التالية.
 */
class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
