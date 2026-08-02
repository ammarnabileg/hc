<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeUser extends Model
{
    use HasFactory;

    protected $table = 'badge_user';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'badge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
