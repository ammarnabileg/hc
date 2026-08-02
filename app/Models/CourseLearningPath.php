<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLearningPath extends Model
{
    use HasFactory;

    protected $table = 'course_learning_path';

    protected $guarded = [];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function learning_path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }
}
