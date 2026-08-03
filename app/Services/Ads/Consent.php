<?php

namespace App\Services\Ads;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * ⭐ الموافقة على التتبّع (21.3-د) — **شرطٌ لازم لا تفصيلة واجهة**.
 *
 * القاعدة الحاكمة: **الرفض يوقف البكسل وأحداث الخادم فعليًّا لا شكليًّا**.
 * ولذلك كلّ نداء إرسالٍ — من المتصفّح أو من الخادم — يمرّ من هنا أوّلًا،
 * ولا يُرسَل شيء إلّا بعد موافقةٍ صريحة على غرضٍ بعينه.
 */
class Consent
{
    /** الأغراض المعتمَدة — قائمة مقفولة، و«التخصيص» يختار منها */
    public const PURPOSES = ['ads', 'analytics'];

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const CUSTOM = 'custom';

    /** ⭐ مفتاح واحد يوقف التتبّع كلّه — لا البانر وحده (21.3-و) */
    public function trackingEnabled(): bool
    {
        return (bool) setting('ads.tracking.enabled', false);
    }

    public function rememberDays(): int
    {
        return (int) setting('ads.consent.remember_days', 180);
    }

    /** اختيار المستخدم: من سجلّه إن كان داخلًا، وإلّا من الكوكي */
    public function choice(?User $user = null, ?Request $request = null): ?string
    {
        $request ??= request();
        $user ??= $request?->user();

        $stored = $user?->tracking_consent;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $cookie = $request?->cookie('tracking_consent');

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }

    /** الأغراض المسموحة عند اختيار «تخصيص» */
    public function scopes(?User $user = null, ?Request $request = null): array
    {
        $request ??= request();
        $user ??= $request?->user();

        $stored = $user?->tracking_scopes;

        if (! is_array($stored)) {
            $raw = $request?->cookie('tracking_scopes');
            $stored = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }

        return array_values(array_intersect(
            array_map(fn ($v) => (string) $v, (array) $stored),
            self::PURPOSES,
        ));
    }

    /**
     * هل يُسمح بغرضٍ بعينه؟
     * — التتبّع مطفأ كلّيًّا ⟵ لا.
     * — بلا اختيار ⟵ لا (الصمت ليس موافقة).
     * — رفض ⟵ لا.
     * — قبول ⟵ نعم.
     * — تخصيص ⟵ فقط ما اختاره صراحةً.
     */
    public function allows(string $purpose, ?User $user = null, ?Request $request = null): bool
    {
        if (! $this->trackingEnabled()) {
            return false;
        }

        return match ($this->choice($user, $request)) {
            self::ACCEPTED => true,
            self::CUSTOM => in_array($purpose, $this->scopes($user, $request), true),
            default => false,
        };
    }

    /** الاختصار الأكثر استعمالًا: هل نُشغّل البكسل وأحداث الخادم؟ */
    public function allowsAds(?User $user = null, ?Request $request = null): bool
    {
        return $this->allows('ads', $user, $request);
    }

    /**
     * ⭐ **القياس الداخليّ** — الغرض الثاني في `PURPOSES`، والبانر يعرضه باسمه
     * («قياس داخليّ لتحسين المنصّة»)، وهو الغرض الحاكم لـ**21.2-ح**:
     *
     *   «**ح) القياس** — ⭐ UTM موحّد على كلّ رابط تولّده المنصّة …
     *    ⭐ **لوحة مصادر الاكتساب** في الإحصائيّات: المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء.»
     *
     * ⚠️ ولماذا اختصارٌ مستقلّ؟ لأنّ `analytics` كان غرضًا **بلا قارئ واحد** في
     *    المشروع كلّه: المنفَّذ كان `allowsAds()` وحده. فمن اختار «قياس داخليّ»
     *    وحده لم يكن يُقاس له شيء، **والقياس الداخليّ كان رهينةَ موافقة الإعلان**
     *    — وهذا يقلب «تخصيص» إلى زينة، وقد نصّ 21.3-د على أنّه خيارٌ حقيقيّ.
     *
     * ⛔ والعكس لازم: من رفض القياس **لا يُقاس ولو وافق على الإعلان**، لأنّ
     *    `allows()` في وضع «تخصيص» لا يعطي إلّا ما اختاره صراحةً.
     */
    public function allowsAnalytics(?User $user = null, ?Request $request = null): bool
    {
        return $this->allows('analytics', $user, $request);
    }

    /** هل نعرض البانر أصلًا؟ لا نُزعج مَن اختار، ولا نعرضه والتتبّع مطفأ (2.9) */
    public function shouldAsk(?User $user = null, ?Request $request = null): bool
    {
        return $this->trackingEnabled() && $this->choice($user, $request) === null;
    }
}
