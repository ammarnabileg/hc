<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Escalation;
use App\Models\Objection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Objections\ObjectionService;

/**
 * ⬆️ «الاعتراضات المصعَّدة إليّ» (الدستور 24.4-8 · 13.4-ط · 23-6).
 *
 * ما يقيسه هذا الملفّ:
 *  1) **الدورة كاملةً** بالتشغيل: رفع اعتراض ⟵ ظهوره على مكتب المسؤول ⟵ البتّ ⟵ الأثر في السجلّ.
 *  2) **الحصر على الخادم:** لا يرى الاعتراضَ إلّا مَن هو على مكتبه، ولا يبتّ فيه غيره
 *     ولو حمل المفتاح بنطاق ALL — ويُجرَّب بحمولةٍ مزوَّرة.
 *  3) **استقلال المسار عن `escalations`** ونافذة الأيّام الخمسة.
 *  4) **قبول الاعتراض = معاملة عكسيّة ظاهرة** لا حذفًا ولا تعديلًا للأصل.
 */
class ObjectionDeskTest extends FlowTestCase
{
    /** مفاتيح مكتب البتّ كما في المصفوفة 12.2.2 */
    private const DESK_KEYS = [
        'objections.list', 'objections.view',
        'objections.approve', 'objections.reject', 'objections.assign',
    ];

    private function repTransaction(User $user, float $value = -0.5): Transaction
    {
        $transaction = app(MeetingLedger::class)->rep(
            $user, $value, 'behavior', 'تنبيه سلوكيّ موثّق', null, $this->reviewer->id,
        );

        $this->assertNotNull($transaction, 'دفتر الأستاذ لازم يكتب المعاملة.');

        return $transaction;
    }

    /** يرفع المساهم اعتراضًا فيقع على مكتب أبلاينه المباشر (مالك المهمّة) */
    private function fileObjection(?Transaction $transaction = null): Objection
    {
        $transaction ??= $this->repTransaction($this->contributor);

        $result = app(ObjectionService::class)->file(
            $transaction, $this->contributor, 'الخصم ده وقع بعد اعتذار مسبق موثَّق.',
        );

        $this->assertTrue($result['ok'], $result['message']);

        return $result['objection'];
    }

    // ================================================================ الدورة كاملة

    /**
     * ⭐ الدورة المقطوعة صارت متّصلة: رفعٌ ⟵ مكتبُ المسؤول ⟵ ردٌّ ⟵ تصعيدٌ ⟵ قبولٌ
     * ⟵ **معاملة عكسيّة في الكشف** — كلّها بطلبات HTTP حقيقيّة.
     */
    public function test_full_objection_cycle_from_filing_to_a_recorded_reversal(): void
    {
        $this->grant($this->owner, ...self::DESK_KEYS);
        $this->grant($this->reviewer, ...self::DESK_KEYS);

        $transaction = $this->repTransaction($this->contributor, -0.5);
        $before = app(MeetingLedger::class)->balance($this->contributor, 'rep');
        $objection = $this->fileObjection($transaction);

        // 1) وقع على مكتب الأبلاين المباشر لا على مَن أضاف المعاملة
        $this->assertSame($this->owner->id, (int) $objection->current_handler_id);
        $this->assertNotSame($this->reviewer->id, (int) $objection->current_handler_id);

        // 2) ويظهر على شاشته هو
        $this->actingAs($this->owner)
            ->get(route('volunteer.escalations.objections'))
            ->assertOk()
            ->assertSee('اعتراض #'.$objection->id)
            ->assertSee($this->contributor->name)
            ->assertSee('سلّم التصعيد')
            ->assertSee('Audit');

        // 3) ردّ ⟵ «قيد المراجعة» ورسالة في السلسلة
        $this->actingAs($this->owner)
            ->post(route('volunteer.escalations.objections.reply', $objection), [
                'body' => 'راجعت الاعتذار وهبعت لأبلايني للتأكيد.',
            ])
            ->assertRedirect();

        $this->assertSame('in_review', $objection->fresh()->status);
        $this->assertDatabaseHas('objection_messages', [
            'objection_id' => $objection->id,
            'user_id' => $this->owner->id,
        ]);

        // 4) تصعيد بسبب مكتوب ⟵ ينتقل المكتب للسوبرفايزر
        $this->actingAs($this->owner)
            ->post(route('volunteer.escalations.objections.escalate', $objection), [
                'reason' => 'القرار محتاج مستوى أعلى لأنّ الخصم من خارج كياني.',
            ])
            ->assertRedirect();

        $objection->refresh();
        $this->assertSame('escalated', $objection->status);
        $this->assertSame($this->reviewer->id, (int) $objection->current_handler_id);

        // وبعد التصعيد لم يعد على مكتب الأوّل — فلا يراه ولا يبتّ فيه
        $this->actingAs($this->owner)
            ->get(route('volunteer.escalations.objections'))
            ->assertOk()
            ->assertDontSee('اعتراض #'.$objection->id);

        $this->actingAs($this->owner)
            ->post(route('volunteer.escalations.objections.accept', $objection), ['note' => 'محاولة بتٍّ بعد ما فقدت المكتب.'])
            ->assertForbidden();

        // 5) القبول من صاحب المكتب الجديد ⟵ معاملة عكسيّة ظاهرة
        $this->actingAs($this->reviewer)
            ->post(route('volunteer.escalations.objections.accept', $objection), [
                'note' => 'الاعتذار المسبق موثَّق — الخصم يتصحّح.',
            ])
            ->assertRedirect();

        $objection->refresh();
        $transaction->refresh();

        $this->assertSame('accepted', $objection->status);
        $this->assertSame($this->reviewer->id, (int) $objection->decided_by);

        // 6) الأثر في السجلّ: صفٌّ عكسيٌّ مقروء، والأصل لم يُحذَف ولم يُعدَّل
        $correction = Transaction::find($objection->correction_transaction_id);

        $this->assertNotNull($correction, 'القبول لازم يخلّف معاملةً عكسيّةً ظاهرة لا حذفًا.');
        $this->assertTrue((bool) $correction->is_correction);
        $this->assertSame($transaction->id, (int) $correction->corrects_transaction_id);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'is_correction' => false]);
        $this->assertEqualsWithDelta(-0.5, (float) $transaction->amount, 0.001);

        // والرصيد رجع لما كان عليه قبل الخصم
        $this->assertEqualsWithDelta(
            $before + 0.5,
            app(MeetingLedger::class)->balance($this->contributor, 'rep'),
            0.001,
        );

        // ويظهر الأثر في كشف صاحبه
        $this->grant($this->contributor, 'rep_transactions.list', 'rep_transactions.view', 'objections.view');
        $this->actingAs($this->contributor)
            ->get(route('volunteer.transactions'))
            ->assertOk()
            ->assertSee('معاملة تصحيحيّة بعد قبول اعتراض #'.$objection->id);
    }

    /** الرفض يُغلِق بسبب موثَّق **بلا** أيّ معاملة تصحيحيّة */
    public function test_rejection_closes_with_a_written_reason_and_no_correction(): void
    {
        $this->grant($this->owner, ...self::DESK_KEYS);

        $objection = $this->fileObjection();
        $countBefore = Transaction::query()->where('is_correction', true)->count();

        $this->actingAs($this->owner)
            ->post(route('volunteer.escalations.objections.reject', $objection), [
                'note' => 'مفيش اعتذار مسجَّل قبل الاجتماع — الخصم في محلّه.',
            ])
            ->assertRedirect();

        $objection->refresh();

        $this->assertSame('rejected', $objection->status);
        $this->assertSame($this->owner->id, (int) $objection->decided_by);
        $this->assertNull($objection->correction_transaction_id);
        $this->assertSame($countBefore, Transaction::query()->where('is_correction', true)->count());

        // والقرار لا يُعاد
        $this->actingAs($this->owner)
            ->post(route('volunteer.escalations.objections.accept', $objection), ['note' => 'محاولة إعادة فتح القرار.'])
            ->assertForbidden();
    }

    // ================================================================ الحصر على الخادم

    /**
     * ⭐ حمولةٌ مزوَّرة: أجنبيٌّ يحمل **كلّ مفاتيح الاعتراضات بنطاق ALL** ومع ذلك
     * لا يرى اعتراضًا ليس على مكتبه ولا يبتّ فيه — فالمفتاح يفتح الشاشة والمكتبُ يفتح القرار.
     */
    public function test_a_stranger_with_all_scope_keys_neither_sees_nor_decides_an_objection_off_his_desk(): void
    {
        $this->grant($this->owner, ...self::DESK_KEYS);
        $this->grant($this->top, ...self::DESK_KEYS);

        $objection = $this->fileObjection();

        // السقف يحمل المفاتيح بنطاق ALL — والشاشة تفتح له، لكنّها فارغة
        $this->actingAs($this->top)
            ->get(route('volunteer.escalations.objections'))
            ->assertOk()
            ->assertDontSee('اعتراض #'.$objection->id)
            ->assertSee(setting('workflow.objection_desk.empty', 'مفيش اعتراضات عندك'));

        // وكلّ أفعال البتّ الأربعة مرفوضة عليه
        foreach (['reply' => ['body' => 'ردٌّ من خارج المكتب.'],
            'escalate' => ['reason' => 'تصعيدٌ من خارج المكتب.'],
            'accept' => ['note' => 'قبولٌ من خارج المكتب.'],
            'reject' => ['note' => 'رفضٌ من خارج المكتب.']] as $action => $payload) {
            $this->actingAs($this->top)
                ->post(route('volunteer.escalations.objections.'.$action, $objection), $payload)
                ->assertForbidden();
        }

        // ولا شيء تحرّك: الحالة والمكتب كما هما
        $objection->refresh();
        $this->assertSame('open', $objection->status);
        $this->assertSame($this->owner->id, (int) $objection->current_handler_id);
        $this->assertSame(0, $objection->messages()->count());
    }

    /** بلا مفتاح = الباب مغلق أصلًا (12.2.1) */
    public function test_the_desk_screen_is_closed_without_the_matrix_key(): void
    {
        $this->actingAs($this->contributor)
            ->get(route('volunteer.escalations.objections'))
            ->assertForbidden();
    }

    // ================================================================ استقلال المسار والنافذة

    /** ⭐ المسار قائم بذاته: لا صفّ في `escalations` مهما بُتَّ فيه (23-6) */
    public function test_the_objection_track_never_writes_a_row_in_escalations(): void
    {
        $this->grant($this->owner, ...self::DESK_KEYS);
        $this->grant($this->reviewer, ...self::DESK_KEYS);

        $objection = $this->fileObjection();

        $this->actingAs($this->owner)->post(route('volunteer.escalations.objections.reply', $objection), [
            'body' => 'شغّالين على المراجعة.',
        ]);
        $this->actingAs($this->owner)->post(route('volunteer.escalations.objections.escalate', $objection), [
            'reason' => 'محتاج مستوى أعلى للبتّ فيه.',
        ]);
        $this->actingAs($this->reviewer)->post(route('volunteer.escalations.objections.accept', $objection), [
            'note' => 'اتأكّدنا من الاعتذار.',
        ]);

        $this->assertDatabaseMissing('escalations', ['case_type' => ObjectionService::CASE_TYPE]);
        $this->assertSame(0, Escalation::query()->count());
    }

    /** نافذة الأيّام الخمسة (23-6): ما بعدها لا يُفتَح اعتراض أصلًا فلا يصل مكتبًا */
    public function test_the_five_day_window_gates_the_whole_track(): void
    {
        $days = (int) setting('rep.objection.window_days', 5);

        $this->assertSame(5, $days, 'النافذة المنصوصة خمسة أيّام (23-6).');

        $transaction = $this->repTransaction($this->contributor);
        $transaction->forceFill([
            'objection_deadline_at' => now()->subDay(),
            'created_at' => now()->subDays($days + 2),
        ])->save();

        $result = app(ObjectionService::class)->file($transaction, $this->contributor, 'اعتراض بعد فوات المهلة.');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, Objection::query()->where('transaction_id', $transaction->id)->count());
    }
}
