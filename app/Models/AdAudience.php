<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdAudience extends Model
{
    use HasFactory;

    protected $table = 'ad_audiences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_built_at' => 'datetime',
            'rule' => 'array',
            // شرائح الجمهور (12.13): الثابتة تُجمَّد الآن، والمؤرشفة تبقى ولا تُحذف
            'frozen_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** أعضاء الشريحة **الثابتة** — القائمة المجمَّدة لحظة الحفظ (12.13). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'audience_segment_members')->withTimestamps();
    }
}
