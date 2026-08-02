<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopupOffer extends Model
{
    use HasFactory;

    protected $table = 'topup_offers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bonus_percent' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'pay_amount' => 'decimal:2',
        ];
    }
}
