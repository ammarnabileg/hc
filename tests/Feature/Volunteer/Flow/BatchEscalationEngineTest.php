<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Escalation;
use App\Models\EscalationStep;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Task;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Tasks\SubtaskBatch;

/**
 * ج-5 — الحالة 9 «مراجعة دفعة الصب-تاسكات» تمرّ **بالمحرّك نفسه** (الدستور 23-2.3-٤ · 23-5).
 *
 * النصّ الحاكم حرفيًّا (23-2.3-٤):
 *   «المراجعة **حالة على محرّك التصعيد — الحالة 9 «مراجعة دفعة الصب-تاسكات»
 *    (جدولها في القسم 5)**: الأبلاين المباشر عنده 24 ساعة ⟵ فاتت؟ تطلع للأبلاين
 *    الأعلى بأثر التباطؤ، وهكذا — **ولا اعتماد تلقائي إلا بعد سقف محرّك التصعيد**:
 *    مشرف عام المتطوّعين نافذته 48 ساعة، وفواتها = **اعتماد الدفعة كاملة تلقائيًّا**.»
 *
 * ونصّ الغياب (23-6): «كلّ نوافذ القرار الواردة إليه **تُوجَّه للبديل مباشرةً**
 * (مراجعات · الحالات التسع · **دفعات الصب-تاسكات**) و**تُحتسَب على البديل**».
 *
 * فالكتابة اليدويّة الموازية للصفّ — بنافذة 24 ثابتة وبلا `HandlerChain` ولا
 * `AbsenceService` — تكسر النصّين معًا: القمّة تأخذ 24 بدل 48، ودفعة الغائب
 * تبقى على مكتبه رغم وجود تفويض.
 */
class BatchEscalationEngineTest extends FlowTestCase
{
    /** دفعة يحفظها المالك: أوّل صاحب قرار = أبلاينه المباشر بنافذة 24 (23-2.3-٤) */
    public function test_batch_opens_on_the_direct_upline_desk_with_the_level_window(): void
    {
        $parent = $this->makeTask($this->owner, ['deadline_at' => now()->addDays(10)]);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(5)->toDateTimeString()],
        ], $this->owner);

        $row = $this->batchRow($parent);

        $this->assertNotNull($row, 'حفظ الدفعة يفتح الحالة 9 على المحرّك.');
        $this->assertSame($this->reviewer->id, (int) $row->current_handler_id);
        $this->assertFalse((bool) $row->is_top_level);
        $this->assertEqualsWithDelta(24, $this->windowHours($row), 0.2);
    }

    /**
     * ⭐ العطب الأوّل: الدفعة التي يحفظها **من أبلاينه هو السقف** لازم تأخذ
     * **48 ساعة** لا 24 — «مشرف عام المتطوّعين نافذته 48 ساعة» (23-2.3-٤).
     */
    public function test_a_batch_landing_on_the_top_gets_the_forty_eight_hour_window(): void
    {
        $parent = $this->makeTask($this->reviewer, [
            'reviewer_id' => $this->top->id,
            'deadline_at' => now()->addDays(10),
        ]);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(5)->toDateTimeString()],
        ], $this->reviewer);

        $row = $this->batchRow($parent);

        $this->assertSame($this->top->id, (int) $row->current_handler_id, 'صاحب القرار هو السقف.');
        $this->assertTrue((bool) $row->is_top_level, 'وعنده تصير النافذة نافذة السقف والتسوية هي المآل.');
        $this->assertEqualsWithDelta(48, $this->windowHours($row), 0.2, 'القمّة نافذتها 48 ساعة نصًّا — لا 24.');
    }

    /**
     * ⭐ العطب الثاني: أبلاين غائب وله بديل مفوَّض ⟵ **الدفعة تصل البديل**
     * وتُحتسَب عليه، ولا تبقى على مكتب الغائب (23-6).
     */
    public function test_a_batch_routes_to_the_delegate_while_the_upline_is_absent(): void
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

        $parent = $this->makeTask($this->owner, ['deadline_at' => now()->addDays(10)]);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(5)->toDateTimeString()],
        ], $this->owner);

        $row = $this->batchRow($parent);

        $this->assertSame(
            $this->top->id,
            (int) $row->current_handler_id,
            'دفعة الغائب تُوجَّه للبديل المفوَّض مباشرةً — لا تبقى على مكتبه.',
        );
    }

    /**
     * ⭐ وأثر المرور بالمحرّك نفسه: خطوة مفتوحة في `escalation_steps` وإشعارٌ
     * لصاحب النافذة — وهما ما تفقده أيّ كتابةٍ يدويّةٍ موازية.
     */
    public function test_the_batch_case_gets_a_real_engine_step_and_a_notification(): void
    {
        $parent = $this->makeTask($this->owner, ['deadline_at' => now()->addDays(10)]);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(5)->toDateTimeString()],
        ], $this->owner);

        $row = $this->batchRow($parent);

        $this->assertSame(
            1,
            EscalationStep::query()->where('escalation_id', $row->id)->whereNull('closed_at')->count(),
            'المحرّك يفتح خطوةً لكلّ مستوًى — وبلا خطوة لا سلّم تصعيد مرئيًّا ولا قياس زمن مراجعة.',
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->reviewer->id,
            'category' => 'escalation',
            'requires_action' => true,
        ]);
    }

    /**
     * والسلّم كاملًا من المحرّك: 24 عند الأبلاين ⟵ فاتت ⟵ تصعد للسقف بـ48
     * ⟵ فاتت ⟵ **اعتماد الدفعة كاملة تلقائيًّا** (23-5 · الصفّ 9).
     */
    public function test_the_batch_climbs_the_ladder_and_settles_as_full_approval(): void
    {
        $parent = $this->makeTask($this->owner, ['deadline_at' => now()->addDays(10)]);

        $created = app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(5)->toDateTimeString()],
        ], $this->owner);

        $row = $this->batchRow($parent);
        $row->forceFill(['window_due_at' => now()->subHour()])->save();

        app(EscalationEngine::class)->run();
        $row->refresh();

        $this->assertSame($this->top->id, (int) $row->current_handler_id, 'فوات النافذة يرفعها للأبلاين الأعلى.');
        $this->assertTrue((bool) $row->is_top_level);
        $this->assertEqualsWithDelta(48, $this->windowHours($row), 0.2);

        $row->forceFill(['window_due_at' => now()->subHour()])->save();
        app(EscalationEngine::class)->run();
        $row->refresh();

        $this->assertSame('auto_settled', $row->status);
        $this->assertSame('approved', $row->decision);
        $this->assertSame('approved', Task::query()->whereKey($created->first()->id)->value('batch_status'));
    }

    // ------------------------------------------------------------------ أدوات

    private function batchRow(Task $parent): ?Escalation
    {
        return Escalation::query()
            ->where('case_type', CaseCatalog::SUBTASK_BATCH)
            ->where('subject_type', $parent->getMorphClass())
            ->where('subject_id', $parent->getKey())
            ->latest('id')
            ->first();
    }

    private function windowHours(Escalation $row): float
    {
        return now()->diffInMinutes($row->window_due_at) / 60;
    }
}
