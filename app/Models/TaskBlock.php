<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskBlock extends Model
{
    use HasFactory;

    protected $table = 'task_blocks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resume_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function blocking_task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'blocking_task_id');
    }

    public function approved_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
