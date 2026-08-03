<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * دفعة الصب-تاسكات (الدستور 23-2.3 · 23-3.9).
 *
 * ⭐ قيد الحصّة عند التفكيك: **أقصى ديدلاين بين الأبناء + نافذة دمج الأب ≤ ديدلاين الأب**
 *    — وإلّا رُفض الحفظ. فمَن يضخّم ديدلاين مَن تحته يأكل من نافذة دمجه هو.
 * ⭐ وقيد الوعاء: مجموع ما يوزّعه على أبنائه ≤ وعاء مهمّته ناقصَ شريحته المحفوظة.
 */
class SubtaskBatch
{
    public function __construct(private readonly EscalationEngine $engine) {}

    /** نافذة الدمج والتسليم للأب بالساعات (إعداد) */
    public function mergeWindowHours(): int
    {
        return (int) setting('workflow.merge_window_hours', 24);
    }

    /** أدنى شريحة محفوظة للأب من وعاء مهمّته (%) */
    public function parentMinSharePercent(): float
    {
        return (float) setting('workflow.vxp.parent_min_share_percent', 10);
    }

    /** أقصى ديدلاين مسموح للأبناء = ديدلاين الأب − نافذة الدمج */
    public function latestAllowedChildDeadline(Task $parent): ?Carbon
    {
        if (! $parent->deadline_at) {
            return null;
        }

        return Carbon::parse($parent->deadline_at)->subHours($this->mergeWindowHours());
    }

    /**
     * فحص الدفعة قبل الحفظ — ورسالة الرفض تشرح القيد نفسه (2.15-د · 2.17-ب).
     *
     * @param  array<int, array<string, mixed>>  $rows
     *
     * @throws ValidationException
     */
    public function validate(Task $parent, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn ($row) => filled($row['title'] ?? null)));

        if ($rows === []) {
            throw ValidationException::withMessages([
                'subtasks' => 'الدفعة فاضية — اكتب صب-تاسك واحدًا على الأقلّ بعنوانه وديدلاينه.',
            ]);
        }

        $limit = $this->latestAllowedChildDeadline($parent);
        $deadlines = [];

        foreach ($rows as $index => $row) {
            if (blank($row['deadline_at'] ?? null)) {
                throw ValidationException::withMessages([
                    "subtasks.{$index}.deadline_at" => 'كلّ صب-تاسك لازم له ديدلاين داخليّ.',
                ]);
            }

            $deadlines[] = Carbon::parse($row['deadline_at']);
        }

        $maxChild = collect($deadlines)->max();

        if ($limit && $maxChild->greaterThan($limit)) {
            throw ValidationException::withMessages([
                'subtasks' => 'تعذّر الحفظ — القيد: أقصى ديدلاين للأبناء ('
                    .$maxChild->format('Y-m-d H:i').') + نافذة دمجك ('
                    .$this->mergeWindowHours().' ساعة) لازم يكون ≤ ديدلاينك ('
                    .Carbon::parse($parent->deadline_at)->format('Y-m-d H:i')
                    .'). خلّي أقصى ديدلاين للأبناء '.$limit->format('Y-m-d H:i').' أو أقرب.',
            ]);
        }

        $pool = (float) $parent->vxp_value;

        if ($pool > 0) {
            $distributed = collect($rows)->sum(fn ($row) => (float) ($row['vxp_value'] ?? 0));
            $keepShare = $pool * $this->parentMinSharePercent() / 100;

            if ($distributed > $pool - $keepShare + 0.0001) {
                throw ValidationException::withMessages([
                    'subtasks' => 'تعذّر الحفظ — القيد: مجموع VXP الأبناء ('.round($distributed, 2)
                        .') لازم يكون ≤ وعاء مهمّتك ('.round($pool, 2).') ناقصَ شريحتك المحفوظة ('
                        .$this->parentMinSharePercent().'%). وزّع '.round($pool - $keepShare, 2).' كحدّ أقصى.',
                ]);
            }
        }
    }

    /**
     * حفظ الدفعة «للمراجعة»: مسودّة تتقدّم بضغطة واحدة لأبلاين صاحبها،
     * ولا اعتماد تلقائيّ إلّا بعد سقف محرّك التصعيد (23-2.3-4).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, Task>
     */
    public function save(Task $parent, array $rows, User $author): Collection
    {
        $this->validate($parent, $rows);

        $rows = array_values(array_filter($rows, fn ($row) => filled($row['title'] ?? null)));

        return DB::transaction(function () use ($parent, $rows, $author) {
            $created = collect();

            /*
             | ⭐ هل فُكِّكت هذه المهمّة قبلًا؟ يُقرأ **قبل** إنشاء أبناء هذه الدفعة.
             | فواقعة «التفكيك» واحدة: أوّل دفعة. ودفعةٌ ثانية بعد أيّام ليست
             | تفكيكًا جديدًا يُحاسَب بتأخّرٍ لم يكن قائمًا لحظة التفكيك نفسه.
             */
            $alreadyBrokenDown = Task::query()->where('parent_task_id', $parent->id)->exists();

            foreach ($rows as $row) {
                $created->push(Task::create([
                    'title' => $row['title'],
                    'brief' => $row['brief'] ?? null,
                    'deliverable_spec' => $row['deliverable_spec'] ?? null,
                    'deadline_at' => Carbon::parse($row['deadline_at']),
                    'vxp_value' => (float) ($row['vxp_value'] ?? 0),
                    'parent_task_id' => $parent->id,
                    // قاعدة الربط: الصب-تاسك يرث ربط أمّه بالبند تلقائيًّا (23-2.3)
                    'work_item_id' => $parent->work_item_id,
                    'entity_id' => $parent->entity_id,
                    'task_type_id' => $parent->task_type_id,
                    'owner_id' => $author->id,
                    'reviewer_id' => $author->id,
                    'created_by' => $author->id,
                    'status' => TaskStatus::IN_PROGRESS,
                    'batch_status' => 'pending_review',
                    'source' => 'assigned',
                ]));
            }

            /*
             | ⭐ نافذة الدمج **لا تُثبَّت هنا**: عدّاد الأب الشخصيّ يبدأ لحظة
             | اعتماد **آخر ابن** (23-3.9-3) لا لحظة التفكيك — وتثبيتها على
             | ديدلاين الأب كان يجعلها نافذةً وهميّة بلا خصمٍ على فواتها.
             | مكان بدئها: `ReviewService::startParentMergeWindow()`.
             */

            // ونافذة التفكيك نفسها تُحاسَب الآن: −0.2 عن كلّ يوم تأخير بسقف −1 (23-3.9-1)
            $this->chargeBreakdownDelay($parent, $author, $alreadyBrokenDown);

            $this->openBatchEscalation($parent, $author);

            return $created;
        });
    }

    /** آخر موعد لتفكيك المهمّة وتوزيعها — من العمود، وإلّا من لحظة وصولها */
    public function breakdownDueAt(Task $parent): ?Carbon
    {
        if ($parent->breakdown_due_at) {
            return Carbon::parse($parent->breakdown_due_at);
        }

        return $parent->created_at
            ? Carbon::parse($parent->created_at)->addHours($this->breakdownWindowHours())
            : null;
    }

    /** نافذة التفكيك بالساعات — إعداد (2.13) */
    public function breakdownWindowHours(): int
    {
        return (int) setting('workflow.breakdown_window_hours', 24);
    }

    /**
     * ⭐ خصم تأخّر التفكيك (23-3.9-1): «التأخّر عن التفكيك نفسه = −0.2 عن كلّ
     * يوم تأخير — كي لا تكون أوّل حلقة هي عنق الزجاجة الخفيّ — بسقف تراكميّ
     * −1 لكلّ مهمّة، حتى لا يصير التأخّر في التفكيك أقسى من عدم التسليم نفسه».
     * ومثله في 13.4-ن-أ: «تأخّر التفكيك −0.2/يوم بسقف −1»، وفي المصطلحات:
     * «نافذة التفكيك… التأخّر عنها −0.2 عن كلّ يوم».
     *
     * ⭐⭐ وحدة القياس **يومٌ تامّ** لا يومٌ مبدوء — والنصّ هو الحكم:
     * ثلاثة مواضع تقول «عن كلّ **يوم** تأخير»، ولا موضع فيها يقول «أو جزء منه»
     * ولا «يوم مبدوء». والدستور حين يريد شريحةً أقلّ من يومٍ **يسمّيها صراحةً**
     * كما في سلّم التسليم نفسه: «تأخير **أقلّ من 24 ساعة** −0.25» (13.4-ن-أ) —
     * فغيابُ نظيرها هنا نصٌّ لا سهو. فالعدّ = عدد الأيّام (24 ساعة) **الكاملة**
     * المنقضية بعد `breakdownDueAt`، وما دون اليوم الأوّل لا خصم فيه.
     *
     * وكانت `ceil()` على ساعاتٍ **كسريّة** تجعل يومًا واحدًا يومين (−0.4) وثلاثةً
     * أربعةً (−0.8) — ضِعفَ المنصوص؛ والسقف وحده هو ما كان يستر الأثر.
     *
     * ولماذا يُحسَب لحظة التفكيك لا كلّ يوم؟ لأنّ المجموع واحد، ولأنّ مَن لم
     * يفكّك أصلًا يمسكه **مسار عدم التسليم** عند ديدلاينه — فلا يُخصَم مرّتين
     * ولا يُعاقَب مَن اختار التنفيذ الذاتيّ (حقّه المنصوص في 23-3.1).
     */
    private function chargeBreakdownDelay(Task $parent, User $author, bool $alreadyBrokenDown = false): void
    {
        // الواقعة = **أوّل** تفكيك. دفعةٌ لاحقة على نفس المهمّة ليست واقعةً ثانية.
        if ($alreadyBrokenDown) {
            return;
        }

        $due = $this->breakdownDueAt($parent);

        if (! $due || ! $due->isPast()) {
            return;
        }

        // أيّامٌ **تامّة** بعد الموعد — لا يومَ مبدوءًا ولا كسرًا يُجبَر لأعلى
        $days = (int) floor($due->diffInHours(now(), absolute: true) / 24);

        if ($days < 1) {
            return;
        }

        $perDay = (float) rep_rule('task.breakdown_delay_per_day');
        $cap = (float) rep_rule('task.breakdown_delay_cap');
        $value = max($cap, $perDay * $days);

        if ($value == 0.0) {
            return;
        }

        RepOnce::record(
            'task.breakdown_delay:'.$parent->id,
            fn () => app(LedgerBridge::class)->record(
                $author,
                'rep',
                $value,
                'task',
                'تأخّر التفكيك '.$days.' يومًا على: '.$parent->title,
                $parent,
                $parent->entity_id,
            ),
        );
    }

    /**
     * مراجعة الدفعة حالةٌ على محرّك التصعيد — **الحالة 9** (23-2.3-٤ · 23-5).
     *
     * ⭐ ولا تُكتَب بيدنا. النصّ يقول حرفيًّا: «المراجعة **حالة على محرّك
     * التصعيد** — الحالة 9 «مراجعة دفعة الصب-تاسكات» (جدولها في القسم 5):
     * الأبلاين المباشر عنده 24 ساعة ⟵ فاتت؟ تطلع للأبلاين الأعلى بأثر
     * التباطؤ… مشرف عام المتطوّعين نافذته **48 ساعة**».
     *
     * والكتابة اليدويّة الموازية كانت تكسر ثلاثة أشياء دفعةً واحدة:
     *  1. **النافذة**: 24 ثابتة للجميع — فالقمّة تأخذ 24 بدل 48، ويسقط معها
     *     `is_top_level` فلا تعرف الدورة متى تُطبِّق «اعتماد الدفعة كاملة».
     *  2. **صاحب القرار**: `reviewer_id` خامًا بلا `HandlerChain` ولا
     *     `AbsenceService` — فدفعة الغائب تبقى على مكتبه رغم وجود مفوَّض،
     *     والنصّ يقول: «كلّ نوافذ القرار الواردة إليه تُوجَّه للبديل مباشرةً
     *     (مراجعات · الحالات التسع · **دفعات الصب-تاسكات**)» (23-6).
     *  3. **الأثر**: بلا خطوة في `escalation_steps` ولا إشعار — فلا سلّم تصعيد
     *     مرئيّ ولا «متوسّط زمن مراجعته» الذي ينصّ عليه 23-2.3-٤.
     */
    private function openBatchEscalation(Task $parent, User $author): void
    {
        if (! Schema::hasTable('escalations')) {
            return;
        }

        $this->engine->open(
            CaseCatalog::SUBTASK_BATCH,
            $parent,
            $author,
            [
                'parent_task_id' => $parent->id,
                'batch_size' => Task::query()
                    ->where('parent_task_id', $parent->id)
                    ->where('batch_status', 'pending_review')
                    ->count(),
            ],
        );
    }
}
