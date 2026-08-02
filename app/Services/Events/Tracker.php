<?php

namespace App\Services\Events;

use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** تتبّع خادميّ خفيف (21.3) — بلا أيّ طرف ثالث وبإزالة تكرار عبر event_uid */
class Tracker
{
    public function record(string $name, ?Model $reference = null, ?int $userId = null): void
    {
        TrackingEvent::create([
            'user_id' => $userId ?? auth()->id(),
            'event' => $name,
            'event_uid' => (string) Str::uuid(),
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'utm_source' => request()->query('utm_source'),
            'utm_medium' => request()->query('utm_medium'),
            'utm_campaign' => request()->query('utm_campaign'),
            'sent_server_side' => true,
        ]);
    }
}
