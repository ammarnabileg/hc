<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $table = 'referrals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_earned' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'welcome_ticket_granted' => 'boolean',
            'referrer_ticket_granted' => 'boolean',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}
