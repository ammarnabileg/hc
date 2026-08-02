<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سؤال مكافأة (12.10-أ) — بنك منفصل عن أسئلة الدرس وأسئلة الحروب.
 *
 * ⭐ `correct_answer` **لا يُرسَل للمتصفّح أبدًا**: التصحيح في الخادم حصرًا،
 * فالسؤال يُنشَر في جروبات عامّة ومن يرى الإجابة في الصفحة يوزّعها على الجميع.
 */
class RewardQuestion extends Model
{
    use HasFactory;

    protected $table = 'reward_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RewardQuestionAnswer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
