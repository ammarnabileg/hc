<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تذكرة مكافأة السلسلة (الدستور 7.2 · 7.1-2): تُصرَف مرّةً واحدة لكلّ دورة
 * مكتملة — والسجلّ هو ما يمنع تكرار الصرف، لا إخفاء الزرّ في الواجهة.
 */
class StreakReward extends Model
{
    use HasFactory;

    protected $table = 'streak_rewards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'tickets' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
