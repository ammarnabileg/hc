<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorTransaction extends Model
{
    use HasFactory;

    protected $table = 'behavior_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'value' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function granted_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function behavior_violation(): BelongsTo
    {
        return $this->belongsTo(BehaviorViolation::class, 'behavior_violation_id');
    }

    public function approved_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
