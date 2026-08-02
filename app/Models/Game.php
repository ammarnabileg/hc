<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** لعبة في قسم الألعاب (7.5 · 24.2). */
class Game extends Model
{
    protected $table = 'games';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ticket_cost' => 'decimal:2'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
