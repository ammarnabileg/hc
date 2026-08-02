<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'exceeded_daily_cap' => 'boolean',
            'is_correction' => 'boolean',
            'meta' => 'array',
            'objection_deadline_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function corrects_transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'corrects_transaction_id');
    }
}
