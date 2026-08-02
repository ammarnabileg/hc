<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    use HasFactory;

    protected $table = 'bundles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:2',
            'price_coins' => 'decimal:2',
        ];
    }
}
