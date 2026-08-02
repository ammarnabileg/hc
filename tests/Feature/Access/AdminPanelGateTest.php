<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\PermissionExpander;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     */
    public function test_a_personal_read_key_does_not_open_an_administrative_screen(): void
    {
        $user = $this->withRole('trainee');
        $role = Role::create(['key' => 'r_feed', 'name_ar' => 'قارئ فيد', 'layer' => 'user']);

        app(PermissionExpander::class)->attachToRole($role, 'announcements.view', 'SELF');
        $user->assignRole($role);

        $this->assertTrue($user->allows('announcements.view'), 'يقرأ فيده هو');
        $this->actingAs($user)->get('/admin/guidance')->assertForbidden();
    }

    private function refused(TestResponse $response): bool
    {
        return in_array($response->getStatusCode(), [401, 403, 404, 302], true);
    }
}
