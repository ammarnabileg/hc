<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'courses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'forced_order' => 'boolean',
            'is_free' => 'boolean',
            'is_indexable' => 'boolean',
            'price_coins' => 'decimal:2',
            'published_at' => 'datetime',
            'rating_enabled' => 'boolean',
            'scheduled_at' => 'datetime',
        ];
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
