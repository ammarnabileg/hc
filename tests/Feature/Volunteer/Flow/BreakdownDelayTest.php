<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ⚖️ خصم تأخّر التفكيك — بالنصّ حرفًا بحرف (الدستور 23-3.9-١ · 13.4-ن-أ · المصطلحات).
 *
 * النصّ الحاكم (23-3.9-١): «التأخّر عن التفكيك نفسه = **خصم −0.2 من درجة
 * الالتزام عن كلّ يوم تأخير** … **بسقف تراكميّ −1 لكل مهمّة**».
 *
 * ثلاثة مواضع تقول «عن كلّ **يوم**»، ولا موضع يقول «أو جزء منه» ولا «يوم مبدوء»
 * — والدستور حين يريد شريحةً دون اليوم يسمّيها («تأخير **أقلّ من 24 ساعة**
 * −0.25» في سلّم التسليم). فالوحدة يومٌ **تامّ**، والعدّة تبدأ من **آخر موعد
 * للتفكيك** (نافذة 24 ساعة من الإشعار — إعداد) لا من ميلاد الصفّ.
 *
 * والقيم كلّها من `rep_rule()` — لا رقم محروق (2.13).
 */
class BreakdownDelayTest extends FlowTestCase
{
    /** الخصم كما تقرؤه القاعدة نفسها — لا كما يظنّه الاختبار */
    private function repOf(User $user): float
    {
        return (float) DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('transactions.user_id', $user->id)
            ->where('currencies.code', 'rep')
            ->sum('transactions.amount');
    }

    /** يفكّك مهمّةً فات موعدُ تفكيكها بالمقدار المطلوب، ويُرجع ما نزل على المفكِّك */
    private function breakDownLateBy(Carbon $due, ?User $author = null): float
    {
        $author ??= $this->makeUser('مفكِّك-'.str()->random(5));

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill(['owner_id' => $author->id, 'breakdown_due_at' => $due])->save();

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'شريحة', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        return $this->repOf($author);
    }

    private function perDay(): float
    {
        return (float) rep_rule('task.breakdown_delay_per_day');
    }

    // ------------------------------------------------------- المعدّل المنصوص

    /** ⭐ «−0.2 عن كلّ يوم تأخير» — يومٌ واحدٌ يساوي **معدّلًا واحدًا** لا اثنين */
    public function test_one_day_late_costs_exactly_one_days_rate(): void
    {
        $this->assertEqualsWithDelta(
            $this->perDay(),
            $this->breakDownLateBy(now()->subDay()),
            0.0001,
            'يوم تأخير = خصم يومٍ واحد. (كانت `ceil()` على ساعاتٍ كسريّة تجعله يومين.)',
        );
    }

    /** وثلاثة أيّام = ثلاثة معدّلات بالضبط */
    public function test_three_days_late_cost_exactly_three_days_rates(): void
    {
        $this->assertEqualsWithDelta(
            $this->perDay() * 3,
            $this->breakDownLateBy(now()->subDays(3)),
            0.0001,
            'ثلاثة أيّام = ثلاثة معدّلات — لا أربعة.',
        );
    }

    // --------------------------------------------------- اليوم المبدوء ليس يومًا

    /**
     * ⭐ النصّ يقول «عن كلّ **يوم** تأخير» ولا يقول «أو جزء منه»: فدقيقةٌ واحدة
     * بعد الموعد **ليست يومًا**، ولا حركة أصلًا في السجلّ.
     */
    public function test_a_started_day_is_not_a_day(): void
    {
        $this->assertSame(0.0, $this->breakDownLateBy(now()->subMinute()), 'دقيقة ليست يومًا.');
        $this->assertSame(
            0.0,
            $this->breakDownLateBy(now()->subHours(23)->subMinutes(59)),
            'و23:59 ليست يومًا — الجَبر لأعلى اختراعٌ على النصّ.',
        );
    }

    /** وأوّل لحظةٍ يستحقّ فيها الخصم: تمام اليوم — لا قبله */
    public function test_the_charge_begins_at_the_first_complete_day(): void
    {
        $this->assertEqualsWithDelta(
            $this->perDay(),
            $this->breakDownLateBy(now()->subHours(24)->subMinute()),
            0.0001,
            'يومٌ تامّ ودقيقة = معدّل يومٍ واحد.',
        );

        $this->assertEqualsWithDelta(
            $this->perDay() * 2,
            $this->breakDownLateBy(now()->subHours(48)->subMinute()),
            0.0001,
            'ويومان تامّان = معدّلان.',
        );
    }

    /** ومَن فكّك في وقته لا يُخصَم منه شيء */
    public function test_breaking_down_inside_the_window_costs_nothing(): void
    {
        $this->assertSame(0.0, $this->breakDownLateBy(now()->addHours(3)));
    }

    // --------------------------------------------------------------- السقف

    /** ⭐ «بسقف تراكميّ −1 لكلّ مهمّة» — يظلّ مفروضًا بعد الإصلاح */
    public function test_the_cumulative_cap_still_holds(): void
    {
        $cap = (float) rep_rule('task.breakdown_delay_cap');

        $this->assertEqualsWithDelta($cap, $this->breakDownLateBy(now()->subDays(10)), 0.0001);
        $this->assertEqualsWithDelta($cap, $this->breakDownLateBy(now()->subDays(90)), 0.0001);

        // وأوّل يومٍ يبلغ السقف يبلغه بالضبط ولا يتخطّاه
        $daysToCap = (int) ceil($cap / $this->perDay());
        $this->assertEqualsWithDelta($cap, $this->breakDownLateBy(now()->subDays($daysToCap)), 0.0001);
        $this->assertEqualsWithDelta(
            $this->perDay() * ($daysToCap - 1),
            $this->breakDownLateBy(now()->subDays($daysToCap - 1)),
            0.0001,
            'واليوم الذي قبله دون السقف — فالسقف حدٌّ لا قيمةٌ ثابتة.',
        );
    }

    // ----------------------------------------------------- لا خصم مزدوج

    /**
     * ⭐ الواقعة واحدة: **أوّل تفكيك**. دفعةٌ ثانية بعد أيّام لا تُحاسَب بتأخّرٍ
     * لم يكن قائمًا لحظة التفكيك — وإلّا وقع الخصم مرّتين على واقعةٍ واحدة.
     */
    public function test_the_same_breakdown_is_never_charged_twice(): void
    {
        $author = $this->makeUser('مفكِّك مرّتين');

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill(['owner_id' => $author->id, 'breakdown_due_at' => now()->subDays(2)])->save();

        $batch = app(SubtaskBatch::class);

        $batch->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $first = $this->repOf($author);
        $this->assertEqualsWithDelta($this->perDay() * 2, $first, 0.0001);

        $batch->save($parent->refresh(), [
            ['title' => 'ابن ثانٍ', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $this->assertEqualsWithDelta($first, $this->repOf($author), 0.0001, 'الدفعة الثانية لا تكرّر الخصم.');
    }

    /**
     * ⭐ والحزام الثاني: **واقعة واحدة ⟵ حركة واحدة** (23 — القسم 6). حتى لو
     * زال أثر التفكيك الأوّل (حُذفت شرائحه) وفُكِّكت المهمّة من جديد، فالخصم
     * على تأخّر تفكيك هذه المهمّة **لا يُكتَب مرّتين** — `RepOnce` بمفتاح الواقعة.
     */
    public function test_the_event_key_blocks_a_second_charge_even_when_the_first_batch_is_gone(): void
    {
        $author = $this->makeUser('مفكِّك بعد حذف');

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill(['owner_id' => $author->id, 'breakdown_due_at' => now()->subDays(2)])->save();

        $batch = app(SubtaskBatch::class);

        $batch->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $first = $this->repOf($author);
        $this->assertEqualsWithDelta($this->perDay() * 2, $first, 0.0001);

        // زال أثر التفكيك الأوّل تمامًا — فحارس «أوّل تفكيك» لا يرى شيئًا
        Task::query()->where('parent_task_id', $parent->id)->delete();
        $this->assertFalse(Task::query()->where('parent_task_id', $parent->id)->exists());

        $batch->save($parent->refresh(), [
            ['title' => 'ابن بديل', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $this->assertEqualsWithDelta(
            $first,
            $this->repOf($author),
            0.0001,
            'مفتاح الواقعة يمنع الحركة الثانية على نفس المهمّة.',
        );
    }

    /**
     * ⭐ والثغرة المقابلة: مَن فكّك **داخل نافذته** (بلا خصم) ثمّ أضاف دفعةً
     * ثانية بعد أيّام — لا يُخصَم بأثرٍ رجعيّ على تفكيكٍ وقع في وقته.
     */
    public function test_an_on_time_breakdown_is_not_charged_retroactively_by_a_later_batch(): void
    {
        $author = $this->makeUser('مفكِّك في وقته');

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill(['owner_id' => $author->id, 'breakdown_due_at' => now()->addHours(6)])->save();

        $batch = app(SubtaskBatch::class);

        $batch->save($parent, [
            ['title' => 'ابن في الوقت', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $this->assertSame(0.0, $this->repOf($author), 'فكّك داخل نافذته ⟵ لا خصم.');

        // ثمّ فاتت النافذة بأيّام، وأضاف شريحةً أخرى
        $parent->refresh()->forceFill(['breakdown_due_at' => now()->subDays(4)])->save();

        $batch->save($parent->refresh(), [
            ['title' => 'ابن متأخّر', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $this->assertSame(0.0, $this->repOf($author), 'ولا خصم بأثرٍ رجعيّ على تفكيكٍ وقع في وقته.');
    }

    /** والعدّة من **الموعد المختوم** لا من ميلاد الصفّ (23-3.9-١) */
    public function test_the_count_starts_from_the_stamped_due_moment(): void
    {
        $author = $this->makeUser('مفكِّك على صفٍّ قديم');

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill([
            'owner_id' => $author->id,
            'created_at' => now()->subDays(20),          // صفٌّ قديم جدًّا
            'breakdown_due_at' => now()->addHours(10),   // ونافذةٌ لم تُفتَح بعد
        ])->save();

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'شريحة', 'deadline_at' => now()->addDays(20)->toDateTimeString()],
        ], $author);

        $this->assertSame(0.0, $this->repOf($author), 'العدّ من الموعد لا من ميلاد الصفّ.');
    }

    /** ولا يُفكَّك حسابٌ على مهمّةٍ بلا موعدٍ ولا ميلاد */
    public function test_a_task_without_any_due_moment_is_never_charged(): void
    {
        $author = $this->makeUser('بلا موعد');

        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(30)]);
        $parent->forceFill(['owner_id' => $author->id])->save();

        $this->assertNotNull(app(SubtaskBatch::class)->breakdownDueAt($parent));

        Task::query()->where('id', $parent->id)->update(['created_at' => null]);

        $this->assertNull(app(SubtaskBatch::class)->breakdownDueAt($parent->refresh()));
    }
}
