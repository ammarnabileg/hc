<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MeetingPost extends Model
{
    use HasFactory;

    protected $table = 'meeting_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MeetingPost::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MeetingPost::class, 'parent_id');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(PostVote::class, 'votable');
    }
}
