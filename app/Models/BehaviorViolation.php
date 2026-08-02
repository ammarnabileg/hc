<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BehaviorViolation extends Model
{
    use HasFactory;

    protected $table = 'behavior_violations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_higher_approval' => 'boolean',
        ];
    }
}
