<?php

namespace App\Models;

use App\Services\Gamification\EconomyRules;
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

    /**
     * تكلفة الدخول الفعليّة (7.5 · 2.13): عمود اللعبة Override صريح،
     * وNULL يعني «اتبع جدول أوجه الصرف» (`xp_rules.spend` ⟵ `game.enter`)
     * وتحته الافتراضيّ العامّ `games.ticket_cost` — فمصدر السعر واحد لا اثنان.
     */
    public function entryCost(): float
    {
        return app(EconomyRules::class)->costFor(
            'game.enter',
            $this->getAttribute('ticket_cost'),
            (float) setting('games.ticket_cost', 1),
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
