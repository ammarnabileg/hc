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

    /**
     * ⭐ **نقل موافقة الزائر إلى حسابه لحظة التسجيل** (21.3-د).
     *
     * النصّ الحاكم حرفيًّا (21.3-د): «**بانر موافقة على التتبّع (Consent)** عند
     * **أوّل زيارة**، بخيارات واضحة: **قبول · رفض · تخصيص**» و«**الرفض يوقف
     * البكسل وأحداث الخادم لهذا المستخدم فعليًّا** — لا شكليًّا» و«**حقّ السحب
     * في أيّ وقت** من إعدادات الخصوصيّة والأمان».
     *
     * والعلّة: البانر يُعرَض **عند أوّل زيارة** أي على الزائر، فاختياره لا يعيش
     * إلّا في الكوكي. ثمّ يُنشَأ الحساب بـ`tracking_consent = NULL` — و`choice()`
     * ينجو بالكوكي على **نفس المتصفّح** وحده. فمن رفض على هاتفه ثمّ دخل من
     * حاسوبه صار **بلا اختيارٍ** فيُسأل من جديد، ومن قبِل صار كأنّه لم يقبل.
     * والقرار الذي لا يسافر مع صاحبه ليس قرارًا — و«الرفض يوقف … فعليًّا» يوجب
     * أن يلاحقه الرفض إلى كلّ جهاز، وهو نفسه شرط «السحب في أيّ وقت».
     *
     * ⛔ **ولا يُعكَس الاتّجاه أبدًا:**
     *  - **بلا كوكي ⟵ لا يُكتَب شيء.** الصمت ليس موافقة، والعمود يبقى `NULL`
     *    فتقرؤه `allows()` في فرع `default` = **لا**. أي أنّ غياب الموافقة يبقى
     *    رفضًا عمليًّا، ولا يُفترَض قبولٌ لمن لم يوافق.
     *  - **ولا يُدهَس اختيارٌ محفوظ على الحساب** — سجلّ الحساب أحدث من كوكي
     *    متصفّحٍ عابر، ودهسُه بكوكي قديمة يُبطِل سحبًا وقع فعلًا.
     *  - و**الأغراض تُنقَل معه**: «تخصيص» بلا أغراضٍ لا معنى له، وقد قرّر
     *    `storeConsent` أنّه رفض — فتُنقَل السلسلة كما هي بلا إعادة تفسير.
     *
     * @return bool هل نُقِلت موافقةٌ فعلًا؟
     */
    public function adopt(User $user, ?Request $request = null): bool
    {
        $request ??= request();

        // اختيارٌ محفوظ على الحساب لا يُدهَس بكوكي
        $stored = $user->tracking_consent;

        if (is_string($stored) && $stored !== '') {
            return false;
        }

        $cookie = $request?->cookie('tracking_consent');

        if (! is_string($cookie) || ! in_array($cookie, [self::ACCEPTED, self::REJECTED, self::CUSTOM], true)) {
            return false;
        }

        $raw = $request?->cookie('tracking_scopes');
        $scopes = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        $scopes = $cookie === self::CUSTOM
            ? array_values(array_intersect(array_map(fn ($v) => (string) $v, (array) $scopes), self::PURPOSES))
            : [];

        // «تخصيص» بلا غرضٍ واحد = رفض — نفس قرار `storeConsent`، فلا تفسيران للحالة الواحدة
        $choice = ($cookie === self::CUSTOM && $scopes === []) ? self::REJECTED : $cookie;

        // `saveQuietly` — نقلُ قرارٍ سابق لا حدثٌ جديد يوقظ مراقبي الحساب
        $user->forceFill([
            'tracking_consent' => $choice,
            'tracking_consent_at' => now(),
            'tracking_scopes' => $scopes,
        ])->saveQuietly();

        return true;
    }

    /** هل نعرض البانر أصلًا؟ لا نُزعج مَن اختار، ولا نعرضه والتتبّع مطفأ (2.9) */
    public function shouldAsk(?User $user = null, ?Request $request = null): bool
    {
        return $this->trackingEnabled() && $this->choice($user, $request) === null;
    }
}
