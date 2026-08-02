<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_downloadable' => 'boolean',
            'is_indexable' => 'boolean',
            'price_coins' => 'decimal:2',
            'price_tickets' => 'decimal:2',
        ];
    }

    public function product_category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
