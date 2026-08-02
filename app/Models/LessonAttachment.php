<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonAttachment extends Model
{
    use HasFactory;

    protected $table = 'lesson_attachments';

    protected $guarded = [];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function media_item(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }
}
