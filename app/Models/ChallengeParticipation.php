<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeParticipation extends Model
{
    use HasFactory;

    protected $table = 'challenge_participations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'progress' => 'array',
            'score' => 'decimal:2',
            'started_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
