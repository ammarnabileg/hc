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
 * والتجاوز **يُرفَض** إلّا بموافقة صريحة على أن يأتي الفرق من **رصيده الشخصيّ**.
 */
class VxpDistributionService
{
    public const CURRENCY = 'vxp';

    /** أدنى شريحة محفوظة للأب (%) — إعداد لا رقم محروق (2.13) */
    public function parentMinSharePercent(): float
    {
        return (float) setting('workflow.vxp.parent_min_share_percent', 10);
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
                $errors[] = 'قيمة سالبة غير مقبولة — اكتب صفرًا أو أكثر.';
                break;
            }
        }

        $overflow = round(max(0, $total - $max), 2);

        if ($overflow > 0 && ! $consentPersonal) {
            if ($total > $pool) {
                // القيد (أ): مجموع الأبناء ≤ وعاء المهمّة
                $errors[] = 'مجموع ما توزّعه ('.$this->num($total).') أكبر من وعاء المهمّة ('.$this->num($pool).') — قلّل القيم أو وافق صراحةً على الخصم من رصيدك الشخصيّ.';
            } else {
                // القيد (ب): شريحة الأب المحفوظة
                $errors[] = 'لازم تحتفظ بـ'.$this->num($this->parentMinSharePercent()).'% على الأقلّ من الوعاء لشريحتك ('.$this->num($this->reservedShare($parent)).' نقطة) — أقصى ما توزّعه '.$this->num($max).'.';
            }
        }

        if ($overflow > 0 && $consentPersonal) {
            $balance = $payer ? Integrations::balance($payer, self::CURRENCY) : 0.0;

            if ($balance < $overflow) {
                $errors[] = 'رصيدك الشخصيّ ('.$this->num($balance).') لا يكفي الفرق المطلوب ('.$this->num($overflow).') — قلّل القيم.';
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

            if ($check['overflow'] > 0 && $payer) {
                // الزيادة فوق الوعاء من الرصيد الشخصيّ بموافقة صريحة (23 — 3.9-٥)
                $parent->forceFill(['personal_vxp_top_up' => $check['overflow']])->save();

                Integrations::debit(
                    user: $payer,
                    currencyCode: self::CURRENCY,
                    amount: $check['overflow'],
                    source: 'task',
                    reference: $parent,
                    reason: 'زيادة فوق وعاء المهمّة بموافقة صريحة من الرصيد الشخصيّ',
                    createdBy: $payer->id,
                );
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
