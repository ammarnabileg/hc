<?php

namespace App\Services\Gamification;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use ArgumentCountError;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

        if ($this->postedByLedger($user, $currencyCode, $signedAmount, $source, $reason, $reference, $method)) {
            return true;
        }

        DB::transaction(function () use ($user, $currency, $signedAmount, $source, $reason, $reference) {
            $wallet = WalletBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
            );

            // الرصيد لا ينزل تحت الصفر (15.2-4)
            $balance = max(0, (float) $wallet->balance + $signedAmount);

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
        });

        return true;
    }

    /**
     * محاولة التمرير لدفتر الأستاذ حين يوجد.
     * نلتقط أخطاء التوقيع وحدها (TypeError/ArgumentCountError) — وهي تُرمى
     * قبل تنفيذ جسم الدالّة، فلا خطر من خصمٍ مزدوج عند الرجوع للبديل.
     *
     * ⚠️ **بوسائط مسمّاة عمدًا:** التمرير بالترتيب هو عين العطل الذي أسقط
     * السبب العربيّ في خانة `source`؛ والاسم لا ينزلق حين يتغيّر التوقيع.
     */
    private function postedByLedger(User $user, string $currencyCode, float $signedAmount, string $source, ?string $reason, ?Model $reference, string $method): bool
    {
        if (! class_exists(self::LEDGER)) {
            return false;
        }

        $ledger = app(self::LEDGER);

        if (! method_exists($ledger, $method)) {
            return false;
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
            return false;
        }
    }
}
