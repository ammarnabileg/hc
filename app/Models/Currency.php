<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'currencies';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_cumulative' => 'boolean',
            'is_spendable' => 'boolean',
            'max_value' => 'decimal:2',
            'min_value' => 'decimal:2',
            'resets_monthly' => 'boolean',
        ];
    }
}
