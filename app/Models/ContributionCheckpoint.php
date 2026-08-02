<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'contribution_checkpoints';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'response_due_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function task_contribution(): BelongsTo
    {
        return $this->belongsTo(TaskContribution::class, 'task_contribution_id');
    }
}
