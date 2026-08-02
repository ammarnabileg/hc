<?php

namespace App\Services\Learning;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * ساعة المستخدم (الدستور 5).
 *
 * لماذا خدمة مستقلّة؟ لأنّ الدستور يقول صراحةً إنّ فتح/غلق التدريبات يعتمد على
 * **التوقيت المحلّيّ للمستخدم** لا توقيت الخادم؛ فلو تفرّق حساب «الآن» على
 * الشاشات لاختلف معنى «مفتوح» بين شاشة وأخرى لنفس المستخدم في نفس اللحظة.
 *
 * ترتيب الأولويّة — والاختيار اليدويّ يعلو الكشف التلقائيّ دائمًا:
 *  1) `users.timezone`      اختيار المستخدم بنفسه.
 *  2) `users.auto_timezone` آخر منطقة مكتشَفة (تتبع مكانه الآن).
 *  3) توقيت دولته من `countries.timezone`.
 *  4) `setting('system.timezone')` — آخر ملجأ لا أوّل خيار.
 */
class UserClock
{
    public function timezoneFor(?User $user): string
    {
        $candidates = [
            $user?->timezone,
            $user?->auto_timezone,
            $user?->country?->timezone,
            setting('system.timezone', config('app.timezone')),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->isValid($candidate)) {
                return $candidate;
            }
        }

        return (string) config('app.timezone');
    }

    /** «الآن» بساعة المستخدم — نقطة الحقيقة الوحيدة لكلّ قرارات الإتاحة */
    public function now(?User $user, ?CarbonInterface $at = null): CarbonImmutable
    {
        return CarbonImmutable::parse($at ?? now())->setTimezone($this->timezoneFor($user));
    }

    /** تحويل أيّ لحظة إلى ساعة المستخدم — للعرض لا للحساب */
    public function toUser(CarbonInterface $at, ?User $user): CarbonImmutable
    {
        return CarbonImmutable::parse($at)->setTimezone($this->timezoneFor($user));
    }

    /** مصدر التوقيت المعروض للمستخدم: يدويّ · تلقائيّ · دولته · المنصّة */
    public function sourceFor(?User $user): string
    {
        return match (true) {
            is_string($user?->timezone) && $this->isValid($user->timezone) => 'manual',
            is_string($user?->auto_timezone) && $this->isValid($user->auto_timezone) => 'auto',
            is_string($user?->country?->timezone) && $this->isValid($user->country->timezone) => 'country',
            default => 'platform',
        };
    }

    /** الفارق عن UTC نصًّا مقروءًا — يُطمئن المستخدم أنّ الساعة ساعته */
    public function offsetLabel(?User $user): string
    {
        $now = $this->now($user);
        $minutes = (int) round($now->utcOffset());
        $sign = $minutes < 0 ? '−' : '+';
        $minutes = abs($minutes);

        return 'UTC'.$sign.str_pad((string) intdiv($minutes, 60), 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    public function isValid(string $timezone): bool
    {
        return $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * قائمة المناطق للاختيار اليدويّ: المفضّلة أوّلًا ثمّ الباقي.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        $preferred = (array) setting('availability.timezone.preferred', []);
        $preferred = array_values(array_filter(
            array_map(fn ($tz) => is_string($tz) ? $tz : null, $preferred),
            fn (?string $tz) => $tz !== null && $this->isValid($tz),
        ));

        return array_values(array_unique([...$preferred, ...timezone_identifiers_list()]));
    }
}
