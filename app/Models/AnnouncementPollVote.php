<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صوت في استطلاع داخل منشور (12.6-أ) — صوتٌ واحد لكلّ مستخدم، والتبديل تعديل.
 */
class AnnouncementPollVote extends Model
{
    use HasFactory;

    protected $table = 'announcement_poll_votes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'option_index' => 'integer',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
