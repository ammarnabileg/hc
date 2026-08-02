<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** الاستعداد الحصريّ (15.0) — صفّ واحد لكلّ مستخدم لا أكثر. */
class WarReadiness extends Model
{
    protected $table = 'war_readiness';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ready_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
