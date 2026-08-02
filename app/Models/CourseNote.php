<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ملاحظات المتدرّب على التدريب — مساحة واحدة مشتركة لكلّ دروسه (3.2). */
class CourseNote extends Model
{
    protected $table = 'course_notes';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
