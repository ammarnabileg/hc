<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    use HasFactory;

    protected $table = 'milestones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_verified' => 'boolean',
            'progress_percent' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function verified_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
