<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * **تشيك-إن QR الديناميكيّ** (13.3 · 12.11 · 24.3).
 *
 * النصّ الحاكم حرفيًّا:
 *  • 13.3 — «**أوفلاين:** خريطة + اتجاهات + تفاصيل المكان + **تشيك-إن بـ QR**».
 *  • 13.3 — «(وللأوفلاين يتوفّر **تشيك-إن QR** كذلك.)»
 *  • 12.11 — «وللأوفلاين **QR ديناميكيّ للتشيك-إن** يمنع استخدام كود شخص لآخر».
 *  • 24.3 (بلوك إعدادات الفعاليّات) — «**ثوانٍ تجديد QR التشيك-إن = 30ث** + Toggle».
 *
 * ولذلك ثلاثة قيود مشتقّة من النصّ لا من الظنّ:
 *  1. **ديناميكيّ:** الرمز يتجدّد كلّ N ثانية (الافتراضيّ 30 كما نصّ 24.3)،
 *     فلقطةُ شاشةٍ تُرسَل لصديقٍ تموت قبل أن تصل.
 *  2. **مربوطٌ بالشخص:** الحمولة تحمل **رقم تسجيله هو**، فلا يفتح تسجيل غيره —
 *     وهو عين «يمنع استخدام كود شخص لآخر».
 *  3. **موقَّعٌ خادميًّا:** توقيع HMAC بمفتاح التطبيق، فالرمز المُلفَّق يُرَدّ
 *     ولا يُقبَل حتى لو صحّ شكلُه.
 *
 * ⚠️ **وما يسكت عنه النصّ فلا نخترعه:** الدستور يقول «QR» ولا يحدّد **مَن يمسح
 * مَن**. والمبنيّ هنا: **المتدرّب يعرض** رمزه المتجدّد و**المنظِّم يمسحه** —
 * لأنّ هذا وحده ما يحقّق «يمنع استخدام كود شخص لآخر» (رمزٌ ثابتٌ على باب القاعة
 * يمسحه الجميع لا يمنع شيئًا). وكذلك يسكت عن **آليّة القراءة**، والمبنيّ أنّ
 * الحمولة **رابطٌ كامل** تفتحه كاميرا أيّ هاتف بنفسها — فلا مكتبةَ قراءةٍ
 * خارجيّة ولا أصلَ من شبكة (قاعدة المشروع)، ومعها حقلُ لصقٍ يدويّ للطوارئ.
 */
class CheckinQr
{
    /** الرمز صالحٌ · مُنتهٍ · مُلفَّق · لفعاليّةٍ أخرى — أسبابٌ مفاتيح لا جُملًا */
    public const OK = 'ok';

    public const MALFORMED = 'malformed';

    public const EXPIRED = 'expired';

    public const FORGED = 'forged';

    public const FOREIGN = 'foreign';

    /** ثوانٍ تجديد الرمز — نصّ 24.3: «ثوانٍ تجديد QR التشيك-إن = 30ث» */
    public function refreshSeconds(): int
    {
        return max(5, (int) setting('events.checkin.qr_refresh_seconds', 30));
    }

    /**
     * كم نافذةً منقضية نقبلها بعد الحاليّة؟ المسح يقع بعد ثوانٍ من التقاط
     * الصورة، فرفضُ النافذة السابقة يعني رفضَ ماسحٍ صادقٍ على الحدّ تمامًا.
     */
    public function graceWindows(): int
    {
        return max(0, (int) setting('events.checkin.qr_grace_windows', 1));
    }

    /**
     * ⭐ Toggle الـQR (24.3). و**الأوفلاين والهجين وحدهما** — لأنّ النصّ يعلّقه
     * بالأوفلاين صراحةً، والهجين «الاثنان» بنصّ 12.11 فيرث حضورَه الحضوريّ.
     */
    public function enabled(Event $event): bool
    {
        return (bool) setting('events.checkin.qr_enabled', true)
            && in_array($event->mode, (array) setting('events.checkin.qr_modes', ['offline', 'hybrid']), true);
    }

    /** النافذة الزمنيّة الحاليّة — أساس «الديناميكيّ» كلّه */
    public function window(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), $this->refreshSeconds());
    }

    /** لحظة انتهاء الرمز المعروض الآن — يعرضها القالب كعدٍّ ظاهر للمستخدم */
    public function expiresAt(?int $timestamp = null): CarbonImmutable
    {
        $seconds = $this->refreshSeconds();

        return CarbonImmutable::createFromTimestamp(($this->window($timestamp) + 1) * $seconds);
    }

    /** الرمز: تسجيل · نافذة · توقيع — والتوقيع وحده يمنع التلفيق */
    public function token(EventRegistration $registration, ?int $window = null): string
    {
        $window ??= $this->window();
        $id = (int) $registration->id;

        return $id.'-'.$window.'-'.$this->signature((int) $registration->event_id, $id, $window);
    }

    /** الحمولة المرسومة داخل الرمز: رابطٌ كامل تفتحه كاميرا الهاتف بنفسها */
    public function payload(EventRegistration $registration, ?int $window = null): string
    {
        return URL::route('admin.events.scan', ['token' => $this->token($registration, $window)]);
    }

    /** الرمز مرسومًا SVG بأيدينا — بلا مكتبة ولا صورة من شبكة */
    public function svg(EventRegistration $registration): string
    {
        return QrMatrix::svg(
            $this->payload($registration),
            max(1, (int) setting('events.checkin.qr_box_px', 5)),
            max(0, (int) setting('events.checkin.qr_quiet_zone', 4)),
            (string) setting('events.checkin.qr_dark', '#0b1512'),
            (string) setting('events.checkin.qr_light', '#ffffff'),
            (string) setting('events.checkin.qr_alt', 'رمز تشيك-إن الحضور'),
        );
    }

    /**
     * فكّ الرمز والتحقّق منه — والنتيجة **مفتاح سبب** لا جملةً، فالجملة تُقرأ
     * من الإعدادات في طبقة العرض (2.13).
     *
     * @return array{reason: string, registration: ?EventRegistration}
     */
    public function resolve(string $token, ?Event $event = null): array
    {
        $parts = explode('-', trim($token));

        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return ['reason' => self::MALFORMED, 'registration' => null];
        }

        [$id, $window, $signature] = [(int) $parts[0], (int) $parts[1], $parts[2]];

        $registration = EventRegistration::query()->with(['event', 'user'])->find($id);

        if (! $registration) {
            return ['reason' => self::MALFORMED, 'registration' => null];
        }

        // التوقيع قبل الزمن: الرمز المُلفَّق يُرَدّ ولو كانت نافذته حيّة
        if (! hash_equals($this->signature((int) $registration->event_id, $id, $window), $signature)) {
            return ['reason' => self::FORGED, 'registration' => null];
        }

        $current = $this->window();

        if ($window > $current || $current - $window > $this->graceWindows()) {
            return ['reason' => self::EXPIRED, 'registration' => $registration];
        }

        if ($event && (int) $registration->event_id !== (int) $event->id) {
            return ['reason' => self::FOREIGN, 'registration' => $registration];
        }

        return ['reason' => self::OK, 'registration' => $registration];
    }

    /** رسالة السبب — نصٌّ من الإعدادات لكلّ حالة (2.13 · 2.17: ماذا حدث وماذا تفعل) */
    public function reasonMessage(string $reason): string
    {
        return (string) setting('events.checkin.qr_msg_'.$reason, (string) setting('events.checkin.qr_msg_malformed', ''));
    }

    /** توقيعٌ خادميّ بمفتاح التطبيق — مقصوصٌ لأنّ الحمولة تُرسَم في مربّعات */
    private function signature(int $eventId, int $registrationId, int $window): string
    {
        $length = max(8, (int) setting('events.checkin.qr_signature_length', 16));

        return substr(
            hash_hmac('sha256', $eventId.'|'.$registrationId.'|'.$window, (string) config('app.key')),
            0,
            $length,
        );
    }
}
