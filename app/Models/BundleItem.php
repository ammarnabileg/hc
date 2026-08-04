<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BundleItem extends Model
{
    use HasFactory;

    protected $table = 'bundle_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            // null = السعر الطبيعيّ للعنصر، ورقم = Override داخل صفحة البندل وحدها (18)
            'price_coins' => 'decimal:2',
            // ⭐ Toggle «اعرضه كبونص» (24) — والبونص قرارٌ لعنصرٍ بعينه لا وسمٌ للكلّ
            'is_bonus' => 'bool',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
