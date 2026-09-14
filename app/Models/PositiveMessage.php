<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالة إيجابيّة من مكتبة الأدمن (2.6-ب) — نصّها وسياقها ولغتها وتفعيلها
 * كلّها بيده، فلا نصّ تشجيع محروق في الكود (2.13).
 *
 * `language` بطاقة تصنيفٍ إداريّة (جدول/فلتر الشاشة) لا فلترة عرضٍ حيّة:
 * المنصّة عربيّةٌ بالكامل حاليًّا (`<html lang="ar">` ثابتة، بلا مبدّل لغةٍ
 * فعليّ للمستخدم) — فاختيار الرسالة في `PositiveMessages::pool()` لا يقرأ
 * هذا الحقل بعد، تحسّبًا لمنصّةٍ ثنائيّة اللغة لاحقًا لا كذبًا على القارئ.
 */
class PositiveMessage extends Model
{
    use HasFactory;

    protected $table = 'positive_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
