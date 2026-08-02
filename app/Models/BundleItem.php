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
        // null = السعر الطبيعيّ للعنصر، ورقم = Override داخل صفحة البندل وحدها (18)
        return ['price_coins' => 'decimal:2'];
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
