<?php

namespace App\Services\Wallet;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * دفتر الأستاذ — المصدر الوحيد لتغيير أيّ رصيد في المنصّة (19 · 13.4-ن).
 *
 * لماذا كلّ شيء يمرّ من هنا؟
 *  - لأنّ الرصيد لا يُلمَس إلّا داخل معاملة قاعدة بيانات بقفل صفّ المحفظة،
 *    فلا يتسابق نداءان على نفس المحفظة ولا تتغيّر خانةٌ بلا سطرٍ يشرحها.
 *  - ولأنّ حدود العملات (سقف Rep · تراكميّة VXP · حدّ الخسارة اليوميّ)
 *    قاعدة واحدة لا تتكرّر في كلّ مجال فتختلف من مكان لمكان.
 */
class LedgerService
{
    /** درجة الالتزام — وحدها التي يسري عليها حدّ الخسارة اليوميّ (13.4-ن-و) */
    public const REP = 'rep';

    /** إضافة رصيد */
    public function credit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        string $layer = 'training',
        ?string $reason = null,
        ?int $createdBy = null,
    ): Transaction {
        return $this->record($user, $currencyCode, abs($amount), $source, $reference, $layer, $reason, $createdBy);
    }

    /** خصم رصيد */
    public function debit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        string $layer = 'training',
        ?string $reason = null,
        ?int $createdBy = null,
    ): Transaction {
        return $this->record($user, $currencyCode, -abs($amount), $source, $reference, $layer, $reason, $createdBy);
    }

    /**
     * ⭐ خصمٌ لا يُنتج رصيدًا سالبًا أبدًا (19.3).
     *
     * لماذا صنفٌ خاصّ بدل `debit()`؟ لأنّ `debit()` يقصّ ما زاد عن الحدّ بصمت
     * (وهذا صحيح لعملات المكافآت)، أمّا العمليّات الماليّة الثلاث فلا يجوز أن
     * تمرّ بنصف قيمة: إمّا تُنفَّذ كاملة أو تُرَدّ برسالةٍ تشرح الناقص.
     *
     * الفحص والخصم داخل معاملةٍ واحدة وبقفل صفّ المحفظة، فلا يمرّ نداءان
     * على نفس الرصيد فيسحبان معًا أكثر ممّا فيه.
     *
     * @throws WalletException عند عدم كفاية الرصيد
     */
    public function debitOrFail(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        string $layer = 'training',
        ?string $reason = null,
        ?int $createdBy = null,
    ): Transaction {
        $amount = abs($amount);

        return DB::transaction(function () use ($user, $currencyCode, $amount, $source, $reference, $layer, $reason, $createdBy) {
            $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();
            $wallet = $this->lockedWallet($user, (int) $currency->id);

            // هامش 0.001 يمنع رفضًا كاذبًا من فروق الفاصلة العائمة
            if ((float) $wallet->balance - $amount + 0.001 < (float) ($currency->min_value ?? 0)) {
                throw new WalletException($this->insufficientMessage($currency, (float) $wallet->balance, $amount));
            }

            return $this->record($user, $currencyCode, -$amount, $source, $reference, $layer, $reason, $createdBy);
        });
    }

    /**
     * معاملة عكسيّة موثّقة — لا تعديل للأصل ولا حذف (19.4).
     * تُستعمَل في حالة `refunded` من البوّابة وفي تصحيح الخطأ التقنيّ.
     */
    public function reverse(Transaction $original, ?string $reason = null, ?int $createdBy = null): Transaction
    {
        $currency = Currency::query()->findOrFail($original->currency_id);
        $effective = (float) ($original->applied_amount ?? $original->amount);

        return $this->record(
            user: $original->user()->firstOrFail(),
            currencyCode: $currency->code,
            // الرقم الظاهر يُردّ بما نزل عليه فعلًا — لا بأكثر منه
            requested: -$effective,
            source: $original->source,
            reference: $original->reference,
            layer: $original->layer,
            reason: $reason,
            createdBy: $createdBy,
            isCorrection: true,
            correctsTransactionId: $original->id,
            /*
             | ⭐ أمّا في **السجلّ والمكتسَب التراكميّ** فالإلغاء كاملٌ بقيمة الأصل:
             | لو قُصَّت مخالفةٌ −6 إلى −2 بحدّ الخسارة اليوميّ ثمّ قُبِل الاعتراض،
             | فردُّ +2 وحده كان يترك −4 عالقةً في عتبة الـ90 يومًا (13.4-س-ج)
             | فتُفتَح لجنةٌ على مخالفةٍ أُلغِيت. الإلغاء يمحو ما سُجِّل كاملًا.
             */
            recordedAmount: -1 * (float) $original->amount,
        );
    }

    /** الرصيد الحاليّ لعملة بعينها */
    public function balance(User $user, string $currencyCode): float
    {
        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * كتابة الحركة: تحديث الرصيد + سطر في الجدول الموحّد بـ`balance_after`.
     *
     * @param  float  $requested  القيمة المطلوبة بإشارتها (+ إضافة · − خصم)
     * @param  ?float  $recordedAmount  ما يُكتَب في السجلّ والمكتسَب التراكميّ حين
     *                                  يختلف عن المطلوب (المعاملة العكسيّة وحدها)
     */
    private function record(
        User $user,
        string $currencyCode,
        float $requested,
        string $source,
        ?Model $reference,
        string $layer,
        ?string $reason,
        ?int $createdBy,
        bool $isCorrection = false,
        ?int $correctsTransactionId = null,
        ?float $recordedAmount = null,
    ): Transaction {
        return DB::transaction(function () use (
            $user, $currencyCode, $requested, $source, $reference,
            $layer, $reason, $createdBy, $isCorrection, $correctsTransactionId, $recordedAmount
        ) {
            $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();

            $wallet = $this->lockedWallet($user, (int) $currency->id);

            $before = (float) $wallet->balance;
            $applied = round($requested, 2);
            $exceededDailyCap = false;

            /*
             | ⭐ «المسجَّل» ≠ «المطبَّق» (13.4-ن-و): المطبَّق ما نزل على الرقم الظاهر
             | بعد القصّ، والمسجَّل هو القيمة الكاملة التي يعترف بها الدفتر —
             | وهي ما يُكتَب في عمود `amount` وفي **المكتسَب التراكميّ** الذي تُقاس
             | عليه الترقية وعتبة الـ90 يومًا. الخلط بينهما يُعيد ثغرة التصفير.
             */
            $recorded = $recordedAmount !== null ? round($recordedAmount, 2) : $applied;
            $cumulative = $recorded;

            /*
             | العملة التراكميّة غير القابلة للصرف (VXP · XP) لا تُخصَم آليًّا (13.4-ن):
             | الخصم منها لا يكون إلّا بقرار إنسانٍ موثَّق (تصحيح أو إجراء أدمن).
             */
            if ($applied < 0 && $currency->is_cumulative && ! $currency->is_spendable
                && ! $this->documentedHumanDeduction($user, $createdBy, $source, $isCorrection)) {
                // السطر يبقى شاهدًا على المحاولة بقيمتها، لكنّها لم تُطبَّق ولم
                // تُستحَقّ — فلا تدخل المكتسَب التراكميّ
                $applied = 0.0;
                $cumulative = 0.0;
            }

            // حدّ الخسارة اليوميّ لدرجة الالتزام: ما زاد يُسجَّل كاملًا بوسمه (13.4-ن-و)
            if ($applied < 0 && $currencyCode === self::REP) {
                $cap = rep_rule('limit.daily_loss');

                if ($cap < 0) {
                    $remaining = min(0.0, $cap - $this->lostToday($user, (int) $currency->id));

                    if ($applied < $remaining) {
                        $exceededDailyCap = true;
                        $applied = $remaining;
                    }
                }
            }

            /*
             | ⭐⭐ **قاعُ الرصيد للعملة القابلة للصرف — يُرَدّ ولا يُقَصّ** (15.2-4 · 19.3)
             |
             | النصّ الحاكم — **15.2-4:** «**بوابة ≥ 12 تذكرة** للطرفين، **والتذاكر
             | لا تنزل تحت الصفر**». وكان `debit()` ينزل بها تحت الصفر فعلًا:
             | رصيد 3 · خصم 12 ⟵ `balance_after = −9` (مُثبَتٌ بالتشغيل).
             |
             | **ولماذا الردّ لا القصّ؟** لأنّ القصّ عند القاع يجعل `amount = −12`
             | و`applied_amount = −3`: الدفتر يقول اثني عشر والرصيد نزل ثلاثة —
             | وهذا **عين ثغرة السكّ**، إذ يكفي أن يقابله `credit` بـ12 لطرفٍ آخر
             | فتُسَكّ تسع تذاكر من العدم وتسقط «المحصّلة الصفريّة» (15.2-6).
             | فالقاعدة هنا هي قاعدة `debitOrFail` نفسها المنصوصة أعلاه:
             | «**إمّا تُنفَّذ كاملة أو تُرَدّ برسالةٍ تشرح الناقص**».
             |
             | **ولماذا القابلة للصرف وحدها؟** لأنّ `rep` **مسقوفة عمدًا** −10…+10
             | (13.4-ن) والقصّ عند حدّها **سلوكٌ منصوص** لا عطب — وهي غير قابلة
             | للصرف. فالردّ للمال (كوينز · تذاكر · دولار الأرباح)، والقصّ للدرجات.
             |
             | **والتصحيح الموثّق مستثنًى** (19.4): المعاملة العكسيّة لخطأٍ تقنيّ
             | أو لاسترجاعٍ من البوّابة يجب أن تُسجَّل كاملةً ولو تركت الرصيد
             | مدينًا — وإلّا بقي في المحفظة رصيدٌ اعترف الدفتر بأنّه رُدّ.
             */
            if ($applied < 0 && ! $isCorrection && $currency->is_spendable && $currency->min_value !== null
                && $before + $applied + 0.001 < (float) $currency->min_value) {
                throw new WalletException($this->insufficientMessage($currency, $before, abs($applied)));
            }

            // سقف العملة وحدّها الأدنى (Rep مسقوف −10…+10)
            $after = $before + $applied;

            /*
             | ⭐ والتصحيح الموثّق في العملة القابلة للصرف **لا يُقَصّ عند القاع**:
             | لو شحن 100 وصرف 90 ثمّ ردّت البوّابة الشحنة، فالعكسيّة −100 كاملة
             | ورصيدُه **−10** يقول الحقيقة؛ أمّا القصّ عند صفر فيسجّل −100 ويطبّق
             | −10 — دفترٌ يفارق الرصيد، وهو ما نغلقه هنا لا ما نفتحه (19.4).
             | و`rep` غير قابلة للصرف فتبقى مقصوصةً بسقفها المنصوص أبدًا (13.4-ن).
             */
            if ($currency->min_value !== null && ! ($isCorrection && $currency->is_spendable)) {
                $after = max($after, (float) $currency->min_value);
            }

            if ($currency->max_value !== null) {
                $after = min($after, (float) $currency->max_value);
            }

            $after = round($after, 2);
            $applied = round($after - $before, 2);

            $wallet->balance = $after;
            // المكتسَب التراكميّ يقرأ **المسجَّل** لا المسقوف — وإلّا صار مرآةً للرقم الظاهر
            $wallet->lifetime_earned = round((float) $wallet->lifetime_earned + max($cumulative, 0), 2);
            $wallet->lifetime_spent = round((float) $wallet->lifetime_spent + abs(min($cumulative, 0)), 2);
            $wallet->save();

            return Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                // القيمة المطلوبة تُسجَّل كاملةً حتى لو لم تُطبَّق كلّها
                'amount' => $recorded,
                'applied_amount' => $applied,
                'balance_after' => $after,
                'layer' => $layer,
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => $createdBy,
                'exceeded_daily_cap' => $exceededDailyCap,
                'is_correction' => $isCorrection,
                'corrects_transaction_id' => $correctsTransactionId,
                // مهلة الاعتراض تبدأ من لحظة الحركة (13.4-ل)
                'objection_deadline_at' => now()->addDays((int) setting('rep.objection.window_days', 5)),
            ]);
        });
    }

    /**
     * ⭐⭐ **هل هذا الخصم قرارُ إنسانٍ موثَّق أم خصمٌ آليّ؟** (13.4-ن · 23-القسم 5)
     *
     * ================== النصّ الحاكم حرفيًّا ==================
     * • **قاموس 19:** «**نقاط الإنتاج (VXP)** | رصيد الإنتاج **التراكمي** — لا
     *   يتصفّر، **ولا يُخصَم آليًّا بأيّ حدث**. **والخصم لا يقع إلا بقرار بشريّ
     *   موثَّق:** قرار محكّم، أو **معاملة خصم يدويّة** من الأدمن أو مشرف عام
     *   التطوّع».
     * • **23 — القسم 5:** «**خصم VXP المسموح:** قرار المحكّم، أو **معاملة خصم
     *   يدويّة** من **الأدمن** أو **مشرف عام التطوّع** — بمبرّر وتُسجَّل في سجلّ
     *   المعاملات. **ولا خصم آليّ على VXP إطلاقًا**».
     * • **جدول الموارد (13.4):** «`vxp_manual.create` | ENTITY · TRACK · ALL |
     *   **ليس نفسه** | منح/خصم VXP يدويًّا بقرار بشريّ موثَّق ومبرّر — **لا خصم
     *   آليّ إطلاقًا**».
     * • **24 — تاب VXP:** «**الخصم الآليّ ممنوع** (قفل معلَن)».
     *
     * ================== لماذا أُعيد بناء الحارس؟ ==================
     * كان شرطه `createdBy === null` وحده. وكلّ مُنادٍ في المنصّة يمرّر
     * `$createdBy ?? $user->id` — أي **يوقّع الخصم باسم صاحب الرصيد نفسه** حين
     * لا يجد إنسانًا. فالشرط لم يكن يصدق **ولا مرّةً واحدة**: حارسٌ منصوصٌ
     * **لا يحرس شيئًا**، والخصم الآليّ يمرّ كاملًا بتوقيعٍ ذاتيّ.
     *
     * ================== وما الفرق الذي يقيسه الآن؟ ==================
     *  1. **تصحيحٌ موثّق** (`is_correction`) ⟵ مسموح: معاملة عكسيّة لها أصلها.
     *  2. **بلا توقيعٍ أصلًا** (`createdBy === null`) ⟵ **آليّ** فيُحيَّد.
     *  3. **بتوقيع إنسانٍ غير صاحب الرصيد** ⟵ مسموح، وهو **عين ما نصّ عليه
     *     جدول الموارد بقيده «ليس نفسه»**: المحكّم · الأدمن · مشرف عام التطوّع.
     *  4. **بتوقيع صاحب الرصيد نفسه** ⟵ **لا يمرّ إلّا من مصدرٍ معلَن** أنّ
     *     صاحبه يصرف فيه من جيبه بموافقته الصريحة، وهما اثنان بالنصّ:
     *     · **`contribution.hold`** — «رصيد VXP المتبقّي عند المالك بعد الخصم
     *       (**بيتخصم مباشرةً تحت بند رصيد معلَّق**)» (23 — معاينة دعوة المساهم).
     *     · **`task`** — «**والزيادة فوق الوعاء لا تأتي إلا من رصيد الأب الشخصي
     *       بموافقته الصريحة**» (23 — 3.9-٥).
     *     والقائمة **إعدادٌ معلَن** لا رقمٌ محروق (2.13) — هي «القفل المعلَن»
     *     الذي يطلبه تاب VXP في 24، فمن أراد فتح بابٍ خامس فتحه في الشاشة
     *     بعينه ووُثِّق، ولا يفتحه كودٌ جديد بتوقيعٍ ذاتيّ صامت.
     */
    private function documentedHumanDeduction(User $user, ?int $createdBy, string $source, bool $isCorrection): bool
    {
        if ($isCorrection) {
            return true;
        }

        if ($createdBy === null) {
            return false;
        }

        if ($createdBy !== (int) $user->id) {
            return true;
        }

        return in_array($source, $this->selfSpendSources(), true);
    }

    /**
     * المصادر التي يجوز فيها لصاحب الرصيد التراكميّ أن يصرف من رصيده بموافقته
     * الصريحة — قائمة **معلَنة في الإعدادات** لا محروقة في الكود (2.13).
     *
     * @return array<int,string>
     */
    private function selfSpendSources(): array
    {
        $raw = setting('wallet.cumulative.self_spend_sources', "contribution.hold\ntask");

        $lines = is_array($raw) ? $raw : (preg_split('/[\r\n,]+/', (string) $raw) ?: []);

        return array_values(array_filter(array_map('trim', $lines), static fn ($v) => $v !== ''));
    }

    /**
     * رسالة «الرصيد لا يكفي» — **صياغةٌ واحدة** يقرؤها المستخدم من `debitOrFail`
     * ومن قاع `record()` معًا، فلا تختلف الرسالة باختلاف الباب الذي رُدّ منه.
     */
    private function insufficientMessage(Currency $currency, float $balance, float $needed): string
    {
        $decimals = (int) $currency->decimals;

        return 'رصيدك من '.$currency->name_ar.' مش مكفّي: عندك '
            .number_format($balance, $decimals).' والمطلوب '
            .number_format($needed, $decimals).'. قلّل القيمة أو اشحن الأوّل.';
    }

    /** صفّ المحفظة مقفولًا حتى نهاية المعاملة — فلا يقرأ نداءان رصيدًا واحدًا معًا */
    private function lockedWallet(User $user, int $currencyId): WalletBalance
    {
        WalletBalance::query()->firstOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currencyId],
            ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
        );

        return WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** مجموع ما خُصِم فعلًا اليوم (بالسالب) — الأساس الذي يُقاس عليه الحدّ اليوميّ */
    private function lostToday(User $user, int $currencyId): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) < 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->value('total');
    }
}
