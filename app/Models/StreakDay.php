<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreakDay extends Model
{
    use HasFactory;

    protected $table = 'streak_days';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'club_5am' => 'boolean',
            'day' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
