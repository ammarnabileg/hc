<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepScore extends Model
{
    use HasFactory;

    protected $table = 'rep_scores';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'daily_loss_date' => 'date',
            'daily_loss_today' => 'decimal:2',
            'last_reset_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
