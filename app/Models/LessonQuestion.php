<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonQuestion extends Model
{
    use HasFactory;

    protected $table = 'lesson_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_general' => 'boolean',
            'options' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }
}
