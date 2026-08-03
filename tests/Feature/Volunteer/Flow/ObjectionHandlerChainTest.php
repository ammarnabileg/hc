<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Objection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Objections\ObjectionService;
use App\Services\Volunteer\Retention\SuspensionService;

/**
 * ⭐ **مكتب الاعتراض يُحسَب بـ`HandlerChain` وحدها** (23-6 · 23-0.2-4 · 13.4-ط).
 *
 * النصّ (23-6 — وضع «غائب» والتفويض المؤقّت): «أثره خلال الفترة: **كلّ نوافذ
 * القرار الواردة إليه تُوجَّه للبديل مباشرةً** (مراجعات · الحالات التسع · دفعات
 * الصب-تاسكات) و**تُحتسَب على البديل** · **ولا يقع على الغائب أيّ أثر تباطؤ**».
 *
 * وكان `ObjectionService` يقرأ `MeetingScope::directManager()` — سلسلةً خامًّا
 * لا تعرف الغياب ولا التعليق — فيقف الاعتراض على **مكتب غائبٍ له بديل** حتى
 * تفوت مهلته، ثمّ يصعد **بفوات المهلة لا بقرار**: أثرُ تباطؤٍ على غائبٍ معذور،
 * وهو عين ما وُجِد وضعُ «غائب» ليمنعه.
 */
class ObjectionHandlerChainTest extends FlowTestCase
{
    /** ⭐ الاعتراض يُفتَح على **بديل الغائب** لا على الغائب */
    public function test_a_new_objection_lands_on_the_delegate_not_on_the_absent_upline(): void
    {
        $deputy = $this->makeUser('البديل');
        $this->makeMembership($deputy, 'team_leader', $this->membershipOf($this->reviewer));

        $this->markAbsent($this->owner, $deputy);

        $objection = $this->fileObjection();

        $this->assertSame(
            $deputy->id,
            (int) $objection->current_handler_id,
            'الاعتراض فُتِح على مكتب غائبٍ له بديل — والنصّ يوجّه نوافذه للبديل مباشرةً.',
        );
        $this->assertNotSame($this->owner->id, (int) $objection->current_handler_id);
    }

    /** والتصعيد بفوات المهلة يتخطّى الغائب كذلك — لا يقف عليه ولا يعود إليه */
    public function test_the_overdue_escalation_skips_the_absent_upline(): void
    {
        $deputy = $this->makeUser('البديل');
        $this->makeMembership($deputy, 'team_leader', $this->membershipOf($this->reviewer));

        $objection = $this->fileObjection();
        $this->assertSame($this->owner->id, (int) $objection->current_handler_id);

        // الآن يغيب المالك ⟵ نافذته للبديل
        $this->markAbsent($this->owner, $deputy);

        $objection->forceFill(['sla_due_at' => now()->subHour()])->save();
        app(ObjectionService::class)->runOverdue();

        $this->assertSame(
            $this->reviewer->id,
            (int) $objection->fresh()->current_handler_id,
            'التصعيد وقف عند مستوًى خطأ.',
        );
    }

    /** ⭐ **والمعلَّق عند −10 يُتخطّى لمن يغطّي بوزشنه** (23-0.2-4) */
    public function test_the_escalation_skips_a_suspended_upline_to_his_cover(): void
    {
        $objection = $this->fileObjection();

        app(SuspensionService::class)->apply($this->reviewer->fresh());

        $objection->forceFill([
            'current_handler_id' => $this->owner->id,
            'sla_due_at' => now()->subHour(),
        ])->save();

        app(ObjectionService::class)->runOverdue();

        $this->assertSame(
            $this->top->id,
            (int) $objection->fresh()->current_handler_id,
            'صعد الاعتراض إلى مكتبٍ معلَّق — وهو مكتبٌ لا صاحب له طوال التحقيق.',
        );
    }

    /**
     * ⭐ **السلّم المعروض يطابق الحركة** — لأنّه يُبنى بنفس الدالّة.
     * ولولا ذلك لَما وجد المعترِض مكتبَه الحاليّ في السلّم أصلًا.
     */
    public function test_the_visible_ladder_marks_the_real_current_desk(): void
    {
        $deputy = $this->makeUser('البديل');
        $this->makeMembership($deputy, 'team_leader', $this->membershipOf($this->reviewer));

        $this->markAbsent($this->owner, $deputy);

        $objection = $this->fileObjection();
        $ladder = app(ObjectionService::class)->ladder($objection->fresh());

        $current = $ladder->firstWhere('is_current', true);

        $this->assertNotNull($current, 'السلّم لا يعلّم أيّ مستوًى بأنّه الحاليّ — فالمعترِض يظنّ اعتراضه ضاع.');
        $this->assertSame($deputy->id, (int) $current['user']->id);
        $this->assertNotContains(
            $this->owner->id,
            $ladder->pluck('user.id')->map(fn ($id) => (int) $id)->all(),
            'السلّم يرسم الغائب وهو ليس صاحب أيّ نافذة.',
        );
    }

    /** ولا يُكتَب للاعتراض صفٌّ في `escalations` — مسارٌ قائم بذاته (23-6) */
    public function test_the_objection_still_stays_off_the_escalation_engine(): void
    {
        $objection = $this->fileObjection();

        $objection->forceFill(['sla_due_at' => now()->subHour()])->save();
        app(ObjectionService::class)->runOverdue();

        $this->assertDatabaseCount('escalations', 0);
    }

    // ------------------------------------------------------------------ أدوات

    private function membershipOf(User $user): Membership
    {
        return Membership::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
    }

    private function markAbsent(User $user, User $delegate): MembershipAbsence
    {
        return MembershipAbsence::create([
            'membership_id' => $this->membershipOf($user)->id,
            'delegate_membership_id' => $this->membershipOf($delegate)->id,
            'from_date' => today()->toDateString(),
            'to_date' => today()->addDays(3)->toDateString(),
            'reason' => 'سفر موثَّق',
            'created_by' => $this->top->id,
        ]);
    }

    private function fileObjection(): Objection
    {
        $transaction = $this->repTransaction($this->contributor);

        $result = app(ObjectionService::class)->file(
            $transaction, $this->contributor, 'الخصم ده وقع بعد اعتذار مسبق موثَّق.',
        );

        $this->assertTrue($result['ok'], $result['message']);

        return $result['objection'];
    }

    private function repTransaction(User $user, float $value = -0.5): Transaction
    {
        $transaction = app(MeetingLedger::class)->rep(
            $user, $value, 'behavior', 'تنبيه سلوكيّ موثّق', null, $this->reviewer->id,
        );

        $this->assertNotNull($transaction);

        return $transaction;
    }
}
