<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ويب-هوك مسجَّل لربط موقع/نظام خارجيّ بالمنصّة (12.15-ب).
 *
 * ⛔ **`secret_encrypted` وحده يُخزَّن** — `Crypt::encryptString()` لا Hash،
 * لأنّ التوقيع الصادر يحتاج فكّ السرّ وقت الإرسال (خلافًا لمفاتيح الـAPI —
 * راجع الفارق في تعليق الهجرة). لا نصّ صريح للسرّ في أيّ عمود (12.15-ج).
 */
class Webhook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
