<?php

namespace Tests\Feature\AdminScreens;

use App\Models\Referral;
use App\Services\AdminScreens\ReferralAdmin;
use App\Services\Wallet\LedgerService;

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

    /**
     * ⭐ 24.2: بحثٌ بلا نتائج في تابَي الدعوات والسفراء يقول كده صراحةً — لا
     * رسالة البداية المضلِّلة. الملاحظة: نصّ الحالة الافتراضيّة يظهر أيضًا
     * داخل «لوحة الإعدادات» أسفل الشاشة، فالتحقّق داخل بطاقة الحالة الفارغة وحدها.
     */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));
        $owner = $this->owner();

        $invitesHtml = $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'invites', 'q' => 'zzzznotexist']))
            ->assertOk()
            ->getContent();
        $invitesCard = substr($invitesHtml, strpos($invitesHtml, 'card p-8 text-center'), 400);

        $this->assertStringContainsString(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            $invitesCard,
        );
        $this->assertStringNotContainsString('لسّه مافيش دعوات في المدى ده', $invitesCard);

        $ambassadorsHtml = $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'ambassadors', 'q' => 'zzzznotexist']))
            ->assertOk()
            ->getContent();
        $ambassadorsCard = substr($ambassadorsHtml, strpos($ambassadorsHtml, 'card p-8 text-center'), 400);

        $this->assertStringContainsString(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            $ambassadorsCard,
        );
        $this->assertStringNotContainsString('لسّه محدّش وصل لأوّل لقب', $ambassadorsCard);
    }

    /** والتابان الفارغان فعليًّا (بلا فلتر) يفضّلان رسالة البداية الأصليّة. */
    public function test_actually_empty_tabs_without_filters_keep_the_original_start_message(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'invites']))
            ->assertOk()
            ->assertSee('لسّه مافيش دعوات في المدى ده');

        $this->actingAs($owner)
            ->get(route('admin.referrals.index', ['tab' => 'ambassadors']))
            ->assertOk()
            ->assertSee('لسّه محدّش وصل لأوّل لقب');
    }

    /** 🔒 العمولة رقم ماليّ: تظهر لمالك المنصّة وتختفي عن غيره — واجهةً وتصديرًا */
    public function test_commission_is_hidden_from_non_owners_in_screen_and_export(): void
    {
        $this->makeReferral($this->makeUser('داعٍ'), $this->makeUser('مدعوّ'));

        $this->actingAs($this->owner())
            ->get(route('admin.referrals.index'))
            ->assertOk()
            // الأيقونة صارت SVG مرسومة داخل المشروع (2.16-ج) — فالنصّ وحده يُقاس
            ->assertSee('العمولة المستحقّة');

        $admin = $this->admin(['referrals.list', 'referrals.view', 'referrals.export']);

        $this->actingAs($admin)
            ->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertDontSee('العمولة المستحقّة');

        $export = $this->actingAs($admin)->get(route('admin.referrals.export'));
        $export->assertOk();

        $this->assertStringNotContainsString('commission', $export->streamedContent());
    }

    /**
     * ⭐ 24.2: عمود **«إجمالي شحنه»** في جدول المدعوّين — كان غائبًا كلّيًّا،
     * فالأدمن يرى عمولةً مستحقّة بلا الرقم الذي حُسِبت منه، ولا يقدر يراجعها.
     *
     * والقيمة تُقاس لا العنوان وحده: شحنتان بقيمتين مختلفتين ومجموعهما بالدولار
     * هو ما يظهر في الصفّ — فعمودٌ يرندر صفرًا دائمًا يمرّ على اختبار العنوان.
     */
    public function test_the_invitees_table_shows_the_invitee_total_topup(): void
    {
        $referred = $this->makeUser('مدعوّ شحّان');
        $referral = $this->makeReferral($this->makeUser('داعٍ'), $referred);

        // شحنتان: 500 + 250 كوينًا = 10$ + 5$ بسعر العرض (1$ = 50 كوينًا)
        app(LedgerService::class)->credit($referred, 'coins', 500, 'topup', null, 'training', 'شحنة أولى');
        app(LedgerService::class)->credit($referred, 'coins', 250, 'topup', null, 'training', 'شحنة تانية');

        $this->assertSame(15.0, app(ReferralAdmin::class)->topupTotal($referral->refresh()));

        $this->actingAs($this->owner())
            ->get(route('admin.referrals.index', ['tab' => 'invites']))
            ->assertOk()
            ->assertSee('إجمالي شحنه')
            ->assertSee('15.00');
    }

    /**
     * 🔒 والرقم ماليّ: العمولة = النسبة × إجمالي الشحن، فكشف الأساس لغير
     * المجموعة المحميّة يكشف العمولة المحجوبة نفسها (24.2 «بلا صلاحيّة» · 12.7).
     */
    public function test_the_total_topup_column_is_hidden_from_non_owners(): void
    {
        $referred = $this->makeUser('مدعوّ شحّان');
        $this->makeReferral($this->makeUser('داعٍ'), $referred);

        app(LedgerService::class)->credit($referred, 'coins', 500, 'topup', null, 'training', 'شحنة أولى');

        $this->actingAs($this->admin(['referrals.list', 'referrals.view']))
            ->get(route('admin.referrals.index', ['tab' => 'invites']))
            ->assertOk()
            ->assertDontSee('إجمالي شحنه');
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
