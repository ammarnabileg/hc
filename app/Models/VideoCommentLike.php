<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** لايك واحد لكلّ (مستخدم، تعليق) — يضمنه قيدٌ فريد في القاعدة لا شرطٌ في الكود (3.1). */
class VideoCommentLike extends Model
{
    protected $table = 'video_comment_likes';

    protected $guarded = [];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(VideoComment::class, 'video_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
