<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفّ يُثبت تسليم منشورٍ لمستخدمٍ على قناةٍ بعينها (12.6-أ).
 *
 * وجوده هو ما يجعل الإرسال **لا يُعيد نفسه**: الفهرس الفريد
 * (منشور × مستخدم × قناة) يمنع رسالةً ثانية مهما أُعيد تشغيل الجدولة.
 */
class AnnouncementDelivery extends Model
{
    protected $table = 'announcement_deliveries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'deferred_until' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
