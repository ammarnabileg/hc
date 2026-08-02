<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvTemplate extends Model
{
    use HasFactory;

    protected $table = 'cv_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'price_tickets' => 'decimal:2',
        ];
    }
}
