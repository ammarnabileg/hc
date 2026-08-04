<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * البندل (18): كيانٌ بسعر **واحد مستقلّ** يجمع مسارات وتدريبات وملفّات ومنتجات،
 * وله **لاندنج بيدج** بحقولها المستقلّة عن الاسم الإداريّ.
 *
 * ⚠️ `original_value` **ليس حقلًا يكتبه أحد**: 24 يصف القيمة الإجماليّة بأنّها
 * «محسوبة تلقائيًّا، **للقراءة**»، فالعمود مرآةٌ يزامنها الخادم من
 * `PricingService::bundleItemsValue()` بعد كلّ تغيير في العناصر — ولا يُقرأ في
 * العرض أصلًا. راجع `StoreAdminController::syncBundleValue()`.
 */
class Bundle extends Model
{
    use HasFactory;

    protected $table = 'bundles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:2',
            'price_coins' => 'decimal:2',
            // ⭐ override نصوص اللاندنج وحالات سكشناتها — والمفتاح الغائب = وراثةٌ حيّة
            'landing_texts' => 'array',
            'landing_sections' => 'array',
            'landing_outcomes' => 'array',
            'landing_fit_for' => 'array',
            'landing_not_fit_for' => 'array',
            'landing_faq' => 'array',
            'show_anchor_strikethrough' => 'bool',
            'show_total_value' => 'bool',
            'is_indexable' => 'bool',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'purchase_limit' => 'int',
        ];
    }

    /** @return HasMany<BundleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id')->orderBy('sort_order');
    }
}
