<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationStep extends Model
{
    use HasFactory;

    protected $table = 'escalation_steps';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'due_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(Escalation::class, 'escalation_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}
