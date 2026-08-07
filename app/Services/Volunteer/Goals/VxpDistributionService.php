<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * توزيع نقاط الإنتاج شرائحيًّا (الدستور 23 — 3.9-٥).
 *
 * قيدان آليّان يُفحَصان **عند الحفظ** لا كنصّ تحذيريّ ثابت (2.15-د):
 *  (أ) مجموع ما يوزّعه الأب على أبنائه ≤ **وعاء مهمّته**.
 *  (ب) **شريحة محفوظة للأب** لا تقلّ عن نسبة الأدمن — فلا يوزّع 100% ويشتغل ببلاش،
 *      ولا يوزّع 5% ويستغلّ فريقه.
 *
 * ⭐ **حدود «الموافقة الصريحة» — قراءةً حرفيّةً للنصّ:** جاءت في النصّ على شيءٍ
 * واحدٍ باسمه: «**والزيادة فوق الوعاء** لا تأتي إلّا من رصيد الأب الشخصي
 * بموافقته الصريحة». فهي استثناءٌ على القيد **(أ)** وحده — ولا حرف يُبيح بها كسر
 * القيد **(ب)**. بل النصّ ينفي نتيجتَه صراحةً: «**فلا يوزّع 100% ويشتغل ببلاش**».
 * ولذلك هنا:
 *  • **الزيادة (`overflow`) تُقاس على الوعاء** (`total − pool`) لا على السقف
 *    (`total − max`) — فما دام التوزيع **داخل الوعاء** فلا زيادة أصلًا، ولا شيء
 *    يُخصَم من جيب الأب: خصمُ الفرق حتى السقف **خصمٌ وهميّ** لنقاطٍ من وعائه هو.
 *  • **أرضيّة الشريحة رفضٌ مطلق لا يُشترى بموافقة** — تُفحَص قبل الموافقة وبعدها
 *    سواء. (وحين يضبط الأدمن النسبة صفرًا تنفتح طريق «الزيادة فوق الوعاء» كما
 *    نصّ الدستور، لأنّ السقف حينها = الوعاء.)
 */
class VxpDistributionService
{
    public const CURRENCY = 'vxp';

    /** أدنى شريحة محفوظة للأب (%) — إعداد لا رقم محروق (2.13) */
    public function parentMinSharePercent(): float
    {
        return (float) setting('workflow.vxp.parent_min_share_percent', 10);
    }

    /**
     * ⭐ معامل جودة الإنجاز ⟵ VXP (24.2 التاب 2): جدول ثلاثيّ قابل للتحرير —
     * 60% / 80% / 100% افتراضيًّا. **الجودة تحجِّم VXP وحده ولا تمسّ Rep**
     * (مبدأ الفصل §6) — فهذا الجدول لا علاقة له بـ`rep_rules` عمدًا.
     *
     * @return array<string, float> tier key => نسبة مئويّة
     */
    public function qualityTiers(): array
    {
        return [
            'low' => (float) setting('workflow.vxp.quality_tier_low', 60),
            'mid' => (float) setting('workflow.vxp.quality_tier_mid', 80),
            'high' => (float) setting('workflow.vxp.quality_tier_high', 100),
        ];
    }

    /** معامل الجودة كنسبة (1.0 = كامل) — والمستوى الغائب أو غير المعروف لا يحجِّم شيئًا */
    public function qualityCoefficient(?string $tier): float
    {
        $tiers = $this->qualityTiers();

        if ($tier === null || ! array_key_exists($tier, $tiers)) {
            return 1.0;
        }

        return max(0.0, $tiers[$tier] / 100);
    }

    /** أقصى ما يجوز توزيعه من وعاء الأب بعد حجز شريحته */
    public function maxDistributable(Task $parent): float
    {
        $pool = (float) $parent->vxp_value;

        return round($pool * (1 - ($this->parentMinSharePercent() / 100)), 2);
    }

    /** شريحة الأب المحفوظة بالقيمة لا بالنسبة — لعرضها في البوب-أب */
    public function reservedShare(Task $parent): float
    {
        return round((float) $parent->vxp_value - $this->maxDistributable($parent), 2);
    }

    /**
     * ⭐⭐ **ما يقبضه الأب فعلًا عند التسليم = الوعاء − ما وزّعه منه** (23 — 3.9-٥).
     *
     * ================== النصّ الحاكم حرفيًّا ==================
     * «ثمّ **التوزيع نزولًا**: كلّ أب يوزّع على صب-تاسكاته **من وعاء مهمّته**
     * **ويحتفظ بشريحة الدمج والإشراف لنفسه** — بقيدين آليّين: (أ) مجموع ما
     * يوزّعه على أبنائه ≤ وعاء مهمّته … (ب) **شريحة محفوظة للأب** لا تقلّ عن
     * نسبة يحدّدها الأدمن … **فلا يوزّع 100% ويشتغل ببلاش**، ولا يوزّع 5%
     * ويستغلّ فريقه. **والزيادة فوق الوعاء لا تأتي إلا من رصيد الأب الشخصي
     * بموافقته الصريحة**.»
     *
     * ================== لماذا هذه الدالّة أصلًا؟ ==================
     * لأنّ التوزيع كان **قيدًا على رقمٍ لا يُترجَم إلى نقود**: `check()`/`distribute()`
     * يحرسان مجموع الأبناء حراسةً محكمة، ثمّ يقبض الأب عند التسليم **وعاءه
     * كاملًا** كأنّه لم يوزّع شيئًا — فتُدفَع نفس النقطة مرّتين: مرّةً للابن على
     * شريحته ومرّةً للأب على الوعاء كلّه. والنصّ يسمّي شريحة الأب باسمها:
     * «**شريحة الدمج والإشراف**» — أي ما بقي بعد ما نزل، لا الوعاء كلّه.
     *
     * ================== والحالة الثالثة: التوزيع فوق الوعاء ==================
     * حين يضبط الأدمن الأرضيّة صفرًا ينفتح المسار المنصوص: يوزّع الأب أكثر من
     * وعائه، **والفرق يُخصَم من رصيده الشخصيّ لحظة الحفظ** (`personal_vxp_top_up`).
     * فما يقبضه حينها؟ **صفر — لا سالب**. والحساب يقرأ النصّ حرفيًّا:
     *
     *   المنصرف **من الوعاء** = `min(ما وزّعه, الوعاء)`  ⟵ فما فوقه ليس منه
     *   شريحته = الوعاء − المنصرف من الوعاء
     *
     * فلو وزّع 120 على وعاء 100 بموافقة: خرج 20 من جيبه لحظة الحفظ، والمنصرف من
     * الوعاء 100، وشريحته **صفر**. ولو حُسِبت «الوعاء − ما وزّعه» لَخرجت **−20**:
     * خصمٌ ثانٍ على نفس العشرين التي دُفِعت بالفعل — أي **خصم مزدوج على فعلٍ
     * واحد**، وهو الممنوع بعينه (23-6: «أي حدث بيلمس الاتنين مع بعض = خصم مزدوج
     * على غلطة واحدة — **ممنوع**»).
     *
     * ⚠️ **ولماذا `min` لا «طرحُ ما دُفِع من الجيب ثمّ قاعٌ عند الصفر»؟** لأنّ
     * الثاني **تعبيران يقولان شيئًا واحدًا**: مع الثابت `top_up = max(0, الموزَّع
     * − الوعاء)` يعطي الطرحُ والقاعُ نفس الرقم دائمًا، فأيّهما حُذِف لم يتغيّر
     * شيء — أي **فرعٌ لا يقدر اختبارٌ على إسقاطه** (ثبت بالطفرة: حذفُ الطرح لم
     * يُسقِط اختبارًا واحدًا). و`min` تعبيرٌ واحد لا ظلّ له، وهي **نفس الصيغة
     * التي يقيس بها `check()` أرضيّةَ الشريحة** — فمقياسُ الحفظ ومقياسُ القبض
     * واحد، ولا يفترق الحارس عن الأثر الذي يحرسه.
     */
    public function parentEarning(Task $parent): float
    {
        $pool = round((float) $parent->vxp_value, 2);

        if ($pool <= 0) {
            return 0.0;
        }

        $distributed = round((float) $this->children($parent)->sum('vxp_value'), 2);

        // ما فوق الوعاء ليس من الوعاء — جاء من جيب الأب بموافقته الصريحة
        $fromPool = round(min(max(0, $distributed), $pool), 2);

        return round($pool - $fromPool, 2);
    }

    /** أبناء المهمّة القابلون للتوزيع عليهم */
    public function children(Task $parent)
    {
        return Task::query()->where('parent_task_id', $parent->id)->orderBy('id')->get();
    }

    /**
     * ملخّص التوزيع الحاليّ — الوعاء · المنصرف · المتاح · الشريحة المحفوظة.
     *
     * @return array{pool:float,distributed:float,reserved:float,max:float,remaining:float,percent:float}
     */
    public function summary(Task $parent): array
    {
        $pool = round((float) $parent->vxp_value, 2);
        $distributed = round((float) $this->children($parent)->sum('vxp_value'), 2);
        $max = $this->maxDistributable($parent);

        return [
            'pool' => $pool,
            'distributed' => $distributed,
            'reserved' => $this->reservedShare($parent),
            'max' => $max,
            'remaining' => round(max(0, $max - $distributed), 2),
            'percent' => $pool > 0 ? round(($distributed / $pool) * 100, 2) : 0.0,
        ];
    }

    /**
     * فحص القيدين قبل الحفظ — يُرجِع الأخطاء بلغة «ماذا حدث + ماذا تفعل» (2.17-ب).
     *
     * @param  array<int,float>  $shares  معرّف الابن ⟵ قيمته
     * @return array{ok:bool,total:float,overflow:float,errors:array<int,string>}
     */
    public function check(Task $parent, array $shares, bool $consentPersonal = false, ?User $payer = null): array
    {
        $total = round(array_sum(array_map(static fn ($v) => (float) $v, $shares)), 2);
        $pool = round((float) $parent->vxp_value, 2);
        $max = $this->maxDistributable($parent);
        $errors = [];

        foreach ($shares as $childId => $value) {
            if ((float) $value < 0) {
                $errors[] = setting('goals.vxp_distribution_service.check_1', 'قيمة سالبة غير مقبولة — اكتب صفرًا أو أكثر.');
                break;
            }
        }

        // ⭐ الزيادة تُقاس على **الوعاء** لا على السقف — فداخل الوعاء لا زيادة أصلًا
        $overflow = round(max(0, $total - $pool), 2);

        // القيد (أ): مجموع الأبناء ≤ وعاء المهمّة — وهذا وحده ما تُبيحه الموافقة الصريحة
        if ($overflow > 0 && ! $consentPersonal) {
            $errors[] = strtr(setting('goals.vxp_distribution_service.check_2', 'مجموع ما توزّعه (:p1) أكبر من وعاء المهمّة (:p2) — قلّل القيم أو وافق صراحةً على الخصم من رصيدك الشخصيّ.'), [':p1' => (string) ($this->num($total)), ':p2' => (string) ($this->num($pool))]);
        }

        /*
         | القيد (ب): شريحة الأب المحفوظة — **رفضٌ مطلق لا يُشترى بموافقة**.
         | يُفحَص خارج شرط الموافقة عمدًا: النصّ يبيح بالموافقة «الزيادة فوق الوعاء»
         | لا كسر الأرضيّة، وينفي نتيجتها باللفظ: «فلا يوزّع 100% ويشتغل ببلاش».
         |
         | والشريحة تُقاس على **ما صُرِف من الوعاء** — `min(total, pool)` — لا على
         | المجموع كلّه، لأنّ ما فوق الوعاء ليس من الوعاء أصلًا بل من جيب الأب.
         | وبهذا يبقى مسار «الزيادة فوق الوعاء» المنصوص عليه **حيًّا** حين يضبط
         | الأدمن النسبة صفرًا (فالشريحة تصير صفرًا وهو حدّها الأدنى)، ويبقى
         | **مغلقًا** في الافتراضيّ 10% مهما وقّع الأب على الموافقة.
         */
        if (round(min($total, $pool) - $max, 2) > 0) {
            $errors[] = strtr(setting('goals.vxp_distribution_service.check_3', 'لازم تحتفظ بـ:p1% على الأقلّ من الوعاء لشريحتك (:p2 نقطة) — أقصى ما توزّعه :p3. وشريحتك دي مش بتتباع بموافقة.'), [':p1' => (string) ($this->num($this->parentMinSharePercent())), ':p2' => (string) ($this->num($this->reservedShare($parent))), ':p3' => (string) ($this->num($max))]);
        }

        if ($overflow > 0 && $consentPersonal) {
            $balance = $payer ? Integrations::balance($payer, self::CURRENCY) : 0.0;

            if ($balance < $overflow) {
                $errors[] = strtr(setting('goals.vxp_distribution_service.check_4', 'رصيدك الشخصيّ (:p1) لا يكفي الفرق المطلوب (:p2) — قلّل القيم.'), [':p1' => (string) ($this->num($balance)), ':p2' => (string) ($this->num($overflow))]);
            }
        }

        return [
            'ok' => $errors === [],
            'total' => $total,
            'overflow' => $overflow,
            'errors' => $errors,
        ];
    }

    /**
     * حفظ التوزيع بعد اجتياز القيدين — والزيادة (إن أُقرّت) تُخصَم من رصيد الأب الشخصيّ.
     *
     * @param  array<int,float>  $shares
     *
     * @throws ValidationException
     */
    public function distribute(Task $parent, array $shares, bool $consentPersonal = false, ?User $payer = null): array
    {
        $payer ??= $parent->owner_id ? User::query()->find($parent->owner_id) : null;

        $check = $this->check($parent, $shares, $consentPersonal, $payer);

        if (! $check['ok']) {
            throw ValidationException::withMessages(['shares' => $check['errors']]);
        }

        DB::transaction(function () use ($parent, $shares, $check, $payer) {
            $children = $this->children($parent)->keyBy('id');

            foreach ($shares as $childId => $value) {
                $child = $children->get((int) $childId);

                if (! $child) {
                    continue;
                }

                $child->forceFill(['vxp_value' => round((float) $value, 2)])->save();
            }

            // ما لم تكن هناك زيادة فلا top-up — والقيمة القديمة تُصفَّر مع كلّ حفظ
            $parent->forceFill(['personal_vxp_top_up' => $check['overflow']])->save();

            if ($check['overflow'] > 0 && $payer) {
                // الزيادة فوق الوعاء من الرصيد الشخصيّ بموافقة صريحة (23 — 3.9-٥)
                $transaction = Integrations::debit(
                    user: $payer,
                    currencyCode: self::CURRENCY,
                    amount: $check['overflow'],
                    source: 'task',
                    reference: $parent,
                    reason: setting('goals.vxp_distribution_service.distribute_1', 'زيادة فوق وعاء المهمّة بموافقة صريحة من الرصيد الشخصيّ'),
                    createdBy: $payer->id,
                );

                /*
                 | ⭐ «لا نقطة تنزل على ابن إلّا وقد نزلت فعلًا من جيب الأب»:
                 | دفتر الأستاذ يحيّد الخصم الآليّ على VXP (13.4-ن) فيكتب السطر
                 | بـ`applied_amount = 0`. فلو حدث ذلك — لأيّ سبب — فالأبناء كانوا
                 | سيأخذون زيادةً **لم يدفعها أحد**. نقيس **ما طُبِّق فعلًا** لا ما
                 | طُلِب، ونُسقِط الحفظ كلّه بالمعاملة الجارية.
                 */
                $applied = round(abs((float) ($transaction?->applied_amount ?? 0)), 2);

                if ($applied !== round((float) $check['overflow'], 2)) {
                    throw ValidationException::withMessages(['shares' => [
                        strtr(setting('goals.vxp_distribution_service.distribute_2', 'الزيادة فوق الوعاء (:p1) لم تُخصَم فعلًا من رصيدك — فلم يُحفَظ التوزيع. قلّل القيم إلى داخل الوعاء أو راجع رصيدك.'), [':p1' => (string) ($this->num($check['overflow']))]),
                    ]]);
                }
            }

            // المنصرف من وعاء البند يعكس ما وُزِّع فعلًا
            if ($parent->work_item_id) {
                $this->syncItemSpent((int) $parent->work_item_id);
            }
        });

        return $check;
    }

    /** المنصرف من وعاء البند = مجموع قيم مهامّه */
    public function syncItemSpent(int $workItemId): void
    {
        $spent = (float) Task::query()->where('work_item_id', $workItemId)->sum('vxp_value');

        WorkItem::query()->whereKey($workItemId)->update(['vxp_spent' => round($spent, 2)]);
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
