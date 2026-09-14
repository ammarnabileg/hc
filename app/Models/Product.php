<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
            'price_xp' => 'decimal:2',
        ];
    }

    public function product_category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * «المالكون»: صفوف `library_entitlements` التي تشير لهذا المنتج (24.3 — المكتبة
     * الرقميّة). نفس الطريق الذي يمنح به `PurchaseService::grant()` الملكيّة
     * (`itemable_type` = FQCN المنتج) — فالعدّ يطابق ما يفتح فعلًا في «مكتبتي».
     */
    public function entitlements(): MorphMany
    {
        return $this->morphMany(LibraryEntitlement::class, 'itemable');
    }

    /**
     * حجم الملفّ المحميّ بالبايت — يُحسَب حيًّا من القرص لا من عمود مخزَّن، فلا
     * يمكن أن ينحرف عن الملفّ الفعليّ بعد استبداله (20.3 · 20.5).
     */
    public function fileSizeBytes(): ?int
    {
        if (! $this->file_path) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($this->file_path) ? $disk->size($this->file_path) : null;
    }

    /**
     * ⭐ صفحة هبوط **مستقلّة** (12.2.3 `landing_pages`) — أوّل مرّة يصير فيها
     * لمنتج متجرٍ مستقلٍّ صفحة هبوط أصلًا (كانت الميزة محصورة في البندل وحده).
     */
    public function landingPage(): MorphOne
    {
        return $this->morphOne(LandingPage::class, 'landingable');
    }
}
