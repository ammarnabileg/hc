<?php

namespace App\Services\Wallet;

use App\Models\GatewayInvoice;
use App\Models\TopupRequest;
use App\Models\User;
use Closure;

/**
 * حدود الشحن — **مصدر حقيقةٍ واحد** للحدّ الأدنى والأقصى والحدّ اليوميّ
 * (24.3 «🔒 الماليّات» · 19.5-و · 19.5-ج-5).
 *
 * **لماذا وُجِد هذا الصنف؟** كانت المنصّة تحمل **مجموعتَي «حدود شحن» منفصلتين
 * لا تلتقيان**، وواحدةٌ فقط موصولة — وهي الخطأ:
 *
 * | المفتاح | مزروع في | القيمة | مَن كان يقرؤه |
 * |---|---|---|---|
 * | `finance.topup.min_amount` / `max_amount` / `daily_limit` | `AdminSystemDemoSeeder` (شاشة 🔒 الماليّات) | 50 / 20000 / 50000 | **لا أحد** |
 * | `topup.gateway.min_amount` / `max_amount` | `AdminSystemDemoSeeder` (شاشة البوّابة) | 50 / 20000 | **لا أحد** |
 * | `topup.min_amount` | `WalletDemoSeeder` | **10** | `TopupController::storeManual()` وحده |
 *
 * فمالك المنصّة يفتح شاشة الماليّات — **«مصدر الحقيقة الوحيد لكلّ رقم ماليّ»**
 * بنصّ 24.3 — ويضبط الحدّ الأدنى 50، ثمّ يمرّ تحويلٌ بـ20 لأنّ الكنترولر كان
 * يقرأ مفتاحًا ثالثًا مخفيًّا قيمته 10. ولا حدَّ أقصى ولا حدَّ يوميّ مطبَّقًا
 * أصلًا في أيّ مسار، مهما كتب المالك في شاشته.
 *
 * **القاعدة المعتمَدة هنا:**
 * - `finance.topup.*` هي **سياسة المنصّة** وتسري على الطريقتين معًا (يدويّ وبوّابة).
 * - `topup.gateway.min_amount/max_amount` **نافذة المزوّد** (19.5-ج-5): لا توسّع
 *   سياسة المنصّة أبدًا بل **تضيّقها** على مسار البوّابة وحده — فالبوّابة قد
 *   ترفض ما تقبله سياستنا، والعكس ليس صحيحًا.
 * - `topup.min_amount` **حُذف**: تكرارٌ صامتٌ لـ`finance.topup.min_amount`
 *   (مايجريشن `2026_09_11_130010`).
 */
class TopupLimits
{
    /** التحويل اليدويّ — سياسة المنصّة وحدها */
    public const CHANNEL_MANUAL = 'manual';

    /** البوّابة — سياسة المنصّة **مضيَّقةً** بنافذة المزوّد */
    public const CHANNEL_GATEWAY = 'gateway';

    /** أدنى قيمة مقبولة للقناة — والبوّابة تأخذ الأشدّ من الحدّين */
    public function min(string $channel): float
    {
        $min = (float) setting('finance.topup.min_amount', 50);

        if ($channel === self::CHANNEL_GATEWAY) {
            $min = max($min, (float) setting('topup.gateway.min_amount', 50));
        }

        return max($min, 0.0);
    }

    /** أقصى قيمة مقبولة للقناة — و**صفرٌ يعني بلا سقف** لا سقفًا صفريًّا */
    public function max(string $channel): float
    {
        $max = max((float) setting('finance.topup.max_amount', 20000), 0.0);

        if ($channel !== self::CHANNEL_GATEWAY) {
            return $max;
        }

        $gateway = max((float) setting('topup.gateway.max_amount', 20000), 0.0);

        // نافذة المزوّد تضيّق ولا توسّع: الأصغر من السقفين، وصفرُ أحدهما «بلا سقف»
        return $max > 0 && $gateway > 0 ? min($max, $gateway) : max($max, $gateway);
    }

    /** الحدّ اليوميّ لكلّ مستخدم — وصفرٌ يعني بلا حدّ (24.3) */
    public function dailyLimit(): float
    {
        return max((float) setting('finance.topup.daily_limit', 50000), 0.0);
    }

    /**
     * ما شُحِن اليوم فعلًا — **مالٌ خرج من جيب المستخدم لا نيّةٌ سُجِّلت**:
     * الطلبات اليدويّة القائمة (قيد التحقّق أو مكتملة — والملغاة والمكرَّرة لا
     * تُحسَب) + فواتير البوّابة التي **أُضيف رصيدها فعلًا**.
     *
     * ⭐ ولا تُحسَب الفاتورة لحظة فتحها: لو حُسِبت، لأقفل المستخدمُ حدَّه اليوميّ
     *    على نفسه بضغطاتٍ على [ادفع دلوقتي] بلا أن يدفع مليمًا واحدًا.
     */
    public function usedToday(User $user): float
    {
        $manual = (float) TopupRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [TopupService::PENDING, TopupService::COMPLETED])
            ->whereDate('created_at', now()->toDateString())
            ->sum('transferred_amount');

        $gateway = (float) GatewayInvoice::query()
            ->where('user_id', $user->id)
            ->where('credited', true)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        return $manual + $gateway;
    }

    /**
     * سبب الرفض إن وُجد، و`null` إن كانت القيمة مقبولة.
     * والرسالة تقول **ماذا حدث وماذا يفعل** (2.17-ب) ونصّها كلّه من `setting()` (2.13).
     */
    public function rejectionFor(User $user, float $amount, string $channel): ?string
    {
        $min = $this->min($channel);

        if ($amount < $min) {
            return strtr(
                (string) setting('wallet.topup_limits.below_min', 'أقلّ مبلغ للشحن :p1 — زوّد القيمة وابعت تاني.'),
                [':p1' => $this->format($min)],
            );
        }

        $max = $this->max($channel);

        if ($max > 0 && $amount > $max) {
            return strtr(
                (string) setting('wallet.topup_limits.above_max', 'أقصى مبلغ للعمليّة الواحدة :p1 — قلّل القيمة أو قسّمها على أكتر من عمليّة.'),
                [':p1' => $this->format($max)],
            );
        }

        $daily = $this->dailyLimit();

        if ($daily > 0 && $this->usedToday($user) + $amount > $daily) {
            return strtr(
                (string) setting('wallet.topup_limits.daily_exceeded', 'الحدّ اليوميّ للشحن :p1 وانت شحنت النهارده :p2 — الباقي لك النهارده :p3، وبكرة يبدأ الحدّ من أوّله.'),
                [
                    ':p1' => $this->format($daily),
                    ':p2' => $this->format($this->usedToday($user)),
                    ':p3' => $this->format(max($daily - $this->usedToday($user), 0.0)),
                ],
            );
        }

        return null;
    }

    /**
     * نفس الفحص كقاعدة تحقّقٍ على حقل الفورم — فيقع الخطأ **على الحقل نفسه**
     * لا في شريط حالةٍ عامّ، ويرجع المستخدم لفورمٍ مملوء.
     */
    public function rule(User $user, string $channel): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user, $channel): void {
            if (! is_numeric($value)) {
                return;
            }

            $rejection = $this->rejectionFor($user, (float) $value, $channel);

            if ($rejection !== null) {
                $fail($rejection);
            }
        };
    }

    /** رقمٌ بلا أصفارٍ زائدة: 50 لا 50.00، و12.5 تبقى 12.5 */
    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
