<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Escalation extends Model
{
    use HasFactory;

    protected $table = 'escalations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'auto_settled' => 'boolean',
            'decided_at' => 'datetime',
            'is_top_level' => 'boolean',
            'last_failure_at' => 'datetime',
            'slowdown_penalty_applied' => 'boolean',
            'window_due_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requested_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function current_handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }
}
