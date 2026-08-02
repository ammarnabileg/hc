<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * الإتاحة والجدولة والتوقيت (الدستور 5).
 *
 * ثلاث طبقات تُقرَّر **كلّها في الخادم** — الواجهة تعرض النتيجة ولا تصنعها:
 *  1) **حالة النشر والجدولة**: غير المنشور أو المجدول لموعدٍ قادم لا يُفتَح.
 *  2) **فترات الإتاحة المتعدّدة** (`course_availability_periods`): للتدريب الواحد
 *     فترات كثيرة (1→7 يناير، 1→7 مارس…) ولا يُوصَل إليه إلّا أثناء إحداها.
 *  3) **أوقات التشغيل اليوميّة** (`courses.daily_open_at/daily_close_at`): خارج
 *     الساعات اليوميّة التدريب مقفول **ولو كانت الفترة سارية**.
 *
 * ⭐ والقاعدة الحاكمة: كلّ هذه المقارنات تجري **بتوقيت المستخدم المحلّيّ** لا
 * بتوقيت الخادم — فتدريب «5→7 ص» يظهر لكلّ مستخدم حسب ساعته هو.
 *
 * ولأنّ رسالة الخطأ في هذه المنصّة = **ماذا حدث + متى يفتح** (2.17)، تُرجِع
 * الخدمة مع كلّ إغلاق لحظةَ الفتح القادمة بساعة المستخدم وثوانيَها للعدّاد.
 */
class AvailabilityService
{
    public function __construct(private readonly UserClock $clock) {}

    /**
     * حالة إتاحة التدريب لهذا المستخدم.
     *
     * @return array{
     *     open:bool, state:string, reason:?string,
     *     opens_at:?CarbonImmutable, opens_in:?int, closes_at:?CarbonImmutable,
     *     timezone:string, offset:string, daily:?array{open:string,close:string},
     *     periods:Collection<int, CourseAvailabilityPeriod>
     * }
     */
    public function forCourse(Course $course, ?Enrollment $enrollment = null, ?User $user = null): array
    {
        $now = $this->clock->now($user);
        $base = [
            'opens_at' => null,
            'opens_in' => null,
            'closes_at' => null,
            'timezone' => $this->clock->timezoneFor($user),
            'offset' => $this->clock->offsetLabel($user),
            'daily' => $this->dailyWindow($course),
            'periods' => $this->periods($course),
        ];

        // لم يُنشَر بعد — رماديّ لا أحمر: «غير نشط» وليست حالةً سيّئة (2.16)
        if ($course->status !== setting('learning.course.published_status', 'published')) {
            return $base + $this->closed('idle', setting('learning.lock.unpublished_reason'));
        }

        $scheduled = $course->scheduled_at ?? $course->published_at;

        if ($scheduled instanceof Carbon && $scheduled->isFuture()) {
            $at = $this->clock->toUser($scheduled, $user);

            return array_merge($base, [
                'opens_at' => $at,
                'opens_in' => max(0, $now->diffInSeconds($at, absolute: false)),
            ], $this->closed(
                'idle',
                setting('learning.lock.scheduled_reason').' — '.$this->stamp($at),
            ));
        }

        if ($enrollment && $this->isExpired($enrollment)) {
            return $base + $this->closed('danger', setting('learning.lock.expired_reason'));
        }

        return array_merge($base, $this->scheduleState($course, $user, $now, $base['daily']));
    }

    /**
     * انتهت إتاحة التسجيل: إمّا وُسِم كذلك في الجدول، أو فات موعده النهائيّ
     * دون إكمال. والمكتمل لا ينتهي أبدًا — الوصول للمُنجَز دائم (20).
     */
    public function isExpired(Enrollment $enrollment): bool
    {
        if ($enrollment->status === 'expired') {
            return true;
        }

        if ($enrollment->status === 'completed') {
            return false;
        }

        return $enrollment->deadline_at instanceof Carbon && $enrollment->deadline_at->isPast();
    }

    /** فترات الإتاحة الفعّالة مرتّبةً — تُعرَض للمتدرّب كما يراها الأدمن (5) */
    public function periods(Course $course): Collection
    {
        return CourseAvailabilityPeriod::query()
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * أوقات التشغيل اليوميّة — والنافذة الناقصة طرفًا لا معنى لها فتُهمَل.
     *
     * @return array{open:string,close:string}|null
     */
    public function dailyWindow(Course $course): ?array
    {
        $open = $this->clampTime($course->daily_open_at);
        $close = $this->clampTime($course->daily_close_at);

        return $open !== null && $close !== null ? ['open' => $open, 'close' => $close] : null;
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * الفترات + النافذة اليوميّة معًا، بساعة المستخدم.
     *
     * @param  array{open:string,close:string}|null  $daily
     */
    private function scheduleState(Course $course, ?User $user, CarbonImmutable $now, ?array $daily): array
    {
        $periods = $this->periods($course);

        // بلا فترات وبلا نافذة يوميّة ⟵ لا قيد زمنيًّا على هذا التدريب
        if ($periods->isEmpty() && $daily === null) {
            return ['open' => true, 'state' => 'ok', 'reason' => null];
        }

        // مفتوح الآن؟ نفحص نافذة اليوم ونافذة أمس (النافذة قد تعبر منتصف الليل)
        foreach ([$now->subDay(), $now] as $day) {
            $window = $this->windowOf($day, $daily);

            if (! $this->insidePeriods($periods, $day)) {
                continue;
            }

            if ($now->gte($window['open']) && $now->lt($window['close'])) {
                return [
                    'open' => true,
                    'state' => 'ok',
                    'reason' => null,
                    'closes_at' => $window['close'],
                ];
            }
        }

        $next = $this->nextOpening($periods, $daily, $now);

        if ($next === null) {
            // لا فترة قادمة — انتهت إتاحة هذا التدريب فعلًا
            return $this->closed('danger', setting('learning.lock.periods_over_reason'));
        }

        return [
            'open' => false,
            'state' => 'idle',
            'reason' => $this->closedReason($daily, $next),
            'opens_at' => $next,
            'opens_in' => max(0, $now->diffInSeconds($next, absolute: false)),
        ];
    }

    /**
     * لحظة الفتح القادمة بساعة المستخدم — أو null إن لم يبقَ فتحٌ أبدًا.
     *
     * @param  Collection<int, CourseAvailabilityPeriod>  $periods
     * @param  array{open:string,close:string}|null  $daily
     */
    private function nextOpening(Collection $periods, ?array $daily, CarbonImmutable $now): ?CarbonImmutable
    {
        $horizon = (int) setting('availability.lookahead_days', 400);
        $day = $now->startOfDay();

        for ($i = 0; $i <= $horizon; $i++) {
            $candidate = $day->addDays($i);

            if (! $this->insidePeriods($periods, $candidate)) {
                continue;
            }

            $window = $this->windowOf($candidate, $daily);

            if ($window['open']->gt($now)) {
                return $window['open'];
            }
        }

        return null;
    }

    /**
     * نافذة يومٍ بعينه بساعة المستخدم. وبلا نافذة يوميّة يكون اليوم كلّه مفتوحًا،
     * ونافذةٌ تنتهي قبل بدايتها تعني عبور منتصف الليل فتمتدّ لليوم التالي.
     *
     * @param  array{open:string,close:string}|null  $daily
     * @return array{open:CarbonImmutable,close:CarbonImmutable}
     */
    private function windowOf(CarbonImmutable $day, ?array $daily): array
    {
        $start = $day->startOfDay();

        if ($daily === null) {
            return ['open' => $start, 'close' => $start->addDay()];
        }

        $open = $start->addMinutes($this->minutes($daily['open']));
        $close = $start->addMinutes($this->minutes($daily['close']));

        return ['open' => $open, 'close' => $close->lte($open) ? $close->addDay() : $close];
    }

    /** @param  Collection<int, CourseAvailabilityPeriod>  $periods */
    private function insidePeriods(Collection $periods, CarbonImmutable $day): bool
    {
        if ($periods->isEmpty()) {
            return true; // بلا فترات محدَّدة لا قيد بالتواريخ — النافذة اليوميّة وحدها تحكم
        }

        $date = $day->toDateString();

        return $periods->contains(
            fn (CourseAvailabilityPeriod $p) => $p->starts_on->toDateString() <= $date
                && $p->ends_on->toDateString() >= $date
        );
    }

    /** ماذا حدث + متى يفتح — بتوقيت المستخدم دائمًا (2.17) */
    private function closedReason(?array $daily, CarbonImmutable $next): string
    {
        $what = $daily === null
            ? (string) setting('learning.lock.outside_period_reason')
            : str_replace(
                [':from', ':to'],
                [$daily['open'], $daily['close']],
                (string) setting('learning.lock.outside_daily_reason'),
            );

        return $what.' — '.setting('learning.lock.opens_at_prefix').' '.$this->stamp($next);
    }

    /** طابع زمنيّ عربيّ مقروء بساعة المستخدم */
    private function stamp(CarbonImmutable $at): string
    {
        return $at->translatedFormat((string) setting('learning.availability.stamp_format', 'l j F — H:i'));
    }

    /** @return array{open:bool,state:string,reason:?string} */
    private function closed(string $state, ?string $reason): array
    {
        return ['open' => false, 'state' => $state, 'reason' => $reason];
    }

    private function clampTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})/', (string) $value, $m)) {
            return null;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }
}
