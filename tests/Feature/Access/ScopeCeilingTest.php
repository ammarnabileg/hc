<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐ **سقف نطاق المصفوفة (12.2.2) يُفرَض — ورفضُه صريحٌ لا تضييقٌ صامت.**
 *
 * كان في الشاشتين عطبان متقابلان:
 *  • **استثناءات المستخدم:** التصديق `['required','string']` وحده، فيُكتَب
 *    `scope=ALL` على مفتاحٍ سقفُه TRACK **وينفُذ**.
 *  • **محرّر الأدوار:** العكس — النطاق الخارج عن السقف **يُستبدَل بالافتراضيّ**
 *    (`@ALL` ⟵ `@SELF`) بلا أيّ إخبار، وهو نقيض 12.2.1-د «يرى بعينه ما مُنِح».
 *
 * والسقف يُفرَض كذلك **وقت التقييم**، فصفٌّ زُرِع من خارج الشاشتين لا يُقرَأ إذنًا.
 */
class ScopeCeilingTest extends TestCase
{
    use RefreshDatabase;

    /** مفتاحٌ سقفه في المصفوفة دون ALL — عليه تقوم كلّ حالات هذا الملفّ */
    private const CAPPED_KEY = 'org_chart.view';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);

        // شرط الاختبار نفسه: المفتاح فعلًا مسقوف دون ALL في المصفوفة
        $this->assertNotContains(
            'ALL',
            Permission::where('key', self::CAPPED_KEY)->value('allowed_scopes'),
            'الاختبار قائم على مفتاحٍ سقفُه دون ALL في 12.2.2',
        );
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    private function owner(): User
    {
        $owner = $this->makeUser('مالك المنصّة');
        $owner->assignRole('platform_owner');

        return $owner;
    }

    // ------------------------------------------- استثناء المستخدم: كتابة مرفوضة

    /** ⭐ `scope=ALL` على مفتاحٍ سقفُه TRACK ⟵ **لا يُكتَب**، ورسالةٌ تقول لماذا */
    public function test_an_individual_override_beyond_the_ceiling_is_refused(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $response = $this->actingAs($owner)
            ->from(route('admin.permissions.index'))
            ->post(route('admin.permissions.update', $target), [
                'permission' => self::CAPPED_KEY,
                'scope' => 'ALL',
                'effect' => 'allow',
            ]);

        $response->assertSessionHasErrors();

        $this->assertSame(
            0,
            PermissionUser::where('user_id', $target->id)->count(),
            'ولا صفَّ واحد يُكتَب — لا بـALL ولا بغيرها تضييقًا صامتًا',
        );
    }

    /** والنطاق المسموح يمرّ **بنفسه** — فالرفض تقييمٌ لا عمًى، ولا يُصغَّر ما اختِير */
    public function test_a_scope_within_the_ceiling_is_written_as_chosen(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $this->actingAs($owner)
            ->from(route('admin.permissions.index'))
            ->post(route('admin.permissions.update', $target), [
                'permission' => self::CAPPED_KEY,
                'scope' => 'TRACK',
                'effect' => 'allow',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('TRACK', PermissionUser::where('user_id', $target->id)->value('scope'));
    }

    /** ونطاقٌ خارج القائمة الستّة أصلًا يُردّ بالتصديق لا بالتخمين */
    public function test_a_corrupt_scope_is_refused_by_validation(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $this->actingAs($owner)
            ->from(route('admin.permissions.index'))
            ->post(route('admin.permissions.update', $target), [
                'permission' => self::CAPPED_KEY,
                'scope' => 'ALLL',
                'effect' => 'allow',
            ])
            ->assertSessionHasErrors('scope');

        $this->assertSame(0, PermissionUser::where('user_id', $target->id)->count());
    }

    /** والمنع لا يُقيَّد بالسقف: توسيعه تشديدٌ لا تصعيد (12.2.1-ز-1) */
    public function test_a_deny_row_is_not_capped(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $this->actingAs($owner)
            ->from(route('admin.permissions.index'))
            ->post(route('admin.permissions.update', $target), [
                'permission' => self::CAPPED_KEY,
                'scope' => 'ALL',
                'effect' => 'deny',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('deny', PermissionUser::where('user_id', $target->id)->value('effect'));
    }

    // ----------------------------------------- محرّر الأدوار: رفضٌ لا تضييقٌ صامت

    /** ⭐ `@ALL` في محرّر الأدوار **لا يُحفَظ `@SELF` بلا إخبار** — يُرفَض ويُقال */
    public function test_the_role_editor_refuses_instead_of_narrowing_silently(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'r_ceiling', 'name_ar' => 'دور اختبار', 'layer' => 'platform']);
        $permission = Permission::where('key', self::CAPPED_KEY)->firstOrFail();

        $response = $this->actingAs($owner)
            ->from(route('admin.roles.edit', $role))
            ->put(route('admin.roles.update', $role), [
                'group' => $permission->group,
                'rows' => [
                    $permission->id => ['on' => '1', 'scope' => 'ALL', 'effect' => 'allow'],
                ],
            ]);

        $response->assertSessionHasErrors();

        $written = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertNull($written, 'ما اتحفظش سطر — ولا اتصغّر لـSELF في السرّ');
    }

    /** والنطاق المسموح يُحفَظ كما اختِير */
    public function test_the_role_editor_saves_an_allowed_scope_as_chosen(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'r_ok', 'name_ar' => 'دور اختبار', 'layer' => 'platform']);
        $permission = Permission::where('key', self::CAPPED_KEY)->firstOrFail();

        $this->actingAs($owner)
            ->from(route('admin.roles.edit', $role))
            ->put(route('admin.roles.update', $role), [
                'group' => $permission->group,
                'rows' => [
                    $permission->id => ['on' => '1', 'scope' => 'TRACK', 'effect' => 'allow'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('TRACK', DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->value('scope'));
    }

    // -------------------------------------------------- وقت التقييم كذلك

    /** ⭐ صفٌّ زُرِع بنطاقٍ فوق السقف من خارج الشاشتين **لا يُقرَأ إذنًا** */
    public function test_a_row_beyond_the_ceiling_is_not_read_as_a_grant(): void
    {
        $user = $this->makeUser('صاحب صفٍّ مزروع');
        $role = Role::create(['key' => 'r_raw', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', self::CAPPED_KEY)->value('id'),
            'scope' => 'ALL',      // فوق سقف المصفوفة
            'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);
        app(AccessEngine::class)->forget();

        $this->assertFalse($user->allows(self::CAPPED_KEY), 'ما يتجاوز نصّ المصفوفة لا يُمنَح عليه');

        // ونفس المفتاح بنطاقٍ منصوص يمرّ — فالرفض تقييمٌ لا عمًى
        DB::table('permission_role')->where('role_id', $role->id)->update(['scope' => 'TRACK']);
        app(AccessEngine::class)->forget();

        $this->assertTrue($user->allows(self::CAPPED_KEY));
    }
}
