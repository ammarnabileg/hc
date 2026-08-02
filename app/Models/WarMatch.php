<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** المواجهة بين محاربَين (15.1 · 15.5 · 15.6). */
class WarMatch extends Model
{
    protected $table = 'war_matches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'settlement' => 'array',
            'started_at' => 'datetime',
            'first_finished_at' => 'datetime',
            'decision_deadline_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function sides(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class, 'war_match_id');
    }

    /** طرف المواجهة المقابل لمستخدمٍ ما */
    public function rivalIdOf(int $userId): int
    {
        return $this->challenger_id === $userId ? (int) $this->opponent_id : (int) $this->challenger_id;
    }

    public function involves(int $userId): bool
    {
        return $this->challenger_id === $userId || $this->opponent_id === $userId;
    }
}
