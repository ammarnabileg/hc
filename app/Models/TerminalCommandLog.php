<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ أمرٍ نُفِّذ عبر تاب «الطرفيّة» (12.15-هـ) — مالك المنصّة حصرًا.
 *
 * صفّ واحد لكلّ أمر: مَن نفّذه · نصّ الأمر · المخرَجات المدموجة (stdout+stderr
 * مقصوصة بحدّ أسطر) · كود الخروج · مدّة التنفيذ · الـIP. لا قيدٌ على محتوى
 * الأمر (12.15-هـ صريح) — القيد الوحيد زمنيّ (`TerminalService`).
 */
class TerminalCommandLog extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
