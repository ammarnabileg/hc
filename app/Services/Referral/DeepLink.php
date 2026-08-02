<?php

namespace App\Services\Referral;

use App\Models\Event;
use Illuminate\Support\Facades\Route;

/**
 * ⭐ رابط دعوة **لكلّ محتوى** لا رابطًا عامًّا واحدًا (21.1-ج):
 * «ادعُ صديقك لهذه الفعاليّة تحديدًا» — ويُفتَح على **نفس الصفحة** بعد التسجيل.
 */
class DeepLink
{
    /** الأنواع المسموحة فقط — فلا يتحوّل الرابط إلى تحويل مفتوح لأيّ عنوان */
    public const TYPES = ['event'];

    public function isSupported(?string $type): bool
    {
        return $type !== null && in_array($type, self::TYPES, true);
    }

    /** عنوان الهبوط بعد التسجيل — أو null إن كان المحتوى اتشال */
    public function url(?string $type, int|string|null $id): ?string
    {
        if (! $this->isSupported($type) || $id === null) {
            return null;
        }

        return match ($type) {
            'event' => $this->eventUrl((int) $id),
            default => null,
        };
    }

    public function label(?string $type, int|string|null $id): ?string
    {
        if ($type !== 'event' || $id === null) {
            return null;
        }

        return Event::query()->whereKey((int) $id)->value('title_ar');
    }

    private function eventUrl(int $id): ?string
    {
        $event = Event::query()->find($id);

        if (! $event || ! Route::has('events.show')) {
            return null;
        }

        return route('events.show', $event->slug);
    }
}
