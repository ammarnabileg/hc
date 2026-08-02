<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** عضويّة في تحدّي تركيز — والدقائق تبقى للعضو حتى لو أُلغي التحدّي (15.3). */
class FocusWarMember extends Model
{
    protected $table = 'focus_war_members';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'paid' => 'decimal:2',
        ];
    }

    public function focusWar(): BelongsTo
    {
        return $this->belongsTo(FocusWar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** هل انقضى وقت التحدّي عليه؟ — مَن انقضى وقته لا يُستَرجَع له شيء (15.3) */
    public function isDue(): bool
    {
        return now()->greaterThanOrEqualTo($this->ends_at);
    }
}
