<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdAudience extends Model
{
    use HasFactory;

    protected $table = 'ad_audiences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_built_at' => 'datetime',
            'rule' => 'array',
        ];
    }
}
