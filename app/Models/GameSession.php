<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جلسة لعب — التذكرة تُخصَم بمجرّد الدخول (24.2). */
class GameSession extends Model
{
    protected $table = 'game_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tickets_spent' => 'decimal:2'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
