<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * حارس تشغيل بوّابة الدفع (19.5-ج-2 · 19.5-ج-5).
 *
 * **لماذا وُجِد؟** كان `expectedHash()` يشتقّ HMAC بـ`setting('topup.gateway.vendor_key','')`
 * بلا أيّ فحصٍ لكون المفتاح مضبوطًا، والقيمة المزروعة **فارغة** بينما
 * `topup.gateway.enabled = 1`. ومفتاحٌ فارغ يعرفه الجميع: فمن يعرف `invoice_id`
 * — **وصاحب الفاتورة يعرفه** — يشتقّ التوقيع بنفسه ويبعث ويب هوك `paid` بلا أن
 * يدفع. `hash_equals` سليمٌ تقنيًّا لكنّه كان يحرس بابًا مفتاحُه معلَن.
 *
 * **القاعدة التي يفرضها هذا الصنف:** السرّ الفارغ ليس «قيمةً افتراضيّة» بل
 * **غياب حماية**. فلا توقيع يُشتقّ به، ولا نداء يُقبَل معه، ولا بوّابة تُعَدّ
 * مفعَّلة بدونه مهما قال التوجّل.
 */
class GatewayGuard
{
    /**
     * المفتاحان اللذان بلا واحدٍ منهما لا تُعَدّ البوّابة مفعَّلة:
     * `api_key` تُنشَأ به الفاتورة، و`vendor_key` يُتحقَّق به من الويب هوك.
     * ونقصُ أيّهما يعني بوّابةً نصفَ موصولة — وهي أسوأ من بوّابةٍ مقفولة.
     */
    public const REQUIRED_KEYS = [
        'topup.gateway.api_key',
        'topup.gateway.vendor_key',
    ];

    /** مفتاح الكاش الذي يمنع تكرار تنبيه الملّاك عن نفس العطب */
    private const NOTIFY_LOCK = 'topup.gateway.misconfig.notified';

    /**
     * المفاتيح الناقصة — والفراغ بعد `trim` نقصٌ كذلك، فمسافةٌ ليست مفتاحًا.
     *
     * @return list<string>
     */
    public static function missingKeys(): array
    {
        return array_values(array_filter(
            self::REQUIRED_KEYS,
            fn (string $key): bool => trim((string) setting($key, '')) === '',
        ));
    }

    public static function isConfigured(): bool
    {
        return self::missingKeys() === [];
    }

    /**
     * ⭐ «مفعَّلة» = التوجّل مرفوع **و**المفتاحان مضبوطان.
     * فـ`topup.gateway.enabled` لا يُعتَدّ به وحده: توجّلٌ مرفوع فوق مفتاحٍ فارغ
     * يَعِد بحمايةٍ غير موجودة، وهو عين ما فتح الثغرة.
     */
    public static function isEnabled(): bool
    {
        return (bool) setting('topup.gateway.enabled', true) && self::isConfigured();
    }

    /**
     * رسالة الأدمن: **ماذا نقص وماذا يفعل** (2.17-ب) — ونصّها كلّه من `setting()`
     * (2.13)، واسم كلّ مفتاحٍ ناقص من لافتته في جدول الإعدادات نفسه، فيقرأ
     * المالك الاسم الذي يراه في لوحته لا مفتاحًا تقنيًّا.
     */
    public static function notice(): string
    {
        $missing = self::missingKeys();

        if ($missing === []) {
            return '';
        }

        $labels = Setting::query()->whereIn('key', $missing)->pluck('label_ar', 'key');

        $names = array_map(
            fn (string $key): string => trim((string) ($labels[$key] ?? '')) ?: $key,
            $missing,
        );

        return str_replace(
            '{missing}',
            implode((string) setting('topup.gateway.misconfig.separator', ' و '), $names),
            (string) setting('topup.gateway.misconfig.notice', ''),
        );
    }

    /**
     * تبليغ ملّاك المنصّة بالعطب — فالرفض الصامت يوقف الهجوم ويُبقي البوّابة
     * معطوبةً بلا أن يدري أحد. والتبليغ مبرَّد بإعداد: عطبٌ واحد لا يولّد
     * إشعارًا لكلّ نداء ويب هوك، وإلّا صار التنبيه نفسه بابَ إغراق.
     */
    public static function warnOwners(): void
    {
        $notice = self::notice();

        if ($notice === '') {
            return;
        }

        $minutes = max((int) setting('topup.gateway.misconfig.notify_cooldown_minutes', 60), 1);

        // `add` ذرّيّة: أوّل نداءٍ يكتب القفل وحده، وما بعده في النافذة لا يبلّغ
        if (! Cache::add(self::NOTIFY_LOCK, true, now()->addMinutes($minutes))) {
            return;
        }

        $owners = User::query()
            ->whereHas('roles', fn ($q) => $q->where('key', (string) config('access.owner_role')))
            ->get();

        foreach ($owners as $owner) {
            AppNotification::create([
                'user_id' => $owner->id,
                'layer' => 'platform',
                'category' => 'topup',
                'title' => (string) setting('topup.gateway.misconfig.title', ''),
                'body' => $notice,
                'url' => route('admin.topups.gateway'),
                'requires_action' => true,
            ]);
        }
    }
}
