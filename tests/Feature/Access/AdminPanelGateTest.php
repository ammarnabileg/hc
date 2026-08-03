<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\PermissionExpander;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * ⭐ باب لوحة الإدارة (12.2.1-أ): «الشاشة نتيجةٌ للصلاحيّات لا صلاحيّةً بذاتها،
 * ومنه: لوحة الإدارة تظهر لمن له **أيّ** صلاحيّة».
 *
 * وأهمّ اختبارٍ هنا هو **المسح الكامل**: أيّ مسارٍ تحت `admin/` يفتحه متدرّبٌ
 * عاديّ يُسقِط البناء. فالحارس الذي يمنع تكرار المشكلة ليس إصلاح المسار الواحد،
 * بل الاختبار الذي يمسح جدول المسارات كلّه.
 */
class AdminPanelGateTest extends TestCase
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

    private function withRole(string $roleKey): User
    {
        $user = User::create([
            'name' => 'مستخدم '.$roleKey,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        $user->assignRole($roleKey);

        return $user;
    }

    // ------------------------------------------------------------------

    /** صاحب أيّ صلاحيّة إداريّة يفتح اللوحة — ولو كان كوردنيتور تطوّع */
    public function test_anyone_with_an_administrative_permission_opens_the_panel(): void
    {
        $access = app(AccessEngine::class);

        foreach (['platform_owner', 'super_admin', 'content_admin', 'auditor', 'director', 'coordinator', 'team_leader'] as $roleKey) {
            $this->assertTrue(
                $access->opensAdminPanel($this->withRole($roleKey)),
                "«{$roleKey}» معه صلاحيّات إداريّة ومع ذلك اتردّ عن باب اللوحة",
            );
        }
    }

    /** ولا يفتحها المستخدم النهائيّ */
    public function test_end_users_do_not_open_the_panel(): void
    {
        $access = app(AccessEngine::class);

        foreach (['trainee', 'pending_review'] as $roleKey) {
            $this->assertFalse($access->opensAdminPanel($this->withRole($roleKey)), $roleKey);
        }
    }

    /** والدايركتور والكوردنيتور يفتحان `/admin` فعلًا لا مبدئيًّا */
    public function test_a_director_reaches_the_admin_dashboard(): void
    {
        $this->actingAs($this->withRole('director'))->get('/admin')->assertOk();
        $this->actingAs($this->withRole('coordinator'))->get('/admin')->assertOk();
    }

    public function test_a_trainee_is_refused_at_the_admin_door(): void
    {
        $this->actingAs($this->withRole('trainee'))->get('/admin')->assertForbidden();
    }

    /**
     * ⭐ المسح الكامل: **كلّ** مسار GET تحت `admin/` يُردّ عن المتدرّب.
     * ولا عيّنة — فالثغرة الأصليّة كانت في مجموعاتٍ لم يخطر ببال أحد فحصُها.
     */
    public function test_no_admin_route_at_all_is_reachable_by_a_trainee(): void
    {
        $trainee = $this->withRole('trainee');
        $leaks = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if ($uri !== 'admin' && ! str_starts_with($uri, 'admin/')) {
                continue;
            }

            // المسارات ذات المعلمات تحتاج سجلّات — والحارس نفسه يسبق ربطَ النموذج
            if (str_contains($uri, '{')) {
                continue;
            }

            $checked++;
            $response = $this->actingAs($trainee)->get('/'.$uri);

            if (! $this->refused($response)) {
                $leaks[] = $uri.' ⟵ '.$response->getStatusCode();
            }
        }

        $this->assertGreaterThan(50, $checked, 'المسح لم يغطِّ عددًا معقولًا من المسارات');
        $this->assertSame([], $leaks, "مسارات إدارة مفتوحة لمتدرّب:\n".implode("\n", $leaks));
    }

    /** ونفس المسح على «تحت المراجعة» — أضعف حساب في المنصّة */
    public function test_no_admin_route_at_all_is_reachable_by_a_pending_user(): void
    {
        $pending = $this->withRole('pending_review');
        $leaks = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{')) {
                continue;
            }

            if ($uri !== 'admin' && ! str_starts_with($uri, 'admin/')) {
                continue;
            }

            $response = $this->actingAs($pending)->get('/'.$uri);

            if (! $this->refused($response)) {
                $leaks[] = $uri.' ⟵ '.$response->getStatusCode();
            }
        }

        $this->assertSame([], $leaks, "مسارات إدارة مفتوحة لحسابٍ تحت المراجعة:\n".implode("\n", $leaks));
    }

    /**
     * ⭐ دلالة «أيٌّ من» لا تصير بابًا خلفيًّا: مفتاح القراءة الشخصيّة
     * (`announcements.view@SELF`) لا يفتح شاشةَ إدارة محروسةً بـ
     * `announcements.list,announcements.view`.
     *
     * ⭐ والباب مفتوحٌ عمدًا في هذا الاختبار (بصلاحيّةٍ إداريّة أخرى) — وإلّا لمرّ
     * الاختبار بسبب **باب اللوحة** لا بسبب القاعدة التي يدّعي قياسها.
     */
    public function test_a_personal_read_key_does_not_open_an_administrative_screen(): void
    {
        $user = $this->withRole('trainee');
        $role = Role::create(['key' => 'r_feed', 'name_ar' => 'قارئ فيد', 'layer' => 'user']);

        app(PermissionExpander::class)->attachToRole($role, 'announcements.view', 'SELF');
        $user->assignRole($role);

        // سلطةٌ إداريّة لا علاقة لها بالتعليمات — تفتح الباب وحده
        $elsewhere = Role::create(['key' => 'r_store', 'name_ar' => 'مسؤول متجر', 'layer' => 'platform']);
        app(PermissionExpander::class)->attachToRole($elsewhere, 'store_products.list', 'ALL');
        $user->assignRole($elsewhere);
        app(AccessEngine::class)->forget($user);

        $this->assertTrue($user->allows('announcements.view'), 'يقرأ فيده هو');
        $this->assertTrue(app(AccessEngine::class)->opensAdminPanel($user), 'الباب مفتوح — فالقياس على الشاشة لا عليه');
        $this->assertFalse($user->allows('announcements.list'), 'ولا يملك المفتاح الإداريّ');

        $this->actingAs($user)->get('/admin/guidance')->assertForbidden();
    }

    // ------------------------------------------------------------------ ب-5

    /**
     * ⭐ **مَن يملك صلاحيّةً إداريّةً يدخل — ولو لم يحرس مفتاحُه مسارًا باسمه.**
     *
     * كانت `AdminPanelSurface::keys()` تحسب **246 مفتاحًا من 1033**، فيُردّ 403 عن
     * `/admin` كلُّ من يملك:
     *  • مفتاحًا لا يحرس مسارًا بذاته (`courses.manage` · `paths.manage` — و`manage`
     *    لا تُوضَع على مسار)،
     *  • أو مفتاحًا **يحمله المتدرّب أيضًا** فأُقصي كلّه (`store_products.list` ·
     *    `events.list` · `referrals.view`).
     */
    public function test_administrative_keys_outside_the_route_surface_open_the_panel(): void
    {
        foreach (['store_products.list', 'events.list', 'referrals.view', 'courses.manage', 'paths.manage'] as $key) {
            $user = $this->withRole('trainee');
            $role = Role::create([
                'key' => 'r_'.str_replace('.', '_', $key),
                'name_ar' => 'مسؤول '.$key,
                'layer' => 'platform',
            ]);

            DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => Permission::where('key', $key)->value('id'),
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $user->assignRole($role);
            app(AccessEngine::class)->forget();

            $this->actingAs($user)->get('/admin')->assertOk("«{$key}@ALL» اتردّ عن باب اللوحة");
        }
    }

    /** ودور **«فريق التوظيف»** (12.2.3-ب-17) — 49 صلاحيّة وكان يُردّ 403 */
    public function test_the_recruitment_team_template_opens_the_panel(): void
    {
        $recruiter = $this->withRole('recruiter');

        $this->assertGreaterThan(
            10,
            DB::table('permission_role')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->where('roles.key', 'recruiter')
                ->count(),
            'القالب لازم يكون محمَّلًا بصلاحيّاته وإلّا فالاختبار وهميّ',
        );

        $this->assertTrue(app(AccessEngine::class)->opensAdminPanel($recruiter));
        $this->actingAs($recruiter)->get('/admin')->assertOk();
    }

    /**
     * ⭐ **والانقلاب المضادّ محروس:** المفتاح المشترَك نفسه (`store_products.list@ALL`)
     * يحمله المتدرّب من قالبه — ولا يفتح له بابًا. فالفارق **مصدر الصفّ** لا اسمه.
     */
    public function test_the_very_same_key_from_the_end_user_template_opens_nothing(): void
    {
        $trainee = $this->withRole('trainee');

        $this->assertTrue($trainee->allows('store_products.list'), 'المتدرّب يقرأ كتالوج المتجر فعلًا');
        $this->assertFalse(app(AccessEngine::class)->opensAdminPanel($trainee));
        $this->actingAs($trainee)->get('/admin')->assertForbidden();
    }

    /** والباب لا يمنح شيئًا: مَن دخل بمفتاحٍ واحد لا يفتح إلّا شاشته هو */
    public function test_the_door_grants_nothing_beyond_itself(): void
    {
        $user = $this->withRole('trainee');
        $role = Role::create(['key' => 'r_only_store', 'name_ar' => 'مسؤول متجر', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'store_products.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);
        app(AccessEngine::class)->forget();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
        $this->actingAs($user)->get('/admin/finance')->assertForbidden();
    }

    private function refused(TestResponse $response): bool
    {
        return in_array($response->getStatusCode(), [401, 403, 404, 302], true);
    }
}
