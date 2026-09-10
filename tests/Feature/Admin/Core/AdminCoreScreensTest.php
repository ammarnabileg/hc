<?php

namespace Tests\Feature\Admin\Core;

use App\Models\AdAudience;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كلّ شاشة رئيسيّة في المجال تُفتَح وتُعرَض — اختبار قبول للواجهات (BUILD 6).
 */
class AdminCoreScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);

        $this->owner = User::create([
            'name' => 'مالك الشاشات',
            'email' => 'screens@test.local',
            'password' => 'secret-password',
            'code' => 'SCREEN01',
            'status' => 'active',
        ]);

        $this->owner->assignRole('platform_owner');
        app(AccessEngine::class)->forget();
    }

    /** الشاشات الأربع الرئيسيّة تفتح بلا كسر. */
    public function test_main_screens_render(): void
    {
        $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()->assertSee('لوحة القيادة');
        $this->actingAs($this->owner)->get(route('admin.dashboard', ['tab' => 'details']))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.users.index'))->assertOk()->assertSee('المستخدمون');
        $this->actingAs($this->owner)->get(route('admin.users.approvals'))->assertOk()->assertSee('طلبات الاعتماد');
        $this->actingAs($this->owner)->get(route('admin.users.segments'))->assertOk()->assertSee('شرائح الجمهور');
        $this->actingAs($this->owner)->get(route('admin.roles.index'))->assertOk()->assertSee('الأدوار والصلاحيّات');
        $this->actingAs($this->owner)->get(route('admin.roles.assign'))->assertOk()->assertSee('إسناد دور');
        $this->actingAs($this->owner)->get(route('admin.permissions.index'))->assertOk()->assertSee('مصفوفة الصلاحيّات');
    }

    /**
     * ⭐ 24.2: بحثٌ بلا نتائج في المستخدمين وطلبات الاعتماد والشرائح يقول كده
     * صراحةً — لا رسالة البداية المضلِّلة.
     */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message_across_screens(): void
    {
        $usersResponse = $this->actingAs($this->owner)
            ->get(route('admin.users.index', ['q' => 'zzzznotexist']))
            ->assertOk();
        $usersResponse->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $usersResponse->assertDontSee(
            setting('admin.users.empty_message', 'مفيش نتائج — امسح الفلاتر وجرّب تاني'),
            false,
        );

        $approvalsResponse = $this->actingAs($this->owner)
            ->get(route('admin.users.approvals', ['q' => 'zzzznotexist']))
            ->assertOk();
        $approvalsResponse->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $approvalsResponse->assertDontSee(
            setting('admin.approvals.empty_message', 'مفيش طلبات معلّقة — كلّ حاجة تمام'),
            false,
        );

        $segmentsResponse = $this->actingAs($this->owner)
            ->get(route('admin.users.segments', ['q' => 'zzzznotexist']))
            ->assertOk();
        $segmentsResponse->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $segmentsResponse->assertDontSee(
            setting('admin.segments.empty_message', 'ابنِ شريحتك الأولى'),
            false,
        );
    }

    /**
     * وطلبات الاعتماد والشرائح الفارغتان فعليًّا (بلا فلتر) تفضّلان رسالة
     * البداية الأصليّة. (شاشة المستخدمين نفسها لا تخلو أبدًا من مستخدمٍ فعليّ
     * — الأدمن صاحب الجلسة نفسه سطرٌ فيها — فلا مقابل «فارغة فعليًّا» لها.)
     */
    public function test_actually_empty_screens_without_filters_keep_the_original_start_message(): void
    {
        User::query()->where('status', 'pending')->update(['status' => 'active']);

        $this->actingAs($this->owner)
            ->get(route('admin.users.approvals'))
            ->assertOk()
            ->assertSee(setting('admin.approvals.empty_message', 'مفيش طلبات معلّقة — كلّ حاجة تمام'), false);

        AdAudience::query()->delete();

        $this->actingAs($this->owner)
            ->get(route('admin.users.segments'))
            ->assertOk()
            ->assertSee(setting('admin.segments.empty_message', 'ابنِ شريحتك الأولى'), false);
    }

    /** محرّر الدور: بحث + مجموعات + نطاق لكلّ سطر + Deny/Allow (12.2.1-ط). */
    public function test_role_editor_has_search_groups_scopes_and_effects(): void
    {
        $role = Role::where('key', 'auditor')->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('data-perm-search', false)
            ->assertSee('مجموعات الصلاحيّات')
            ->assertSee('النطاق')
            ->assertSee('منع');
    }

    /** كلّ تابات صفحة المستخدم تُحمَّل كسولًا وتُعرَض (12.1 · 2.15-ب). */
    public function test_user_detail_tabs_render(): void
    {
        $target = User::create([
            'name' => 'حساب التابات',
            'email' => 'tabs@test.local',
            'password' => 'secret-password',
            'code' => 'TABS0001',
            'status' => 'active',
        ]);

        foreach (['profile', 'tables', 'wallet', 'learning', 'certificates', 'security', 'admin', 'advanced', 'volunteer'] as $tab) {
            $this->actingAs($this->owner)
                ->get(route('admin.users.show', ['user' => $target, 'tab' => $tab]))
                ->assertOk();
        }
    }

    /** إسناد دور تطوّع داخل عضويّة يمرّ، ونطاقه يُشتقّ منها (12.2.1-و). */
    public function test_volunteer_role_assignment_inside_membership(): void
    {
        $target = User::create([
            'name' => 'متطوّع الشاشات',
            'email' => 'volunteer@test.local',
            'password' => 'secret-password',
            'code' => 'VOL00001',
            'status' => 'active',
        ]);

        $track = Track::where('key', 'department')->firstOrFail();
        $entity = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم الاختبار', 'status' => 'active']);
        $position = Position::where('key', 'coordinator')->firstOrFail();

        $membership = Membership::create([
            'user_id' => $target->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)
            ->post(route('admin.roles.assign.store'), [
                'user' => $target->id,
                'role' => Role::where('key', 'coordinator')->value('id'),
                'membership' => $membership->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'membership_id' => $membership->id,
        ]);
    }
}
