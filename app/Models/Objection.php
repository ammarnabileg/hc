<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Objection extends Model
{
    use HasFactory;

    protected $table = 'objections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'sla_due_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function current_handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }

    public function correction_transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'correction_transaction_id');
    }
}
