<?php

namespace App\Services\Learning;

use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * كشف دولة المستخدم ومنطقته الزمنيّة تلقائيًّا (الدستور 5).
 *
 * الدستور: «تحديد الدولة **تلقائيًا حسب موقع المستخدم الحالي** — ديناميكي يتبع
 * مكانه دلوقتي، مش دولة ثابتة عند التسجيل»، مع بقاء التعديل اليدويّ ممكنًا.
 *
 * مصدران، بلا أيّ مكتبة أو خدمة خارجيّة:
 *  1) **ترويسة الدولة** من الطبقة الأماميّة (Cloudflare/CDN/Proxy) — أسماء
 *     الترويسات إعداد لا رقم محروق، فتُضبَط حسب استضافة كلّ منصّة.
 *  2) **تلميح المتصفّح** بالمنطقة الزمنيّة (IANA) يصل من نداء موقَّع، ويُتحقَّق
 *     منه على الخادم قبل الحفظ — فالمتصفّح يقترح والخادم يقرّر ويكتب.
 *
 * وفي الحالتين: الكتابة على `auto_timezone` وحده، فاختيار المستخدم اليدويّ
 * (`timezone`) لا يُدهَس أبدًا.
 */
class TimezoneDetector
{
    public function __construct(private readonly UserClock $clock) {}

    /**
     * مزامنة ما نعرفه عن مكان المستخدم الآن.
     *
     * @return bool هل تغيّرت القيمة المخزّنة؟
     */
    public function sync(Request $request, ?User $user): bool
    {
        // التوجل من لوحة الإدارة: منصّة تعمل في دولة واحدة قد لا تريد الكشف أصلًا
        if (! $user || ! setting('availability.detect.enabled', true)) {
            return false;
        }

        $timezone = $this->fromClientHint($request) ?? $this->fromGeoHeaders($request);

        if ($timezone === null || $timezone === $user->auto_timezone) {
            return false;
        }

        $this->remember($user, $timezone);

        return true;
    }

    /** حفظ المنطقة المكتشَفة + دولتها إن أمكن استنتاجها ولم يحدّدها المستخدم */
    public function remember(User $user, string $timezone): void
    {
        if (! $this->clock->isValid($timezone)) {
            return;
        }

        $attributes = ['auto_timezone' => $timezone, 'auto_timezone_at' => now()];

        // الدولة تتبع المكان أيضًا — لكن لا نلمسها إن اختارها المستخدم
        // ولا إن **ثبّتها الأدمن يدويًّا** (12.1-متقدّم-5): التثبيت يعلو الكشف.
        if (! $user->country_id && ! $user->country_locked_at) {
            $country = Country::query()->where('timezone', $timezone)->where('is_active', true)->first();

            if ($country) {
                $attributes['country_id'] = $country->id;
            }
        }

        $user->forceFill($attributes)->saveQuietly();
    }

    /** الاختيار اليدويّ — يعلو الكشف ولا يُلغيه */
    public function setManual(User $user, ?string $timezone): void
    {
        $user->forceFill([
            'timezone' => $timezone !== null && $this->clock->isValid($timezone) ? $timezone : null,
        ])->saveQuietly();
    }

    /** تلميح المتصفّح: `Intl.DateTimeFormat().resolvedOptions().timeZone` */
    public function fromClientHint(Request $request): ?string
    {
        $candidate = (string) $request->input(
            (string) setting('availability.detect.client_field', 'timezone'),
            ''
        );

        return $this->clock->isValid($candidate) ? $candidate : null;
    }

    /** ترويسة الدولة من الطبقة الأماميّة ⟵ توقيت الدولة من جدولها */
    public function fromGeoHeaders(Request $request): ?string
    {
        $headers = (array) setting('availability.detect.country_headers', []);

        foreach ($headers as $header) {
            $iso2 = strtoupper(trim((string) $request->header((string) $header, '')));

            if (strlen($iso2) !== 2) {
                continue;
            }

            $timezone = Country::query()->where('iso2', $iso2)->value('timezone');

            if (is_string($timezone) && $this->clock->isValid($timezone)) {
                return $timezone;
            }
        }

        return null;
    }
}
