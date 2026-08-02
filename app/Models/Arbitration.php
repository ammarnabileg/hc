<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arbitration extends Model
{
    use HasFactory;

    protected $table = 'arbitrations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conflict_of_interest_skipped' => 'boolean',
            'contributor_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'owner_amount' => 'decimal:2',
            'window_due_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function task_contribution(): BelongsTo
    {
        return $this->belongsTo(TaskContribution::class, 'task_contribution_id');
    }

    public function opened_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function arbiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbiter_id');
    }
}
