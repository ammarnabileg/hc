<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ⭐ شاشة سياسة الاسترجاع المستقلّة (12.2.2 · 12.2.3-أ-7) — سدّ فجوة «قدرةٌ في
 * المصفوفة بانتظار شاشتها» (database/data/_STATUS.md).
 *
 * والفارق عن `PermissionArchitectureTest::test_the_finance_admin_actually_holds_the_refunds_resource`:
 * ذاك يقيس أنّ الصلاحيّة **تُمنَح وتنفُذ** في محرّك الصلاحيّات، وهذا يقيس أنّ لها
 * الآن **مسارًا وشاشةً فعليّين** يفتحان لصاحبها — بأدوارٍ حقيقيّة من السيدرات
 * الفعليّة (`RolePermissionSeeder`) لا صلاحيّاتٍ مفبركة عبر `permission_user`.
 */
class RefundPolicyScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower(Str::random(10)).'@test.local',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
        ]);
    }

    private function withRole(string $roleKey, string $name): User
    {
        $user = $this->makeUser($name);
        $user->assignRole($roleKey);

        return $user;
    }

    private function financeAdmin(): User
    {
        return $this->withRole('finance_admin', 'المسؤول الماليّ');
    }

    private function owner(): User
    {
        return $this->withRole('platform_owner', 'مالك المنصّة');
    }

    // ------------------------------------------- المسؤول الماليّ يصل الشاشة الجديدة

    /** ⭐⭐ الفجوة كانت هنا بالحرف: صلاحيّةٌ ممنوحة بلا مسارٍ يحرسه — صار لها مسار */
    public function test_finance_admin_opens_the_new_refund_policy_screen(): void
    {
        $financeAdmin = $this->financeAdmin();

        $this->actingAs($financeAdmin)->get(route('admin.refund-policy.index'))
            ->assertOk()
            ->assertSee('سياسة الاسترجاع', false);
    }

    /** والمسؤول الماليّ يحفظ فعلًا — بسبب إلزاميّ يدخل الـAudit كما في 19.4 */
    public function test_finance_admin_can_save_the_refund_policy_with_an_audit_trail(): void
    {
        $financeAdmin = $this->financeAdmin();

        $this->actingAs($financeAdmin)->post(route('admin.refund-policy.save'), [
            'locale' => 'ar',
            'body' => '<p>نصّ سياسة معدَّل من المسؤول الماليّ.</p>',
            'reason' => 'تحديث دوريّ للصياغة',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $setting = Setting::query()->where('key', 'finance.refund.policy_ar')->firstOrFail();

        $this->assertStringContainsString('نصّ سياسة معدَّل من المسؤول الماليّ', (string) $setting->value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'refunds.edit',
            'auditable_id' => $setting->id,
        ]);
    }

    /** وبلا سبب ⟵ يُرفَض تمامًا كما في شاشة المالك (نفس القاعدة، نفس الخدمة) */
    public function test_finance_admin_save_is_rejected_without_a_reason(): void
    {
        $financeAdmin = $this->financeAdmin();

        $this->actingAs($financeAdmin)->post(route('admin.refund-policy.save'), [
            'locale' => 'en',
            'body' => 'No cash refunds.',
            'reason' => '',
        ])->assertSessionHasErrors('reason');
    }

    /** والمعاينة (12.2.2: «… ومعاينته») تفتح كذلك بصلاحيّة العرض */
    public function test_finance_admin_can_hit_the_preview_endpoint(): void
    {
        $financeAdmin = $this->financeAdmin();

        $this->actingAs($financeAdmin)->postJson(route('admin.refund-policy.preview'), [
            'body' => '<p>معاينة</p>',
        ])->assertOk()->assertJson(['html' => '<p>معاينة</p>']);
    }

    // ------------------------------------------- 🔒 الماليّات تبقى مغلقة تمامًا

    /**
     * ⛔ وما لم يتّسع لم يتّسع: المسؤول الماليّ يبقى خارج 🔒 الماليّات بحارسيها
     * القديمين (`finance.view`/`finance.edit`) — الشاشة الجديدة سدّت الفجوة
     * المنصوصة (`refunds`)، ولم تفتح مجموعةً معزولة بالخطأ.
     */
    public function test_finance_admin_stays_blocked_from_every_owner_only_finance_route(): void
    {
        $financeAdmin = $this->financeAdmin();

        $this->actingAs($financeAdmin)->get(route('admin.finance.index'))->assertForbidden();
        $this->actingAs($financeAdmin)->get(route('admin.finance.audit'))->assertForbidden();

        // ⭐ الحارس على المسار (`permission:finance.edit`) يردّ 403 **قبل** أن يصل
        // المتحكّم إلى البحث عن المفتاح — فمفتاحٌ وهميّ يكفي لقياس الحارس نفسه.
        $this->actingAs($financeAdmin)->post(route('admin.finance.save'), [
            'key' => 'finance.rates.usd_to_coins',
            'value' => 1,
            'reason' => 'محاولة كتابة ممنوعة',
        ])->assertForbidden();

        $this->actingAs($financeAdmin)->post(route('admin.finance.refund-policy'), [
            'locale' => 'ar',
            'body' => 'محاولة تحرير عبر الباب القديم',
            'reason' => 'اختبار العزل',
        ])->assertForbidden();
    }

    // ------------------------------------------- مالك المنصّة: البابان معًا بلا افتراق

    /** ⭐ مالك المنصّة يفتح البابين معًا: اللوحة القديمة (تاب) والشاشة المستقلّة الجديدة */
    public function test_platform_owner_opens_both_the_old_panel_and_the_new_screen(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.finance.index', ['group' => 'refund']))->assertOk();
        $this->actingAs($owner)->get(route('admin.refund-policy.index'))->assertOk();
    }

    /**
     * ⭐⭐ **لا Drift بين الشاشتين:** الحفظ من الباب القديم يظهر فورًا في الشاشة
     * الجديدة، والعكس — لأنّ الاثنتين تكتبان نفس المفتاح عبر نفس الخدمة
     * (`FinanceSettings::saveRefundPolicy`).
     */
    public function test_old_panel_and_new_screen_read_and_write_the_same_policy_value(): void
    {
        $owner = $this->owner();

        // 1) يحفظ مالك المنصّة من الباب **القديم**
        $this->actingAs($owner)->post(route('admin.finance.refund-policy'), [
            'locale' => 'ar',
            'body' => 'نصّ مكتوب من شاشة الماليّات القديمة',
            'reason' => 'تحديث من الشاشة القديمة',
        ])->assertSessionHasNoErrors();

        // ويظهر في الشاشة **الجديدة** فورًا — نفس المفتاح بالضبط
        $this->actingAs($owner)->get(route('admin.refund-policy.index'))
            ->assertOk()
            ->assertSee('نصّ مكتوب من شاشة الماليّات القديمة', false);

        // 2) ويحفظ من الشاشة **الجديدة**
        $this->actingAs($owner)->post(route('admin.refund-policy.save'), [
            'locale' => 'ar',
            'body' => 'نصّ مكتوب من الشاشة المستقلّة الجديدة',
            'reason' => 'تحديث من الشاشة الجديدة',
        ])->assertSessionHasNoErrors();

        // ويظهر في الباب **القديم** فورًا — لا نسختان متفرّقتان من نفس النصّ
        $this->actingAs($owner)->get(route('admin.finance.index', ['group' => 'refund']))
            ->assertOk()
            ->assertSee('نصّ مكتوب من الشاشة المستقلّة الجديدة', false);

        // ومفتاحٌ واحدٌ فعلًا في القاعدة — لا صفّان متوازيان
        $this->assertSame(
            1,
            Setting::query()->where('key', 'finance.refund.policy_ar')->count(),
        );
    }

    /** والمالك يبقى فوق الجميع (12.2.1-ز-5): 🔒 الماليّات القديمة لم تُمسّ */
    public function test_platform_owner_still_reaches_the_old_protected_finance_group(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.finance.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.finance.audit'))->assertOk();
    }
}
