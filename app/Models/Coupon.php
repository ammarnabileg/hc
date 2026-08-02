<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'value' => 'decimal:2',
        ];
    }
}
