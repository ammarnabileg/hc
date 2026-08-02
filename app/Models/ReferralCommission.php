<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عمولة ريفيرال مسجَّلة على حركة شحن بعينها — سطر لكلّ حركة ولا يتكرّر (19.3) */
class ReferralCommission extends Model
{
    use HasFactory;

    protected $table = 'referral_commissions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'base_usd' => 'decimal:2',
            'percent' => 'decimal:2',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function source_transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }
}
