<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allowed_scopes' => 'array',
            'is_owner_only' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }
}
