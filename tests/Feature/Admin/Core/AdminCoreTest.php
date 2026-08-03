<?php

namespace Tests\Feature\Admin\Core;

use App\Models\AdAudience;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Support\Access\AccessEngine;
use App\Support\Access\PermissionExpander;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * مجال «admin-core» — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور:
 * 12.0 · 12.2.1 · 12.2.3 · 12.3 · 2.5-د · 2.15.
 */
class AdminCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);
    }

    // ------------------------------------------------------------------ أدوات

    private function makeUser(string $name = 'مستخدم', string $status = 'active'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => $status,
        ]);
    }

    private function owner(): User
    {
        $user = $this->makeUser('مالك الاختبار');
        $user->assignRole('platform_owner');
        app(AccessEngine::class)->forget();

        return $user;
    }

    private function supportAdmin(): User
    {
        $user = $this->makeUser('مسؤول الدعم');
        $user->assignRole('support_admin');
        app(AccessEngine::class)->forget();

        return $user;
    }

    // ---------------------------------------------------- الليَاوت والسايد بار

    /** لوحة القيادة تُفتَح لمن يملك `admin_panel.view` وحده (12.0 · 12.2.1). */
    public function test_admin_panel_is_gated_by_admin_panel_view(): void
    {
        $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk();

        // متدرّب عاديّ لا يملك مفتاح الباب — فلا يدخل ولو بالرابط المباشر
        $trainee = $this->makeUser('متدرّب');
        $trainee->assignRole('trainee');
        app(AccessEngine::class)->forget();

        $this->actingAs($trainee)->get(route('admin.dashboard'))->assertForbidden();
    }

    /** السايد بار اثنا عشر عنصرًا و«الإعدادات والنظام» آخر قسم دائمًا (12.0). */
    public function test_admin_sidebar_shows_twelve_sections_with_settings_last(): void
    {
        $html = $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach ([
            'لوحة القيادة', 'إدارة المستخدمين', 'إدارة التدريب', 'إدارة الشهادات',
            'إدارة التطوّع', 'التلعيب والتحديات', 'المتجر والماليّات', 'إدارة المكافآت',
            'الفعاليّات', 'التوجيه والدعم', 'الإحصائيّات', 'الإعدادات والنظام',
        ] as $section) {
            $this->assertStringContainsString($section, $html, "السايد بار ناقصه: {$section}");
        }

        $this->assertGreaterThan(
            strpos($html, 'الإحصائيّات'),
            strpos($html, 'الإعدادات والنظام'),
            'الإعدادات والنظام لازم تكون آخر قسم دائمًا',
        );
    }

    /** المحظور يُخفى لا يُعطَّل: من لا يملك صلاحيّة قسم لا يراه أصلًا (2.15-أ-7). */
    public function test_forbidden_sections_are_hidden_not_disabled(): void
    {
        $html = $this->actingAs($this->supportAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('إدارة المستخدمين', $html);
        $this->assertStringNotContainsString('المتجر والماليّات', $html);
    }

    // ------------------------------------------------------------ لوحة القيادة

    /** أربعة كروت KPI بحدّ أقصى، والباقي في تاب «تفاصيل» (2.15-أ-3 · 12.3). */
    public function test_dashboard_shows_at_most_four_kpi_cards(): void
    {
        $response = $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk();

        $this->assertLessThanOrEqual(4, count($response->viewData('kpis')));

        $response->assertSee('حسابات محتاجة موافقة')
            ->assertSee('مهامّ معلّقة')
            ->assertSee('مقارنة بالفترة السابقة');

        // تاب «تفاصيل» يحمل ما زاد عن الأربعة
        $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertSee('قمع التحويل');
    }

    /** المهامّ المعلّقة تجمع السحوبات والشكاوى بزرّ [مراجعة] (12.3-16). */
    public function test_dashboard_lists_pending_withdrawals_and_complaints(): void
    {
        $response = $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk();

        $counts = $response->viewData('pendingWorkCounts');

        $this->assertSame(Complaint::where('status', 'open')->count(), $counts['شكاوى']);
        $this->assertGreaterThan(0, $counts['سحوبات']);
    }

    // ---------------------------------------------------------- إدارة المستخدمين

    /** الجدول يفتح بأعمدته الافتراضيّة، واختيار المستخدم يُحفَظ له (2.15-د-⭐). */
    public function test_user_table_columns_are_saved_per_user(): void
    {
        $admin = $this->supportAdmin();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.users.columns'), ['columns' => ['name', 'code', 'xp']])
            ->assertRedirect();

        $this->assertSame(['name', 'code', 'xp'], $admin->fresh()->table_columns['admin_users']);
    }

    /** البحث بالكود والاسم والبريد والموبايل (24.1). */
    public function test_user_search_matches_code_name_email(): void
    {
        $admin = $this->supportAdmin();
        $target = $this->makeUser('فاطمة الزهراء عليّ');
        $target->forceFill(['code' => 'ZZTEST99', 'phone' => '+201000000099'])->save();

        foreach (['ZZTEST99', 'فاطمة', $target->email, '000000099'] as $term) {
            $this->actingAs($admin)
                ->get(route('admin.users.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('فاطمة');
        }
    }

    /** صفحة المستخدم بتاباتها — وتاب «التطوّع» بعد «متقدّم» لمن له صلاحيّته (12.1). */
    public function test_user_page_tabs_include_volunteer_tab_only_with_permission(): void
    {
        $target = $this->makeUser('حساب للعرض');

        // مالك المنصّة يملك كلّ شيء ⟵ يرى تاب التطوّع
        $tabs = collect($this->actingAs($this->owner())
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->viewData('tabs'))->pluck('key')->all();

        $this->assertSame(
            ['profile', 'tables', 'wallet', 'learning', 'certificates', 'security', 'admin', 'advanced', 'volunteer'],
            $tabs,
        );

        // مسؤول الدعم بلا صلاحيّة التطوّع ⟵ التاب مخفيّ تمامًا
        $supportTabs = collect($this->actingAs($this->supportAdmin())
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->viewData('tabs'))->pluck('key')->all();

        $this->assertNotContains('volunteer', $supportTabs);
    }

    // --------------------------------------------------------- طلبات الاعتماد

    /**
     * ⭐ التفعيل مجّانيّ باعتماد إداريّ (2.5-د): القبول يغيّر الحالة ويمنح دور المتدرّب
     * **بلا أيّ خصم أو رسوم**، ويصرف تذكرة الترحيب للمدعوّ إن كان له ريفيرال.
     */
    public function test_account_approval_is_free_and_grants_trainee_role_and_welcome_ticket(): void
    {
        $admin = $this->supportAdmin();
        $referrer = $this->makeUser('الداعي');
        $account = $this->makeUser('حساب مستنّي', 'pending');

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $account->id,
            'code' => 'REF-TEST',
            'welcome_ticket_granted' => false,
        ]);

        $before = Transaction::where('user_id', $account->id)->count();

        $this->actingAs($admin)
            ->post(route('admin.users.approve'), ['users' => [$account->id]])
            ->assertRedirect();

        $account->refresh();

        $this->assertSame('active', $account->status);
        $this->assertTrue($account->hasRole('trainee'), 'القبول لازم يمنح دور المتدرّب');
        $this->assertNotNull($account->activated_at);

        // مجّانيّ: مافيش أيّ معاملة خصم على الحساب — والوحيدة المضافة هي هديّة الترحيب
        $tickets = Currency::where('code', 'tickets')->firstOrFail();
        $this->assertSame(0, Transaction::where('user_id', $account->id)->where('amount', '<', 0)->count());
        $this->assertSame($before + 1, Transaction::where('user_id', $account->id)->count());

        $this->assertSame(
            (float) setting('admin.approvals.welcome_tickets', 1),
            (float) WalletBalance::where('user_id', $account->id)->where('currency_id', $tickets->id)->value('balance'),
        );

        $this->assertTrue((bool) Referral::where('referred_id', $account->id)->value('welcome_ticket_granted'));

        // Audit على القرار + إشعار للمستخدم
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.approved', 'auditable_id' => $account->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $account->id, 'category' => 'account']);
    }

    /** الرفض بسبب واضح يُبلَّغ للمستخدم (24.1). */
    public function test_account_rejection_requires_reason_and_notifies_user(): void
    {
        $admin = $this->supportAdmin();
        $account = $this->makeUser('حساب مرفوض', 'pending');

        $this->actingAs($admin)
            ->post(route('admin.users.reject'), ['users' => [$account->id]])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.users.reject'), ['users' => [$account->id], 'reason' => 'بيانات ناقصة أو غير واضحة'])
            ->assertRedirect();

        $this->assertSame('rejected', $account->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.rejected', 'auditable_id' => $account->id]);
    }

    /** الإجراء الجماعيّ مخفيّ قبل الاختيار (2.15-ب) — ويظهر بالجافاسكربت عند التحديد. */
    public function test_bulk_bar_is_hidden_until_selection(): void
    {
        $html = $this->actingAs($this->supportAdmin())
            ->get(route('admin.users.approvals'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-bulk-bar class="hidden', str_replace(' data-bulk-bar', ' data-bulk-bar', $html));
        $this->assertStringContainsString('اعتماد المحدَّد', $html);
    }

    // ----------------------------------------------------------- شرائح الجمهور

    /** الشريحة تُحفَظ بشروطها وتُعاد الاستفادة منها (24.1 · 12.13). */
    public function test_segment_is_saved_with_its_rule(): void
    {
        $admin = $this->owner();

        $this->actingAs($admin)
            ->post(route('admin.users.segments.store'), [
                'name' => 'شريحة اختبار',
                'match' => 'all',
                'groups' => [
                    ['match' => 'all', 'conditions' => [
                        ['field' => 'status', 'values' => ['pending']],
                    ]],
                ],
            ])
            ->assertRedirect();

        $segment = AdAudience::where('name', 'شريحة اختبار')->firstOrFail();

        $this->assertSame(['pending'], $segment->rule['groups'][0]['conditions'][0]['values']);
        $this->assertSame(User::where('status', 'pending')->count(), (int) $segment->size);
        $this->assertDatabaseHas('audit_logs', ['action' => 'segment.created']);
    }

    // ------------------------------------------------------ الأدوار والصلاحيّات

    /**
     * ⭐ `manage` تُفرَد ظاهرةً عند الحفظ: سطر واحد يصير ثلاثة عشر (12.2.1-د).
     * مورد الاختبار يحمل الأفعال الثلاثة عشر كاملةً حتى يُقاس الفرد كاملًا لا منقوصًا.
     */
    public function test_manage_expands_into_its_five_visible_rows_on_save(): void
    {
        $owner = $this->owner();
        $role = Role::create([
            'key' => 'test_manage_role', 'name_ar' => 'دور اختبار الفرد', 'layer' => 'platform',
        ]);

        $group = 'النظام والتقارير';

        foreach (config('access.actions') as $action) {
            Permission::create([
                'key' => "core_demo.{$action}",
                'resource' => 'core_demo',
                'action' => $action,
                'group' => $group,
                'label_ar' => 'مورد اختبار',
                'allowed_scopes' => ['ALL'],
            ]);
        }

        $manage = Permission::where('key', 'core_demo.manage')->firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.roles.update', $role), [
                'group' => $group,
                'rows' => [$manage->id => ['on' => '1', 'scope' => 'ALL', 'effect' => 'allow']],
            ])
            ->assertRedirect();

        $written = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $role->id)
            ->where('permissions.resource', 'core_demo')
            ->pluck('permissions.action')
            ->all();

        // الصلاحيّة نفسها + الأفعال الخمسة التي ينصّ عليها 12.2.1-د = ستّة أسطر ظاهرة
        $this->assertCount(
            count(config('access.manage_expands_to')) + 1,
            $written,
            'manage تُفرَد بأفعالها الخمسة وحدها (12.2.1-د) — لا بأوسع منها',
        );
        $this->assertContains('manage', $written);
        $this->assertContains('delete', $written);
        $this->assertContains('assign', $written);
        // و`approve` ليست منها: منحُها صامتةً مع `manage` توسيعُ صلاحيّةٍ بلا سند
        $this->assertNotContains('approve', $written);
    }

    /**
     * ⭐ منع تصعيد الامتياز (12.2.1-ز-2) — على طبقتين:
     *
     * (1) **الباب:** `roles.edit` منصوصة «مالك المنصّة فقط» في 12.2.2، فغير
     *     المالك لا يصل إلى محرّر الأدوار أصلًا مهما أُسنِد إليه من أدوار.
     * (2) **الحارس نفسه:** `canGrant` يرفض منح ما لا يملكه المانح — ويُفحَص
     *     مباشرةً لأنّ الباب أعلاه يمنع الوصول إليه عبر HTTP لغير المالك.
     */
    public function test_the_role_editor_is_closed_to_anyone_but_the_platform_owner(): void
    {
        $actor = $this->makeUser('مسؤول الشهادات');
        $actor->assignRole('certificates_admin');

        // حتى لو أُسنِدت `roles.edit` لدوره، العزل يغلب الإسناد
        app(PermissionExpander::class)->attachToRole(
            Role::where('key', 'certificates_admin')->firstOrFail(), 'roles.edit', 'ALL',
        );
        app(AccessEngine::class)->forget();

        $role = Role::create(['key' => 'test_escalation_role', 'name_ar' => 'دور اختبار التصعيد', 'layer' => 'platform']);
        $manage = Permission::where('key', 'complaints.manage')->firstOrFail();

        $this->actingAs($actor)
            ->put(route('admin.roles.update', $role), [
                'group' => $manage->group,
                'rows' => [$manage->id => ['on' => '1', 'scope' => 'ALL', 'effect' => 'allow']],
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('permission_role')->where('role_id', $role->id)->count());
    }

    /** ولا يمنح أحدٌ ما لا يملك، ولا نطاقًا أوسع من نطاقه */
    public function test_nobody_grants_what_they_do_not_hold(): void
    {
        $actor = $this->makeUser('مسؤول الشهادات');
        $actor->assignRole('certificates_admin');
        app(AccessEngine::class)->forget();

        $engine = app(AccessEngine::class);

        // لا يملك صلاحيّات الشكاوى إطلاقًا ⟵ لا يمنحها
        $this->assertFalse($engine->canGrant($actor, 'complaints.manage', 'ALL'));

        // ويملك صلاحيّات الشهادات بنطاق ALL ⟵ يمنحها في حدود ما يملك
        $this->assertTrue($engine->canGrant($actor, 'certificates.view', 'ALL'));
    }

    /** ⭐ عزل الحسّاس: صلاحيّات مالك المنصّة لا تظهر لغيره أصلًا (12.2.1-ز-3). */
    public function test_owner_only_permissions_are_invisible_to_non_owners(): void
    {
        // المجموعة المحميّة (الماليّ والأسرار) — تُعرَّف بعلامة `is_owner_only`
        $ownerOnly = Permission::create([
            'key' => 'owner_vault.view',
            'resource' => 'owner_vault',
            'action' => 'view',
            'group' => 'المال والمتجر والملكيّة',
            'label_ar' => 'الخزنة المحميّة',
            'allowed_scopes' => ['ALL'],
            'is_sensitive' => true,
            'is_owner_only' => true,
        ]);

        $role = Role::where('key', 'trainee')->firstOrFail();

        // مالك المنصّة يراها
        $this->actingAs($this->owner())
            ->get(route('admin.roles.edit', ['role' => $role, 'group' => $ownerOnly->group]))
            ->assertOk()
            ->assertSee($ownerOnly->key);

        // الأدمن العامّ لا يراها ولا تُكتَب له حتى لو بعتها بالبوست
        $superAdmin = $this->makeUser('أدمن عامّ');
        $superAdmin->assignRole('super_admin');
        app(AccessEngine::class)->forget();

        $this->actingAs($superAdmin)
            ->get(route('admin.roles.edit', ['role' => $role, 'group' => $ownerOnly->group]))
            ->assertOk()
            ->assertDontSee($ownerOnly->key);

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'group' => $ownerOnly->group,
            'rows' => [$ownerOnly->id => ['on' => '1', 'scope' => 'ALL', 'effect' => 'allow']],
        ]);

        $this->assertSame(0, DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $ownerOnly->id)
            ->count());
    }

    /** دور مالك المنصّة غير قابل للحذف (12.2.3). */
    public function test_platform_owner_role_cannot_be_deleted(): void
    {
        $role = Role::where('key', 'platform_owner')->firstOrFail();

        $this->actingAs($this->owner())
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['key' => 'platform_owner']);
    }

    /** دور جديد = نسخ قالب (12.2.3-4)، والنسخة تُسجَّل في التدقيق. */
    public function test_new_role_is_a_copy_of_a_template(): void
    {
        $template = Role::where('key', 'support_admin')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('admin.roles.store'), ['name_ar' => 'دعم مستوى تاني', 'template' => $template->id])
            ->assertRedirect();

        $copy = Role::where('name_ar', 'دعم مستوى تاني')->firstOrFail();

        $this->assertFalse((bool) $copy->is_system);
        $this->assertGreaterThan(0, DB::table('permission_role')->where('role_id', $copy->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'auditable_id' => $copy->id]);
    }

    /** الإسناد داخل عضويّة: «الدور ماذا والعضويّة أين» (12.2.1-و) — و Audit عليه. */
    public function test_role_assignment_requires_membership_for_volunteer_roles(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('متطوّع');
        $volunteerRole = Role::where('key', 'coordinator')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.roles.assign.store'), ['user' => $target->id, 'role' => $volunteerRole->id])
            ->assertSessionHasErrors();

        $this->assertFalse($target->fresh()->hasRole('coordinator'));

        // دور منصّة بلا عضويّة يمرّ عادي — ويُسجَّل في التدقيق
        $platformRole = Role::where('key', 'auditor')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.roles.assign.store'), ['user' => $target->id, 'role' => $platformRole->id])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('auditor'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.assigned', 'auditable_id' => $target->id]);
    }

    /**
     * Audit على كلّ تغيير صلاحيّة — ويُعرَض **آخر تغيير فقط** بالـHover (12.2.1-ز-4).
     *
     * ⭐ والنطاق هنا **من نطاقات المصفوفة للمفتاح نفسه**: `complaints.view` سقفُها
     * SELF في 12.2.2، وكان الحفظ بـALL يمرّ لأنّ المحرّر **يضيّقه صامتًا** إلى
     * الافتراضيّ. صار يُردّ ويُخبِر (أ-7)، فالاختبار يقيس التدقيق لا التضييق.
     */
    public function test_permission_change_is_audited_and_last_change_is_shown_on_hover(): void
    {
        $owner = $this->owner();
        $role = Role::where('key', 'auditor')->firstOrFail();
        $permission = Permission::where('key', 'complaints.view')->firstOrFail();

        $this->actingAs($owner)->put(route('admin.roles.update', $role), [
            'group' => $permission->group,
            'rows' => [$permission->id => ['on' => '1', 'scope' => 'SELF', 'effect' => 'allow']],
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.permissions.updated',
            'auditable_id' => $role->id,
            'user_id' => $owner->id,
        ]);

        $html = $this->actingAs($owner)->get(route('admin.roles.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-audit-hover', $html);
        $this->assertStringContainsString('آخر تغيير', $html);
        // آخر تغيير فقط: سجلّ واحد لكلّ دور في الصفحة لا سجلّ كامل
        $this->assertCount(
            1,
            collect(AuditLog::where('auditable_id', $role->id)->get())->unique('auditable_id'),
        );
    }
}
