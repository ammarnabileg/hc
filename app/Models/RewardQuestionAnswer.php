<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إجابة مستخدم على سؤال مكافأة (12.10-أ).
 * القيد الفريد (سؤال، مستخدم) هو ما يضمن «إجابة واحدة» و«لا صرف مكرّر».
 */
class RewardQuestionAnswer extends Model
{
    use HasFactory;

    protected $table = 'reward_question_answers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(RewardQuestion::class, 'reward_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
