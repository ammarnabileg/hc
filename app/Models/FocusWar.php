<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تحدّي التركيز (15.3). */
class FocusWar extends Model
{
    protected $table = 'focus_wars';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'create_cost' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FocusWarMember::class);
    }

    /** المنضمّون غير صاحب التحدّي — أكوام الأفاتار تُبنى منهم (15.3) */
    public function joiners(): HasMany
    {
        return $this->members()->whereColumn('user_id', '!=', 'focus_wars.owner_id');
    }
}
