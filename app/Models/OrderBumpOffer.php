<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** عرض Order-bump (17) — عنصر يُقترَح مع عنصرٍ آخر وقت الشراء بسعرٍ خاصّ */
class OrderBumpOffer extends Model
{
    use HasFactory;

    protected $table = 'order_bump_offers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_coins' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
