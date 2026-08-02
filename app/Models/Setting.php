<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_owner_only' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }
}
