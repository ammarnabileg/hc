<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * ⭐ **حذف التصنيف لا يمسّ منتجاته**: `product_category_id` في المنتجات
     * `nullOnDelete` بالمخطّط — فمنتجات التصنيف المحذوف تصير بلا تصنيف
     * لا تُحذَف ولا تُفقَد (24.3 · permissions: `product_categories.delete`).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}
