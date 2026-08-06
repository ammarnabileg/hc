<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ استخدام مفتاح API — سطرٌ لكلّ طلب (12.15-أ).
 * آخر 100 سجلّ لكلّ مفتاح فقط تبقى — الباقي يحذفه أمر `api:prune-request-logs`
 * المجدول (12.15-أ ⭐ الحدّ مفروضٌ فعليًّا لا وصفًا).
 */
class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
