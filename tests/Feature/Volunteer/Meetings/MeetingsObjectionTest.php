<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Currency;
use App\Models\Objection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Objections\ObjectionService;

/**
 * المعاملات والاعتراضات (13.4-ط · 24.4).
 * القواعد المختبَرة: اعتراض واحد لكلّ معاملة · التوجيه للمسؤول المباشر ·
 * انتهاء المهلة · والتصحيح بمعاملة عكسيّة لا بتعديل الأصل.
 */
class MeetingsObjectionTest extends MeetingsTestCase
{
    /** معاملة Rep على طبقة التطوّع لمستخدم */
    private function repTransaction(User $user, float $value = -0.5): Transaction
    {
        $transaction = app(MeetingLedger::class)->rep(
            $user, $value, 'meeting', 'غياب بلا اعتذار: اجتماع اختبار',
        );

        $this->assertNotNull($transaction, 'دفتر الأستاذ لازم يكتب المعاملة.');

        return $transaction;
    }

    public function test_transactions_screen_is_filtered_to_the_volunteer_layer_only(): void
    {
        $user = $this->volunteer('متطوّع');
        $this->repTransaction($user);

        // معاملة على طبقة التدريب لا تظهر في كشف التطوّع
        Transaction::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'coins')->value('id'),
            'amount' => 100,
            'layer' => 'training',
            'source' => 'purchase',
            'reason' => 'شراء من المتجر',
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.transactions'))
            ->assertOk()
            ->assertSee('غياب بلا اعتذار')
            ->assertDontSee('شراء من المتجر');
    }

    /** ⭐ اعتراض واحد لكلّ معاملة */
    public function test_only_one_objection_per_transaction(): void
    {
        $manager = $this->volunteer('المسؤول');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->grant($member, $this->baseGrants());

        $transaction = $this->repTransaction($member);

        $this->actingAs($member)
            ->post(route('volunteer.objections.store'), [
                'transaction_id' => $transaction->id,
                'reason' => 'كنت في مهمّة ميدانيّة وبعتّ اعتذاري.',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->post(route('volunteer.objections.store'), [
                'transaction_id' => $transaction->id,
                'reason' => 'محاولة اعتراض تانية على نفس المعاملة.',
            ]);

        $this->assertSame(1, Objection::where('transaction_id', $transaction->id)->count());
    }

    /** الاعتراض يذهب للمسؤول المباشر عن المعترِض لا لمن أضاف المعاملة */
    public function test_objection_is_routed_to_the_direct_manager(): void
    {
        $manager = $this->volunteer('المسؤول المباشر');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->grant($member, $this->baseGrants());

        $stranger = $this->volunteer('اللي ضاف المعاملة');

        $transaction = app(MeetingLedger::class)->rep(
            $member, -0.5, 'behavior', 'تنبيه موثّق', null, $stranger->id,
        );

        $this->actingAs($member)->post(route('volunteer.objections.store'), [
            'transaction_id' => $transaction->id,
            'reason' => 'الخصم ده مش مظبوط، وده تفصيل الواقعة.',
        ]);

        $objection = Objection::where('transaction_id', $transaction->id)->firstOrFail();

        $this->assertSame($manager->id, $objection->current_handler_id);
        $this->assertNotSame($stranger->id, $objection->current_handler_id);

        // صفّ تصعيد بالنوع الصحيح — والمحرّك نفسه مجالٌ آخر
        $this->assertDatabaseHas('escalations', [
            'case_type' => ObjectionService::CASE_TYPE,
            'subject_id' => $objection->id,
            'current_handler_id' => $manager->id,
            'status' => 'open',
        ]);
    }

    /** بعد انتهاء المهلة: لا زرّ اعتراض ولا قبول للطلب */
    public function test_objection_window_closes_after_the_configured_days(): void
    {
        $user = $this->volunteer('متطوّع');
        $transaction = $this->repTransaction($user);

        $transaction->forceFill([
            'objection_deadline_at' => now()->subDay(),
            'created_at' => now()->subDays(setting('rep.objection.window_days', 5) + 2),
        ])->save();

        $this->actingAs($user)
            ->get(route('volunteer.transactions'))
            ->assertOk()
            ->assertSee('انتهت مهلة الاعتراض');

        $this->actingAs($user)->post(route('volunteer.objections.store'), [
            'transaction_id' => $transaction->id,
            'reason' => 'اعتراض متأخّر عن المهلة.',
        ]);

        $this->assertSame(0, Objection::where('transaction_id', $transaction->id)->count());
    }

    /** «إضافة تفاصيل» متاحة ما دام الاعتراض ساريًا */
    public function test_details_can_be_added_while_the_objection_is_active(): void
    {
        $manager = $this->volunteer('المسؤول');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->grant($member, $this->baseGrants());

        $transaction = $this->repTransaction($member);
        $objection = app(ObjectionService::class)->file($transaction, $member, 'سبب الاعتراض الأصليّ.')['objection'];

        $this->actingAs($member)
            ->post(route('volunteer.objections.messages', $objection), ['body' => 'أرفقت لقطة الاعتذار.'])
            ->assertRedirect();

        $this->assertDatabaseHas('objection_messages', [
            'objection_id' => $objection->id,
            'user_id' => $member->id,
        ]);
    }

    /** ⭐ التصحيح بمعاملة عكسيّة موثّقة — والمعاملة الأصليّة لا تُعدَّل أبدًا */
    public function test_accepted_objection_creates_a_reversing_transaction_and_never_edits_the_original(): void
    {
        $manager = $this->volunteer('المسؤول');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->grant($member, $this->baseGrants());

        $transaction = $this->repTransaction($member, -0.5);
        $originalAmount = (float) $transaction->amount;
        $originalApplied = (float) $transaction->applied_amount;

        $service = app(ObjectionService::class);
        $objection = $service->file($transaction, $member, 'الخصم ده مش مظبوط.')['objection'];

        $before = app(MeetingLedger::class)->balance($member, 'rep');
        $preview = $service->correctionPreview($objection);

        $service->accept($objection, $manager, 'اتأكّدنا من الاعتذار المسبق.');

        $objection->refresh();
        $transaction->refresh();

        // الأصل كما هو حرفًا بحرف
        $this->assertEqualsWithDelta($originalAmount, (float) $transaction->amount, 0.001);
        $this->assertEqualsWithDelta($originalApplied, (float) $transaction->applied_amount, 0.001);
        $this->assertFalse((bool) $transaction->is_correction);

        // والتصحيح معاملة عكسيّة موثّقة مربوطة بالأصل
        $correction = Transaction::find($objection->correction_transaction_id);

        $this->assertNotNull($correction);
        $this->assertTrue((bool) $correction->is_correction);
        $this->assertSame($transaction->id, (int) $correction->corrects_transaction_id);
        $this->assertSame('accepted', $objection->status);

        // ومعاينة الأثر تطابق ما حصل فعلًا
        $after = app(MeetingLedger::class)->balance($member, 'rep');
        $this->assertEqualsWithDelta($before, $preview['from'], 0.001);
        $this->assertEqualsWithDelta($preview['to'], $after, 0.001);
    }

    /** شاشة اعتراضاتي: تخطيط «قائمة + بانل» بسلّم التصعيد */
    public function test_objections_screen_shows_list_and_panel_with_ladder(): void
    {
        $manager = $this->volunteer('المسؤول المباشر');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $manager->memberships()->first());
        $this->grant($member, $this->baseGrants());

        $transaction = $this->repTransaction($member);
        app(ObjectionService::class)->file($transaction, $member, 'سبب الاعتراض بالتفصيل.');

        $this->actingAs($member)
            ->get(route('volunteer.objections'))
            ->assertOk()
            ->assertSee('سلّم التصعيد')
            ->assertSee('المسؤول المباشر')
            ->assertSee('إضافة تفاصيل');
    }

    /** كشف المعاملات يُصدَّر CSV للمتطوّع عن نفسه */
    public function test_statement_can_be_exported(): void
    {
        $user = $this->volunteer('متطوّع');
        $this->repTransaction($user);

        $this->actingAs($user)
            ->get(route('volunteer.transactions.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
