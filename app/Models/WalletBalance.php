<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    use HasFactory;

    protected $table = 'wallet_balances';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'last_reset_at' => 'datetime',
            'lifetime_earned' => 'decimal:2',
            'lifetime_spent' => 'decimal:2',
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
}
