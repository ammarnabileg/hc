<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonQuestionAnswer extends Model
{
    use HasFactory;

    protected $table = 'lesson_question_answers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lesson_question(): BelongsTo
    {
        return $this->belongsTo(LessonQuestion::class, 'lesson_question_id');
    }
}
