<?php

namespace App\Services\Gamification;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use ArgumentCountError;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use TypeError;

/**
 * بوّابة المحفظة لمجال التحديات (نقطة التكامل).
 *
 * لماذا هذا الغلاف: دفتر الأستاذ الموحّد `App\Services\Wallet\LedgerService`
 * يملكه مجالٌ آخر يُبنى الآن؛ فنستدعيه متى وُجد، ونتحمّل غيابه بأمان
 * بكتابة نفس السطرين (رصيد + معاملة) داخل معاملة ذرّيّة — فلا يتعطّل التحدّي
 * ولا يتكرّر الخصم حين يصل الدفتر لاحقًا.
 *
 * ⭐ **خانتان لا واحدة (24.2 · 19.2):** جدول الكسب في لوحة الإدارة عموده
 * «**المصدر**» ويُبحَث فيه «بالمصدر/الـKey»، وعليه وحده يقع «**حدّ يوميّ**»؛
 * أمّا جدول معاملات المحفظة فعموده «**السبب**» جملةٌ للمستخدم. فالمصدر
 * **مفتاحٌ** (`challenge` · `streak`) والسبب **نصٌّ عربيّ** — ولا يجتمعان في خانة.
 *
 * وكان هذا الغلاف يمرّر السبب في موضع `$source` من الدفتر **بالترتيب**، فينزل
 * «مواجهة حرب #1 — فوز» في خانة المصدر ويبقى `reason` فارغًا: تقاريرُ تتفتّت،
 * و`EconomyLedger::withinDailyCap()` يرشّح بـ`where('source', …)` فلا يجد شيئًا
 * ⟵ **الحدّ اليوميّ بلا أثر**. فمن اليوم: `$source` وسيطٌ صريحٌ في التوقيع،
 * والتمرير للدفتر **بوسائط مسمّاة** لا بالترتيب — فلا يعود اختلافُ توقيعٍ
 * يمرّ صامتًا.
 */
class WalletGateway
{
    private const LEDGER = 'App\Services\Wallet\LedgerService';

    /** «الرصيد لا يكفي» من الدفتر — نميّزها عن غياب الخدمة فلا نكتب ما رفضه */
    private const WALLET_EXCEPTION = 'App\Services\Wallet\WalletException';

    /** مُعرِّف خرقِ ثابتٍ داخليّ (إضافةٌ ردّت بعد خصمٍ نجح) — ليس نصًّا لمستخدم */
    private const CREDIT_FAILED = 'wallet.transfer.credit_refused_after_debit';

    /** رصيد المستخدم من عملةٍ بكودها (coins · tickets · xp) */
    public function balance(User $user, string $currencyCode): float
    {
        $currency = $this->currency($currencyCode);

        if (! $currency) {
            return 0.0;
        }

        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currency->id)
            ->value('balance');
    }

    /**
     * خصمٌ موجَّه (دخول تحدّي) — القيمة تُمرَّر موجبةً وتُسجَّل سالبةً.
     *
     * @param  string  $source  **مفتاح** دلو المصدر في الدفتر (`challenge` · `streak`)
     * @param  ?string  $reason  **جملة** السبب كما يقرؤها المستخدم في تاب المعاملات (19.2)
     */
    public function debit(User $user, string $currencyCode, float $amount, string $source, ?string $reason = null, ?Model $reference = null): bool
    {
        return $this->post($user, $currencyCode, -abs($amount), $source, $reason, $reference, 'debit');
    }

    /** إضافةٌ موجَّهة (مكافأة تحدّي) — والوسيطان كما في `debit()` */
    public function credit(User $user, string $currencyCode, float $amount, string $source, ?string $reason = null, ?Model $reference = null): bool
    {
        return $this->post($user, $currencyCode, abs($amount), $source, $reason, $reference, 'credit');
    }

    /**
     * ⭐⭐ **تحويلٌ بين مستخدمَين — يُخصَم بالضبط ما يُضاف أو لا شيء** (15.2-6).
     *
     * ================== النصّ الحاكم حرفيًّا ==================
     * • **15.2-6:** «**منع الفارمينج:** الرابح **+2** والخاسر **−2** (**محصّلة
     *   صفرية**)».
     * • **15.2-4:** «**بوابة ≥ 12 تذكرة** للطرفين، **والتذاكر لا تنزل تحت الصفر**».
     * • **15.3:** «**تذكرة الانضمام تروح لـ صاحب التحدي** (تحويل مباشر بين
     *   المستخدمين — **مش minting**، فالفارمينج مقفول)».
     *
     * ================== لماذا دالّةٌ واحدة بدل سطرين متجاورين؟ ==================
     * لأنّ كلّ مواضع التحويل كانت تكتب `debit()` ثمّ `credit()` **وتُهمل نتيجة
     * الخصم**: فإن رُدَّ الخصم مضت الإضافة، و**سُكَّت تذكرة من العدم**. والانضباط
     * عند ثلاثة مواضع لا يُغني عن بناءٍ لا يسمح بالخطأ أصلًا — فمن اليوم:
     * **الخصم أوّلًا، ولا إضافة إلّا بعد نجاحه، وكلاهما في معاملةٍ واحدة**،
     * فالمحصّلة الصفريّة **خاصّيّةُ بناءٍ** لا وعدَ مراجعة.
     *
     * @return float ما تحرّك فعلًا — و`0.0` تعني أنّ الرصيد لم يغطِّ فلم يتحرّك شيء
     */
    public function transfer(
        User $from,
        User $to,
        string $currencyCode,
        float $amount,
        string $source,
        ?string $debitReason = null,
        ?string $creditReason = null,
        ?Model $reference = null,
    ): float {
        $amount = abs($amount);

        if ($amount <= 0.0) {
            return 0.0;
        }

        return (float) DB::transaction(function () use ($from, $to, $currencyCode, $amount, $source, $debitReason, $creditReason, $reference) {
            if (! $this->debit($from, $currencyCode, $amount, $source, $debitReason, $reference)) {
                return 0.0;
            }

            if (! $this->credit($to, $currencyCode, $amount, $source, $creditReason, $reference)) {
                // لا يقع عمليًّا (الخصم أثبت وجود العملة وأنّ القيمة غير صفريّة) —
                // وإن وقع فالمعاملة تُرتجَع كاملةً فلا يبقى خصمٌ بلا إضافةٍ تقابله.
                // ومُعرِّفٌ داخليّ لا جملةٌ للمستخدم: هذا خرقُ ثابتٍ لا رسالةُ خطأ (2.13).
                throw new RuntimeException(self::CREDIT_FAILED);
            }

            return $amount;
        });
    }

    public function currency(string $code): ?Currency
    {
        static $cache = [];

        return $cache[$code] ??= Currency::query()->where('code', $code)->first();
    }

    /** اسم العملة بالعربيّة للعرض في الواجهة والرسائل */
    public function label(string $code): string
    {
        return $this->currency($code)?->name_ar ?? $code;
    }

    // ------------------------------------------------------------------ داخليّ

    private function post(User $user, string $currencyCode, float $signedAmount, string $source, ?string $reason, ?Model $reference, string $method): bool
    {
        $currency = $this->currency($currencyCode);

        if (! $currency || $signedAmount == 0.0) {
            return false;
        }

        $byLedger = $this->postedByLedger($user, $currencyCode, $signedAmount, $source, $reason, $reference, $method);

        // `null` وحدها تعني «لا دفتر أصلًا» — أمّا `false` فقرارٌ صريح بالردّ
        if ($byLedger !== null) {
            return $byLedger;
        }

        return (bool) DB::transaction(function () use ($user, $currency, $signedAmount, $source, $reason, $reference) {
            $wallet = WalletBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
            );

            // ⭐ الصفّ مقفولٌ قبل القراءة — وإلّا قرأ نداءان رصيدًا واحدًا فخصما معًا
            $wallet = WalletBalance::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $before = (float) $wallet->balance;
            $balance = $before + $signedAmount;

            /*
             | ⭐⭐ **العجز يُرَدّ ولا يُبتلَع** (15.2-4 · 15.2-6).
             |
             | كان هنا `max(0, $before + $signedAmount)`: الرصيد يقف عند القاع
             | بينما يُقيَّد في `transactions` **بقيمته كاملة**. رصيد 3 · خصم 12
             | ⟵ `amount = −12` و`balance_after = 0` والرصيد نزل **3 فقط**؛ فإن
             | قابله `credit` بـ12 لطرفٍ آخر **سُكَّت تسع تذاكر من العدم** وسقطت
             | «المحصّلة الصفريّة» المنصوصة في 15.2-6.
             |
             | ولأنّ «التذاكر لا تنزل تحت الصفر» (15.2-4) **ولا يجوز أن يفترق
             | الدفتر عن الرصيد**، فالجواب الوحيد الذي يجمع النصّين هو **الردّ**:
             | لا سطر ولا خصم ولا إضافة. والقاع **بيانٌ من العملة** لا رقمٌ
             | محروق هنا (2.13).
             */
            $floor = (float) ($currency->min_value ?? 0);

            if ($signedAmount < 0 && $balance + 0.001 < $floor) {
                return false;
            }

            $wallet->forceFill([
                'balance' => $balance,
                'lifetime_earned' => (float) $wallet->lifetime_earned + max(0, $signedAmount),
                'lifetime_spent' => (float) $wallet->lifetime_spent + max(0, -$signedAmount),
            ])->save();

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                'amount' => $signedAmount,
                'balance_after' => $balance,
                'layer' => 'training',
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
            ]);

            return true;
        });
    }

    /**
     * محاولة التمرير لدفتر الأستاذ حين يوجد.
     * نلتقط أخطاء التوقيع وحدها (TypeError/ArgumentCountError) — وهي تُرمى
     * قبل تنفيذ جسم الدالّة، فلا خطر من خصمٍ مزدوج عند الرجوع للبديل.
     *
     * ⚠️ **بوسائط مسمّاة عمدًا:** التمرير بالترتيب هو عين العطل الذي أسقط
     * السبب العربيّ في خانة `source`؛ والاسم لا ينزلق حين يتغيّر التوقيع.
     *
     * ⭐ **والخصم يمرّ بـ`debitOrFail` لا بـ`debit`:** عقد هذه البوّابة أنّ
     * `debit()` ترجع **هل وقع الخصم فعلًا؟** — و`debitOrFail` تفحص وتخصم داخل
     * معاملةٍ واحدة **بقفل صفّ المحفظة**، فترمي **قبل أن تكتب شيئًا** ولا يمرّ
     * خصمٌ بنصف قيمته (19.3). وهو نفس ما يفعله `Events\LedgerBridge`
     * و`EconomyLedger::charge()` — فبابٌ واحد للخصم في المنصّة كلّها.
     *
     * @return ?bool `true` قُيِّدت · `false` رُدَّت لعدم كفاية الرصيد ·
     *               `null` لا دفتر أصلًا فيُكمل المستدعي بمسار الاحتياط
     */
    private function postedByLedger(User $user, string $currencyCode, float $signedAmount, string $source, ?string $reason, ?Model $reference, string $method): ?bool
    {
        if (! class_exists(self::LEDGER)) {
            return null;
        }

        $ledger = app(self::LEDGER);
        $method = $method === 'debit' ? 'debitOrFail' : $method;

        if (! method_exists($ledger, $method)) {
            return null;
        }

        try {
            $ledger->{$method}(
                user: $user,
                currencyCode: $currencyCode,
                amount: abs($signedAmount),
                source: $source,
                reference: $reference,
                layer: 'training',
                reason: $reason,
            );

            return true;
        } catch (TypeError|ArgumentCountError) {
            return null;
        } catch (Throwable $e) {
            // رصيدٌ لا يغطّي: قرارٌ صريح من الدفتر **ولم يُكتَب شيء** —
            // فلا رجوع لمسار الاحتياط وإلّا كتبنا ما رفضه الدفتر.
            // ونسأل عنه **بالاسم** لا بـ`use` حفاظًا على تحمّل غياب المجلّد.
            if (is_a($e, self::WALLET_EXCEPTION)) {
                return false;
            }

            throw $e;
        }
    }
}
