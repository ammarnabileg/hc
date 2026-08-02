<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** نقاش حائط الشكر (13.4-ي) — والتصويت على `post_votes` المورفيّ القائم. */
class ThanksWallPost extends Model
{
    use HasFactory;

    protected $table = 'thanks_wall_posts';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(PostVote::class, 'votable');
    }
}
