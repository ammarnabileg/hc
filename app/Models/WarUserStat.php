<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سجلّ المحارب: فوز/خسارة + سلسلة الخسارات + دقائق التركيز (15.1 · 15.3). */
class WarUserStat extends Model
{
    protected $table = 'war_user_stats';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
