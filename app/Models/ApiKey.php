<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مفتاح API لربط مواقع/أنظمة خارجيّة بالمنصّة (12.15-أ).
 *
 * ⛔ **`key_hash` وحده يُخزَّن** — لا نصّ صريح للمفتاح في أيّ عمود (12.15-ج).
 * والتحقّق وقت الطلب في `ApiKeyService::resolve()` لا هنا.
 */
class ApiKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoked_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
