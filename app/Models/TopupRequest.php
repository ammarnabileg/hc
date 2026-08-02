<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupRequest extends Model
{
    use HasFactory;

    protected $table = 'topup_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credited_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'transferred_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function topup_offer(): BelongsTo
    {
        return $this->belongsTo(TopupOffer::class, 'topup_offer_id');
    }

    public function transfer_method(): BelongsTo
    {
        return $this->belongsTo(TransferMethod::class, 'transfer_method_id');
    }

    public function reviewed_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
