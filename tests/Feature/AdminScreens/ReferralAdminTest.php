<?php

namespace Tests\Feature\AdminScreens;

use App\Models\Referral;

/**
 * لوحة الريفيرال والسفراء (24.2): ترندر · الصلاحيّة تحجب · الفلاتر تشتغل ·
 * 🔒 والعمولة لا تُرى ولا تُصدَّر لغير مالك المنصّة.
 */
class ReferralAdminTest extends ScreensTestCase
{
    public function test_screen_renders_with_its_three_tabs(): void
    {
        $admin = $this->admin(['referrals.list', 'referrals.view']);
        $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));

        foreach (['invites', 'ambassadors', 'tiers'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.referrals.index', ['tab' => $tab]))
                ->assertOk()
                ->assertSee('الريفيرال والسفراء');
        }
    }

    public function test_permission_blocks_the_screen_and_its_actions(): void
    {
        $referral = $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));

        $this->actingAs($this->admin(['users.list']))
            ->get(route('admin.referrals.index'))
            ->assertForbidden();

        // القراءة وحدها لا تصرف مكافأة
        $this->actingAs($this->admin(['referrals.list', 'referrals.view']))
            ->post(route('admin.referrals.payout', $referral), ['note' => 'تمام'])
            ->assertForbidden();
    }

    /** الفلاتر: حالة الدعوة · حالة المكافأة */
    public function test_filters_narrow_the_invites(): void
    {
        $referrer = $this->makeUser('داعٍ');

        $completed = $this->makeReferral($referrer, $this->makeUser('مدعوّ مكتمل'));
        $incomplete = $this->makeReferral($referrer);
        $incomplete->forceFill(['payout_status' => 'held'])->save();

        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'invites', 'status' => 'completed']))
            ->assertOk()
            ->assertSee('مدعوّ مكتمل');

        $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'invites', 'status' => 'incomplete']))
            ->assertOk()
            ->assertDontSee('مدعوّ مكتمل');

        $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'invites', 'payout' => 'held']))
            ->assertOk()
            ->assertDontSee('مدعوّ مكتمل');

        $this->assertNotNull($completed->id);
    }

    /** 🔒 العمولة رقم ماليّ: تظهر لمالك المنصّة وتختفي عن غيره — واجهةً وتصديرًا */
    public function test_commission_is_hidden_from_non_owners_in_screen_and_export(): void
    {
        $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));

        $this->actingAs($this->owner())
            ->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee('🔒 العمولة');

        $admin = $this->admin(['referrals.list', 'referrals.view', 'referrals.export']);

        $this->actingAs($admin)
            ->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertDontSee('🔒 العمولة');

        $export = $this->actingAs($admin)->get(route('admin.referrals.export'));
        $export->assertOk();

        $this->assertStringNotContainsString('commission', $export->streamedContent());
    }

    /** الصرف يشترط تفعيل حساب المدعوّ — وإلّا بقيت المكافأة معلّقة برسالة تشرح */
    public function test_payout_requires_an_activated_invitee(): void
    {
        $owner = $this->owner();
        $pending = $this->makeUser('مدعوّ لسّه', status: 'pending');
        $referral = $this->makeReferral($this->makeUser('داعٍ'), $pending);

        $this->actingAs($owner)
            ->post(route('admin.referrals.payout', $referral), ['note' => ''])
            ->assertRedirect()
            ->assertSessionHas('problem');

        $this->assertSame('pending', $referral->refresh()->payout_status);

        $pending->forceFill(['status' => 'active'])->save();

        $this->actingAs($owner)
            ->post(route('admin.referrals.payout', $referral), ['note' => 'اتراجعت'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('paid', $referral->refresh()->payout_status);
    }

    /** التعليق يلزمه سبب — قرارٌ بلا سبب أثرٌ لا يُراجَع */
    public function test_hold_requires_a_written_reason(): void
    {
        $referral = $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));

        $this->actingAs($this->owner())
            ->post(route('admin.referrals.hold', $referral), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->owner())
            ->post(route('admin.referrals.hold', $referral), ['note' => 'نمط متكرّر'])
            ->assertRedirect();

        $this->assertSame('held', $referral->refresh()->payout_status);
        $this->assertTrue((bool) $referral->is_flagged);
    }

    /** سلّم الألقاب يُحفَظ مرتّبًا تصاعديًّا مهما كان ترتيب الإدخال (7.6.1) */
    public function test_ambassador_tiers_are_saved_sorted_ascending(): void
    {
        $this->actingAs($this->owner())->post(route('admin.referrals.tiers'), [
            'tiers' => [
                ['key' => 'gold', 'label' => 'سفير ذهبيّ', 'threshold' => 30],
                ['key' => 'bronze', 'label' => 'سفير برونزيّ', 'threshold' => 5],
            ],
        ])->assertRedirect();

        $tiers = setting('ambassadors.tiers');

        $this->assertSame('bronze', $tiers[0]['key']);
        $this->assertSame('gold', $tiers[1]['key']);
    }

    public function test_audit_screen_shows_the_referrer_network(): void
    {
        $referrer = $this->makeUser('داعٍ للتدقيق');
        Referral::query()->delete();
        $this->makeReferral($referrer, $this->makeUser('مدعوّ واحد'));

        $this->actingAs($this->owner())
            ->get(route('admin.referrals.audit', $referrer))
            ->assertOk()
            ->assertSee('مدعوّ واحد');
    }
}
