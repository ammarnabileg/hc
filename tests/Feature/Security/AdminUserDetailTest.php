<?php

namespace Tests\Feature\Security;

use App\Models\Country;
use App\Models\Referral;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Str;

/**
 * صفحة حساب المستخدم في لوحة الإدارة (12.1) — التابات والحقول التي كانت ناقصة:
 * **الجداول بفلتر الفترة** · **جدول السحوبات** · **الأمان** · **الإدارة** ·
 * **التعديل اليدويّ وتأكيد الإيميل والملاحظات الداخليّة** ·
 * **تثبيت الدولة** · **تصدير بيانات المستخدم** — وكلٌّ بصلاحيّته.
 */
class AdminUserDetailTest extends SecurityTestCase
{
    private const SUPPORT = [
        'admin_panel.view', 'users.view', 'users.edit', 'user_sessions.delete',
        'admin_user_detail.view', 'admin_user_detail.edit',
    ];

    // ------------------------------------------------------------- الصلاحيّات

    /** ⭐ `admin_user_detail.view` صارت حارسًا حقيقيًّا على مسار الصفحة */
    public function test_the_detail_permission_alone_opens_the_page(): void
    {
        $target = $this->makeUser('حساب للعرض');

        // يملك صلاحيّة الصفحة المخصّصة وحدها ⟵ يفتح
        $this->actingAs($this->admin(['admin_panel.view', 'admin_user_detail.view']))
            ->get(route('admin.users.show', $target))
            ->assertOk();

        // لا يملك أيًّا منهما ⟵ 403 على المسار نفسه لا إخفاءً في الفيو وحده
        $this->actingAs($this->admin(['admin_panel.view'], 'أدمن بلا صلاحيّة'))
            ->get(route('admin.users.show', $target))
            ->assertForbidden();
    }

    /** التابات الناقصة ظهرت — وكلٌّ منها **يُخفى لمن لا يملك صلاحيّته** */
    public function test_tabs_appear_by_permission_only(): void
    {
        $target = $this->makeUser('حساب التابات');

        $tabs = collect($this->actingAs($this->owner())
            ->get(route('admin.users.show', $target))->assertOk()
            ->viewData('tabs'))->pluck('key')->all();

        foreach (['profile', 'tables', 'security', 'admin', 'advanced'] as $key) {
            $this->assertContains($key, $tabs);
        }

        // أدمن قراءة فقط: لا تاب أمان ولا إدارة ولا جداول
        $readerTabs = collect($this->actingAs($this->admin(['admin_panel.view', 'users.view'], 'قارئ'))
            ->get(route('admin.users.show', $target))->assertOk()
            ->viewData('tabs'))->pluck('key')->all();

        $this->assertNotContains('security', $readerTabs);
        $this->assertNotContains('admin', $readerTabs);
        $this->assertNotContains('tables', $readerTabs);
    }

    // ------------------------------------------------------- تاب الجداول والفلتر

    /** ⭐ **فلتر الفترة يعمل**: صفوف خارج المدى تختفي وصفوف داخله تظهر */
    public function test_the_period_filter_actually_filters_the_tables(): void
    {
        $target = $this->makeUser('صاحب سحوبات');

        $recent = $this->withdrawal($target, 'W-RECENT', now()->subDays(3));
        $old = $this->withdrawal($target, 'W-OLD', now()->subDays(200));

        $url = fn (array $query) => route('admin.users.show', ['user' => $target, 'tab' => 'tables'] + $query);

        // المدى الافتراضيّ (آخر 30 يومًا) ⟵ الجديد وحده
        $this->actingAs($this->admin(self::SUPPORT))
            ->get($url([]))
            ->assertOk()
            ->assertSee($recent->number, false)
            ->assertDontSee($old->number, false);

        // ووسّع المدى ⟵ القديم يظهر
        $this->actingAs($this->admin(self::SUPPORT))
            ->get($url(['from' => now()->subDays(365)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertSee($old->number, false);
    }

    /** جدول السحوبات ما كانش موجودًا أصلًا — دلوقتي موجود بحالته */
    public function test_the_withdrawals_table_exists_on_the_tables_tab(): void
    {
        $target = $this->makeUser('صاحب سحب واحد');
        $withdrawal = $this->withdrawal($target, 'W-ONE', now()->subDay());

        $this->actingAs($this->admin(self::SUPPORT))
            ->get(route('admin.users.show', ['user' => $target, 'tab' => 'tables']))
            ->assertOk()
            ->assertSee('عمليّات السحب', false)
            ->assertSee($withdrawal->number, false);
    }

    /** الهديّة تظهر **بعد قبول الحساب** لا وقت التسجيل (12.1-الجداول-3) */
    public function test_the_referral_gift_state_waits_for_account_acceptance(): void
    {
        $referrer = $this->makeUser('داعٍ');
        $pending = $this->makeUser('مدعوّ تحت المراجعة', ['status' => 'pending']);

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $pending->id,
            'code' => $referrer->code,
            'welcome_ticket_granted' => false,
        ]);

        $this->actingAs($this->admin(self::SUPPORT))
            ->get(route('admin.users.show', ['user' => $referrer, 'tab' => 'tables']))
            ->assertOk()
            ->assertSee('مستنّي قبول الحساب', false);
    }

    // --------------------------------------------------- تاب المعلومات الأساسيّة

    /** ⭐ التعديل اليدويّ + Checkbox تأكيد الإيميل + الملاحظات الإداريّة الداخليّة */
    public function test_admin_can_edit_the_profile_verify_the_email_and_write_internal_notes(): void
    {
        $target = $this->makeUser('اسم فيه غلطة');

        $this->actingAs($this->admin(self::SUPPORT))
            ->put(route('admin.users.update', $target), [
                'name' => 'محمود عبد الرحمن',
                'email' => 'mahmoud@fixed.local',
                'phone' => '01000000001',
                'email_verified' => '1',
                'admin_notes' => 'اتكلّم مع الدعم مرّتين — الحساب سليم.',
            ])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('محمود عبد الرحمن', $target->name);
        $this->assertSame('mahmoud@fixed.local', $target->email);
        $this->assertNotNull($target->email_verified_at);
        $this->assertSame('اتكلّم مع الدعم مرّتين — الحساب سليم.', $target->admin_notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $target->id]);
    }

    /** إلغاء الـCheckbox يرجّع البريد لغير مؤكَّد ويُسجَّل */
    public function test_unchecking_the_box_marks_the_email_unverified_again(): void
    {
        $target = $this->makeUser('بريد مؤكَّد', ['email_verified_at' => now()]);

        $this->actingAs($this->admin(self::SUPPORT))
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
            ])
            ->assertRedirect();

        $this->assertNull($target->refresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.email.unverify', 'auditable_id' => $target->id]);
    }

    /** ⛔ التعديل محروس: مَن لا يملك `admin_user_detail.edit` يُرفَض على المسار */
    public function test_editing_is_guarded_by_its_own_permission(): void
    {
        $target = $this->makeUser('حساب محميّ');

        $this->actingAs($this->admin(['admin_panel.view', 'users.view'], 'قارئ'))
            ->put(route('admin.users.update', $target), ['name' => 'محاولة', 'email' => 'x@y.local'])
            ->assertForbidden();

        $this->assertSame('حساب محميّ', $target->refresh()->name);
    }

    // ----------------------------------------------------------- تاب متقدّم

    /** تثبيت/تصحيح الدولة يدويًّا — والكشف التلقائيّ بعدها لا يدهسها */
    public function test_country_can_be_pinned_manually(): void
    {
        $target = $this->makeUser('دولته غلط');
        $country = Country::query()->first() ?? Country::create([
            'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt', 'is_active' => true,
        ]);

        $this->actingAs($this->admin(self::SUPPORT))
            ->post(route('admin.users.country', $target), ['country_id' => $country->id])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame($country->id, $target->country_id);
        $this->assertNotNull($target->country_locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.country.pin', 'auditable_id' => $target->id]);
    }

    /** ⭐ **تصدير بيانات المستخدم محروس بصلاحيّته** `admin_user_detail.export` */
    public function test_export_is_guarded_by_its_own_permission(): void
    {
        $target = $this->makeUser('حساب للتصدير');

        // بلا الصلاحيّة ⟵ 403 على المسار
        $this->actingAs($this->admin(self::SUPPORT))
            ->get(route('admin.users.export', $target))
            ->assertForbidden();

        // وبها ⟵ ملفٌّ ينزل فيه بياناته
        $response = $this->actingAs($this->admin([...self::SUPPORT, 'admin_user_detail.export'], 'مصدّر'))
            ->get(route('admin.users.export', $target))
            ->assertOk()
            ->assertDownload();

        $payload = json_decode($this->streamed($response), true);

        $this->assertSame($target->code, $payload['account']['code']);
        $this->assertArrayHasKey('withdrawals', $payload);
        // ⛔ ولا تخرج الملاحظات الداخليّة ولا كلمة السرّ في الملفّ
        $this->assertArrayNotHasKey('admin_notes', $payload['account']);
        $this->assertArrayNotHasKey('password', $payload['account']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.data.export', 'auditable_id' => $target->id]);
    }

    // ------------------------------------------------------------------ أدوات

    private function withdrawal(User $user, string $number, $at): WalletWithdrawal
    {
        $withdrawal = WalletWithdrawal::create([
            'number' => $number.'-'.Str::random(4),
            'user_id' => $user->id,
            'amount' => 100,
            'fee_percent' => 5,
            'fee_amount' => 5,
            'net_amount' => 95,
            'method' => 'wallet',
            'account_number' => '01000000000',
            'status' => WalletWithdrawal::PENDING,
        ]);

        $withdrawal->forceFill(['created_at' => $at])->saveQuietly();

        return $withdrawal->refresh();
    }

    private function streamed($response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }
}
