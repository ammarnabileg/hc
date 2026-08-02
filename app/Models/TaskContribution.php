<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskContribution extends Model
{
    use HasFactory;

    protected $table = 'task_contributions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'auto_approved' => 'boolean',
            'delivered_at' => 'datetime',
            'held_amount' => 'decimal:2',
            'internal_deadline_at' => 'datetime',
            'invited_at' => 'datetime',
            'owner_review_due_at' => 'datetime',
            'responded_at' => 'datetime',
            'vxp_value' => 'decimal:2',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }

    public function invited_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
