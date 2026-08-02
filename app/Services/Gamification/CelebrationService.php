<?php

namespace App\Services\Gamification;

use App\Models\CelebrationConsumption;
use App\Models\CelebrationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * نظام الاحتفالات (2.14) — مصدرٌ واحد لا نسخ متعدّدة.
 *
 * القواعد المطبَّقة هنا:
 *  - ثلاثة مستويات لا رابع لها، ولكلّ حدث مستواه من جدول `celebration_events`.
 *  - **مرّة واحدة لكلّ حدث Server-side** عبر `celebration_consumptions` — فلا يتكرّر بإعادة التحميل.
 *  - **حدّ يوميّ لمستوى الذروة** من `setting('celebrations.peak.daily_cap')` كي تبقى الذروة ذروةً.
 *  - الصوت يخضع لتوجل الصوت، والأنيميشن حاضرٌ دائمًا (روح المنصّة).
 */
class CelebrationService
{
    /**
     * تسجيل حدثٍ واستهلاكه مرّةً واحدة.
     *
     * @return array{key:string,tier:int,label:string,message:string,sound:bool}|null
     *                                                                                null = لا احتفال (حدث غير مفعَّل أو مستهلَك من قبل)
     */
    public function fire(User $user, string $eventKey, ?Model $reference = null): ?array
    {
        $event = CelebrationEvent::query()
            ->where('key', $eventKey)
            ->where('is_active', true)
            ->first();

        if (! $event) {
            return null;
        }

        if ($this->alreadyConsumed($user, $event, $reference)) {
            return null;
        }

        $tier = (int) $event->tier;

        // الحدّ اليوميّ للذروة: ما بعد الحدّ ينزل لمستوى «متوسّط» ولا يُلغى
        if ($tier === 3 && $this->peakCountToday($user) >= (int) setting('celebrations.peak.daily_cap', 3)) {
            $tier = 2;
        }

        CelebrationConsumption::create([
            'user_id' => $user->id,
            'celebration_event_id' => $event->id,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'consumed_at' => now(),
        ]);

        return [
            'key' => $event->key,
            'tier' => $tier,
            'label' => $event->label_ar,
            // النداء بالاسم في لحظات الذروة (2.17-أ)
            'message' => $this->message($event, $user),
            'sound' => $tier > 1
                && (bool) $user->sound_enabled
                && (bool) setting('celebrations.sound.enabled', true),
        ];
    }

    /**
     * لا تتراكم: إن وقع أكثر من حدث في اللحظة نفسها يُعرَض الأعلى مستوى فقط.
     *
     * @param  array<int, array{tier:int}|null>  $results
     */
    public function highest(array $results): ?array
    {
        $found = array_values(array_filter($results));

        if ($found === []) {
            return null;
        }

        usort($found, fn ($a, $b) => $b['tier'] <=> $a['tier']);

        return $found[0];
    }

    /** كم احتفال ذروة أُطلِق اليوم لهذا المستخدم */
    public function peakCountToday(User $user): int
    {
        return CelebrationConsumption::query()
            ->where('celebration_consumptions.user_id', $user->id)
            ->whereDate('consumed_at', now()->toDateString())
            ->whereIn('celebration_event_id', CelebrationEvent::query()->where('tier', 3)->select('id'))
            ->count();
    }

    private function alreadyConsumed(User $user, CelebrationEvent $event, ?Model $reference): bool
    {
        return CelebrationConsumption::query()
            ->where('user_id', $user->id)
            ->where('celebration_event_id', $event->id)
            ->when(
                $reference !== null,
                fn ($q) => $q->where('reference_type', $reference->getMorphClass())->where('reference_id', $reference->getKey()),
                fn ($q) => $q->whereNull('reference_id'),
            )
            ->exists();
    }

    private function message(CelebrationEvent $event, User $user): string
    {
        $template = $event->message_ar ?: setting('celebrations.default_message', 'مبروك يا :name — :label 🎉');

        return str_replace([':name', ':label'], [$user->shortName(1), $event->label_ar], $template);
    }
}
