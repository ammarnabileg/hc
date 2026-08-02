<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** تعليق على فيديو الدرس (3.1) — بردٍّ بمستوًى واحد وإخفاءٍ إشرافيّ بلا مسح. */
class VideoComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'video_comments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(VideoCommentLike::class, 'video_comment_id');
    }
}
