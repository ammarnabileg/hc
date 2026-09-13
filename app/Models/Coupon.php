<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'value' => 'decimal:2',
        ];
    }

    /**
     * الطلبات التي **فعلًا** استخدمت الكوبون — المدفوعة وحدها (24.3)، لأنّ
     * `used_count` نفسه لا يزيد إلّا عند نجاح الدفع في `PurchaseService::commit()`.
     * طلبٌ فاشل أو معلَّق طبَّق الكوبون في العرض ولم «يستخدمه» بعد.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id')->where('status', 'paid');
    }
}
