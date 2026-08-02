<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Escalation;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Objection;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Contributions\ReviewService;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Escalation\HandlerChain;
use App\Services\Volunteer\Objections\ObjectionService;
use App\Services\Volunteer\Tasks\NoDeliverySweeper;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use Illuminate\Support\Facades\Artisan;

/**
 * الأعطال المثبَتة بالتشغيل في دورة العمل (الدستور 23):
 * صفٌّ تالف يُسقِط المحرّك · خصم مزدوج على واقعة واحدة · مسار عدم التسليم
 * الغائب · نافذة الدمج · وضع «غائب» · أثر التباطؤ لكلّ مستوًى.
 */
class WorkflowGapsTest extends FlowTestCase
{
    private function engine(): EscalationEngine
    {
        return app(EscalationEngine::class);
    }

    private function repRows(User $user): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'rep'))
            ->sum('amount');
    }

    // ------------------------------------------------------------ 1) صلابة المحرّك

    /**
     * ⭐ حالةٌ تالفةُ النوع **لا تُسقِط باقي الدورة**: تُعزَل ويكمل الباقي —
     * فلا يوقف اعتراضٌ واحدٌ عالقٌ المنصّةَ كلَّها إلى الأبد.
     */
    public function test_a_broken_case_type_does_not_take_the_whole_cycle_down(): void
    {
        $broken = Escalation::create([
            'case_type' => 'objection', // نوع لا يعرفه CaseCatalog
            'subject_type' => (new Task)->getMorphClass(),
            'subject_id' => $this->makeTask()->id,
            'requested_by' => $this->owner->id,
            'current_handler_id' => $this->top->id,
            'level' => 1,
            'window_due_at' => now()->subHour(),
            'is_top_level' => true,
            'status' => 'open',
        ]);

        $task = $this->makeTask();
        $healthy = $this->engine()->open(CaseCatalog::EXTENSION, $task, $this->owner, [], $this->top);
        $healthy->forceFill(['window_due_at' => now()->subHour()])->save();

        $exit = Artisan::call('escalations:run');

        $this->assertSame(0, $exit, 'الأمر لا يجوز أن يخرج بكود 1 بسبب صفٍّ واحد تالف.');
        $this->assertSame('failed', $broken->refresh()->status);
        $this->assertSame('auto_settled', $healthy->refresh()->status, 'باقي الحالات تُعالَج رغم التالفة.');
    }

    /** والاعتراض لم يعد يُكتَب في جدول الحالات التسع أصلًا — مساره مستقلّ (23-6) */
    public function test_objections_escalate_on_their_own_path(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->owner->id,
            'currency_id' => \App\Models\Currency::where('code', 'rep')->value('id'),
            'amount' => -0.25,
            'layer' => 'volunteer',
            'source' => 'task',
            'reason' => 'تأخير',
        ]);

        $result = app(ObjectionService::class)->file($transaction, $this->owner, 'الخصم ده مش مظبوط.');

        $this->assertTrue($result['ok']);
        $this->assertDatabaseMissing('escalations', ['case_type' => 'objection']);

        $objection = $result['objection'];
        $this->assertSame($this->reviewer->id, (int) $objection->current_handler_id);

        $objection->forceFill(['sla_due_at' => now()->subHour()])->save();

        app(ObjectionService::class)->runOverdue();

        $objection->refresh();
        $this->assertSame($this->top->id, (int) $objection->current_handler_id);
        $this->assertSame('escalated', $objection->status);
        $this->assertTrue($objection->sla_due_at->isFuture());
    }

    // ------------------------------------------------------------ 2) لا خصم مزدوج

    /** ⭐ تسليم واحد ⟵ **حركة Rep واحدة**: التسليم يكتبها، والاعتماد لا يعيدها */
    public function test_one_delivery_writes_exactly_one_rep_movement(): void
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(2)]);

        app(TaskWorkflow::class)->deliver($task, $this->owner, ['body' => 'المخرج جاهز.']);
        app(ReviewService::class)->approveTask($task->refresh(), $this->reviewer);

        $rows = Transaction::query()
            ->where('user_id', $this->owner->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'rep'))
            ->get();

        $this->assertCount(1, $rows, 'واقعة واحدة ⟵ حركة واحدة (23-6).');
        $this->assertSame(rep_rule('task.early'), (float) $rows->first()->amount);
    }

    // ------------------------------------------------------------ 3) مسار عدم التسليم

    /** ⭐ مهمّة عاديّة فائتة ⟵ خصم **واحد** وتصعيد ومالك جديد */
    public function test_a_plain_missed_task_gets_a_deduction_an_escalation_and_a_new_owner(): void
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->subDays(3)]);

        app(NoDeliverySweeper::class)->run();
        app(NoDeliverySweeper::class)->run(); // التشغيل الثاني لا يكرّر شيئًا

        $task->refresh();
        $this->assertSame('no_delivery', $task->status);
        $this->assertEqualsWithDelta(rep_rule('task.no_delivery'), $this->repRows($this->owner), 0.001);

        $escalation = Escalation::query()
            ->where('case_type', CaseCatalog::NO_DELIVERY)
            ->where('subject_id', $task->id)
            ->firstOrFail();

        $this->assertSame($this->reviewer->id, (int) $escalation->current_handler_id, 'ترجع للأبلاين المباشر.');

        // ينفّذها بنفسه ⟵ صار هو المالك الجديد (23-3.8)
        $this->engine()->decide($escalation, $this->reviewer, 'self_execute');

        $this->assertSame($this->reviewer->id, (int) $task->refresh()->owner_id);
        $this->assertSame('in_progress', $task->status);
    }

    /** ويصعد **بلا خصم تباطؤ** — مهمّة يتيمة لا قرار متأخّر */
    public function test_missed_task_escalation_carries_no_slowdown_penalty(): void
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->subDays(3)]);

        app(NoDeliverySweeper::class)->run();

        $escalation = Escalation::query()->where('subject_id', $task->id)->firstOrFail();
        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();

        $this->engine()->run();

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);
    }

    // ------------------------------------------------------------ 4) نافذة الدمج

    /** ⭐ اعتماد آخر ابن يبدأ عدّاد الأب الشخصيّ فورًا (23-3.9-3) */
    public function test_approving_the_last_child_starts_the_parent_merge_window(): void
    {
        $parent = $this->makeTask(attributes: ['deadline_at' => now()->addDays(10)]);
        $child = $this->makeTask($this->contributor, [
            'parent_task_id' => $parent->id,
            'deadline_at' => now()->addDay(),
        ]);

        $this->assertNull($parent->merge_window_at);

        app(TaskWorkflow::class)->deliver($child, $this->contributor, ['body' => 'خلصت.']);
        app(ReviewService::class)->approveTask($child->refresh(), $this->owner);

        $parent->refresh();

        $this->assertNotNull($parent->merge_window_at, 'العدّاد يبدأ لحظة اعتماد آخر ابن لا لحظة التفكيك.');
        $this->assertTrue($parent->merge_window_at->lessThan($parent->deadline_at));

        // وفواتها خصمه هو — بسلّم 3.7 نفسه
        $parent->forceFill(['merge_window_at' => now()->subDays(2)])->save();
        app(NoDeliverySweeper::class)->run();

        $this->assertEqualsWithDelta(rep_rule('task.no_delivery'), $this->repRows($this->owner), 0.001);
    }

    /** وعلم «متأخّر بسبب ابن» يُقرأ فعلًا عند الخصم — فيحمي رافعه (23-3.9-4) */
    public function test_the_late_due_to_child_flag_protects_its_raiser(): void
    {
        $parent = $this->makeTask(attributes: [
            'deadline_at' => now()->subDays(3),
            'late_due_to_child' => true,
        ]);

        app(NoDeliverySweeper::class)->run();

        $this->assertSame('no_delivery', $parent->refresh()->status, 'المهمّة تدور على مالك جديد رغم العلم.');
        $this->assertEqualsWithDelta(0.0, $this->repRows($this->owner), 0.001, 'ومَن بلّغ مبكّرًا لا يُخصَم منه شيء.');
    }

    // ------------------------------------------------------------ 5) وضع «غائب»

    /** ⭐ غائب وله بديل ⟵ القرار **للبديل** لا للغائب */
    public function test_decisions_route_to_the_delegate_while_the_upline_is_absent(): void
    {
        $reviewerMembership = Membership::query()->where('user_id', $this->reviewer->id)->firstOrFail();
        $topMembership = Membership::query()->where('user_id', $this->top->id)->firstOrFail();

        MembershipAbsence::create([
            'membership_id' => $reviewerMembership->id,
            'delegate_membership_id' => $topMembership->id,
            'from_date' => today()->subDay(),
            'to_date' => today()->addDays(3),
            'created_by' => $this->top->id,
        ]);

        $handler = app(HandlerChain::class)->firstHandlerFor($this->owner, $this->entity->id);

        $this->assertSame($this->top->id, (int) $handler?->id, 'نوافذ القرار تُوجَّه للبديل مباشرةً.');

        // ولا يقع على الغائب أيّ أثر تباطؤ
        $task = $this->makeTask();
        $escalation = $this->engine()->open(CaseCatalog::EXTENSION, $task, $this->owner, [], $this->reviewer);
        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();

        $this->engine()->run();

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);
    }

    /** والغائب لا تُسنَد إليه مهامّ ولا تمسّه مسحة عدم التسليم */
    public function test_the_absent_owner_is_skipped_by_the_missed_task_sweep(): void
    {
        $ownerMembership = Membership::query()->where('user_id', $this->owner->id)->firstOrFail();
        $reviewerMembership = Membership::query()->where('user_id', $this->reviewer->id)->firstOrFail();

        MembershipAbsence::create([
            'membership_id' => $ownerMembership->id,
            'delegate_membership_id' => $reviewerMembership->id,
            'from_date' => today()->subDay(),
            'to_date' => today()->addDays(3),
            'created_by' => $this->top->id,
        ]);

        $this->makeTask(attributes: ['deadline_at' => now()->subDays(3)]);

        app(NoDeliverySweeper::class)->run();

        $this->assertEqualsWithDelta(0.0, $this->repRows($this->owner), 0.001, 'لا نزيف خصومات على غائب معذور.');
    }

    // ------------------------------------------------------------ 6) أثر التباطؤ لكلّ مستوًى

    /** ⭐ المستوى الثاني الفائت يأخذ أثر التباطؤ هو الآخر — بلا تخصيص (23-5) */
    public function test_every_level_that_misses_its_window_takes_the_slowdown(): void
    {
        // السلسلة كاملة: المساهم (كوردنيتور) ⟵ المالك ⟵ المراجِع ⟵ السقف
        $task = $this->makeTask($this->contributor);
        $escalation = $this->engine()->open(CaseCatalog::EXTENSION, $task, $this->contributor);

        $this->assertSame($this->owner->id, (int) $escalation->current_handler_id);

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();
        $this->engine()->run();

        // المستوى الثاني: على مكتب المراجِع — ويفوّت نافذته هو الآخر
        $this->assertSame($this->reviewer->id, (int) $escalation->refresh()->current_handler_id);

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();
        $this->engine()->run();

        $this->assertDatabaseHas('transactions', ['user_id' => $this->owner->id, 'source' => 'escalation.slowdown']);
        $this->assertDatabaseHas('transactions', ['user_id' => $this->reviewer->id, 'source' => 'escalation.slowdown']);
    }

    // ------------------------------------------------------------ 7) الحالة 7 المعلَّقة

    /** خصم التباطؤ في بلاغ الرابط **معلَّق**: «الرابط يعمل» يشيله عن الكلّ */
    public function test_broken_link_slowdown_is_suspended_until_someone_verifies(): void
    {
        $task = $this->makeTask();
        $escalation = $this->engine()->open(CaseCatalog::BROKEN_LINK, $task, $this->owner);

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();
        $this->engine()->run();

        // لم يُكتَب شيء في الدفتر بعد — القيمة معلَّقة
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);

        $this->engine()->decide($escalation->refresh(), $this->top, 'link_works');

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);
    }

    /** ولو تأكّد العطل يُعتمَد المعلَّق في سجلّ مَن فوّتوا نوافذهم */
    public function test_confirmed_broken_link_applies_the_suspended_slowdown(): void
    {
        $task = $this->makeTask();
        $escalation = $this->engine()->open(CaseCatalog::BROKEN_LINK, $task, $this->owner);

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();
        $this->engine()->run();

        $this->engine()->decide($escalation->refresh(), $this->top, 'link_broken');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);
    }

    // ------------------------------------------------------------ 8) التودو الشخصيّ

    /** التودو لصاحبه وحده — والمالك الجديد لا يرث قائمة السابق (23-2.1) */
    public function test_todos_belong_to_their_writer_only(): void
    {
        $this->grant($this->owner, 'todos.create', 'todos.edit', 'todos.delete');
        $this->grant($this->reviewer, 'todos.create', 'todos.edit', 'todos.delete');

        $task = $this->makeTask();

        $this->actingAs($this->owner)
            ->post(route('volunteer.tasks.todos.store', $task), ['body' => 'أراجع المصادر'])
            ->assertRedirect();

        $todo = \App\Models\TaskTodo::query()->firstOrFail();
        $this->assertSame($this->owner->id, (int) $todo->user_id);

        // انتقلت الملكيّة ⟵ المالك الجديد لا يحرّر قائمة السابق
        $task->forceFill(['owner_id' => $this->reviewer->id])->save();

        $this->actingAs($this->reviewer)
            ->post(route('volunteer.tasks.todos.toggle', [$task, $todo]))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ 9) شاشة Rep بلا حركة

    /** ⭐ شاشة «درجة الالتزام» تفتح لمتطوّع **بلا أيّ حركة** — والمنحنى مسطَّح على الصفر */
    public function test_the_rep_screen_opens_for_a_volunteer_with_no_movements_at_all(): void
    {
        $this->grant($this->contributor, 'rep_transactions.view');

        $this->actingAs($this->contributor)
            ->get(route('volunteer.performance.rep'))
            ->assertOk();
    }
}
