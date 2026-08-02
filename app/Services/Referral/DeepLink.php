<?php

namespace App\Services\Referral;

use App\Models\Course;
use App\Models\Event;
use App\Models\LearningPath;
use App\Services\Growth\UtmBuilder;
use Illuminate\Support\Facades\Route;

/**
 * ⭐ رابط دعوة **لكلّ محتوى** لا رابطًا عامًّا واحدًا (21.1-ج):
 * «ادعُ صديقك **لهذا التدريب تحديدًا**» — ويُفتَح على **نفس الصفحة** بعد التسجيل.
 *
 * ولذلك الأنواع المدعومة ليست الفعاليّة وحدها: **التدريب والمسار** كذلك،
 * لأنّهما المحتوى الذي يُدعى إليه أصلًا. والوجهة صفحةٌ **عامّة** يفتحها الزائر
 * قبل أن يسجّل — وإلّا كان الرابط بابًا مغلقًا.
 */
class DeepLink
{
    /** الأنواع المسموحة فقط — فلا يتحوّل الرابط إلى تحويل مفتوح لأيّ عنوان */
    public const TYPES = ['event', 'course', 'path'];

    public function __construct(private readonly UtmBuilder $utm) {}

    public function isSupported(?string $type): bool
    {
        return $type !== null && in_array($type, self::TYPES, true);
    }

    /** التسميات العربيّة للأنواع — تُعرَض في شاشة الدعوات */
    public static function typeLabels(): array
    {
        return ['event' => 'فعاليّة', 'course' => 'تدريب', 'path' => 'مسار'];
    }

    /** عنوان الهبوط بعد التسجيل — أو null إن كان المحتوى اتشال */
    public function url(?string $type, int|string|null $id): ?string
    {
        if (! $this->isSupported($type) || $id === null) {
            return null;
        }

        $url = match ($type) {
            'event' => $this->eventUrl((int) $id),
            'course' => $this->courseUrl((int) $id),
            'path' => $this->pathUrl((int) $id),
            default => null,
        };

        // ⭐ UTM موحّد على كلّ رابط تولّده المنصّة (21.2-ح)
        return $url ? $this->utm->tag($url, 'invite', 'deep_link', $type) : null;
    }

    public function label(?string $type, int|string|null $id): ?string
    {
        if (! $this->isSupported($type) || $id === null) {
            return null;
        }

        return match ($type) {
            'event' => Event::query()->whereKey((int) $id)->value('title_ar'),
            'course' => Course::query()->whereKey((int) $id)->value('name_ar'),
            'path' => LearningPath::query()->whereKey((int) $id)->value('name_ar'),
            default => null,
        };
    }

    private function eventUrl(int $id): ?string
    {
        $event = Event::query()->find($id);

        if (! $event || ! Route::has('events.show')) {
            return null;
        }

        return route('events.show', $event->slug);
    }

    /** صفحة التدريب العامّة — يفتحها الزائر ويعاين أوّل درس قبل التسجيل (21.1-أ) */
    private function courseUrl(int $id): ?string
    {
        $course = Course::query()->find($id);

        if (! $course || ! Route::has('store.product')) {
            return null;
        }

        return route('store.product', ['type' => 'course', 'slug' => $course->slug]);
    }

    private function pathUrl(int $id): ?string
    {
        $path = LearningPath::query()->find($id);

        if (! $path || ! Route::has('store.product')) {
            return null;
        }

        return route('store.product', ['type' => 'path', 'slug' => $path->slug]);
    }
}
