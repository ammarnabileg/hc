<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قالب بريد (email_templates.* — الدستور سطر 5069: «نصّ القالب» لكلّ نوع
 * إشعارٍ محكوم بالمصفوفة). قالبٌ واحدٌ فقط لكلّ `category` (فهرسٌ فريد)،
 * وقوالب `category = null` عامّةٌ غير مربوطة بنوعٍ بعد (مثلًا مستوردة).
 *
 * والاستبدال الديناميكيّ للنصّ **لا يُخترَع من جديد** — `Notifier` يمرّر
 * الجسم عبر `AnnouncementPersonalizer` نفسه (12.14: محرّك واحد لا ازدواج)،
 * فوسوم `[اسم]`/`[الكود]`/… تعمل هنا كما تعمل في منشورات التعليمات تمامًا.
 */
class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'category', 'name', 'subject', 'body', 'variables', 'is_enabled', 'status', 'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** القالب الفعّال لهذا النوع — مفعّلٌ وغير مؤرشَف، أو null فيُترَك النصّ الافتراضيّ. */
    public static function forCategory(string $category): ?self
    {
        return static::query()
            ->where('category', $category)
            ->where('is_enabled', true)
            ->where('status', 'active')
            ->first();
    }
}
