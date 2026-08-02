<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** محاولة اختبار الدرس (4.1) — تحمل الترتيب العشوائيّ والإجابات وحاجز إعادة المحاولة. */
class LessonQuizAttempt extends Model
{
    protected $table = 'lesson_quiz_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'question_order' => 'array',
            'option_order' => 'array',
            'answers' => 'array',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
            'retry_available_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /** الثواني المتبقّية على حاجز الانتظار — والحساب في الخادم دائمًا (4.1). */
    public function secondsUntilRetry(): int
    {
        if (! $this->retry_available_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->retry_available_at, false));
    }
}
