<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\RoleEditor;
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

    /**
     * ⭐⭐ **`RoleEditor::save()` آمنة بذاتها — بلا المرور على حارس الكنترولر.**
     *
     * كان الفحص السابق موجودًا فقط في `RoleController::scopesBeyondCeiling()`
     * (حارسٌ يسبق النداء)، وداخل `save()` نفسها النطاق الخارج عن السقف كان
     * **يُستبدَل بالافتراضيّ صامتًا** (`@ALL` ⟵ `@SELF`) بلا رفضٍ ولا رسالة —
     * فمن نادى الخدمة مباشرةً (خارج هذا الكنترولر) كان يُصغَّر منحه في السرّ.
     * هذا الاختبار ينادي `save()` وحدها، متجاوزًا الكنترولر كلّيًّا.
     */
    public function test_role_editor_save_itself_refuses_instead_of_narrowing_silently(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'r_ceiling_direct', 'name_ar' => 'دور اختبار مباشر', 'layer' => 'platform']);
        $permission = Permission::where('key', self::CAPPED_KEY)->firstOrFail();

        $result = app(RoleEditor::class)->save($role, $owner, $permission->group, [
            $permission->id => ['on' => '1', 'scope' => 'ALL', 'effect' => 'allow'],
        ]);

        $this->assertNotSame([], $result['rejected'], 'save() نفسها ترفض — لا تصغّر النطاق صامتًا');
        $this->assertSame(0, $result['written']);

        $written = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertNull($written, 'ما اتحفظش سطر بنطاق @SELF ولا بأيّ نطاق آخر — لا وراثة صامتة (12.2.1-د)');
    }

    // ------------------------------------------ قاعدةٌ واحدة تحرس البابين

    /** السقف يُقرأ من المصفوفة نفسها — لا قائمةَ ثانية ولا اجتهاد */
    public function test_the_ceiling_rule_is_read_from_the_matrix_itself(): void
    {
        $access = app(AccessEngine::class);
        $allowed = Permission::where('key', self::CAPPED_KEY)->value('allowed_scopes');

        $this->assertSame($allowed, $access->allowedScopesOf(self::CAPPED_KEY));

        foreach ((array) config('access.scopes') as $scope) {
            $this->assertSame(
                in_array($scope, $allowed, true),
                $access->withinAllowedScopes(self::CAPPED_KEY, $scope),
                "الحكم على «{$scope}» يجب أن يطابق نصّ المصفوفة حرفيًّا",
            );
        }
    }

    // ------------------------------------- الإسنادات المزروعة: صفر صفٍّ فوق السقف

    /**
     * ⭐⭐ **ولا صفَّ واحدًا فوق السقف في الإسنادات المزروعة** (12.2.2).
     *
     * كان هنا اختبارٌ يقيس **رقمًا** (`assertGreaterThan(0, …)`) يوثّق أنّ 528 صفًّا
     * من 3965 نطاقُها خارج `allowed_scopes` — فكان يحرس الحالة لا القاعدة، ويسقط
     * يوم تُصلَح. وقد صولحت الصفوف بالقاعدة المنصوصة: **يُقصّ النطاق إلى السقف
     * ولا يُسحَب المنح** (والسطر التالي يحرس النصف الثاني). فصار الحارس هنا
     * **القاعدة نفسها**: صفر — ويسقط يوم يظهر صفٌّ واحد، لا يوم تُصلَح الصفوف.
     *
     * و**مالك المنصّة ليس استثناءً**: سلطته بنيويّة (12.2.1-ز-5) ويتخطّى الفحص
     * أصلًا، فقصُّ صفوفه لا ينقص من قدرته شيئًا (انظر الاختبار الأخير).
     */
    public function test_not_one_seeded_row_sits_above_the_matrix_ceiling(): void
    {
        $access = app(AccessEngine::class);

        $violations = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->get(['permissions.key as key', 'permission_role.scope', 'roles.key as role'])
            ->reject(fn ($row) => $access->withinAllowedScopes($row->key, $row->scope))
            ->map(fn ($row) => "{$row->role}: {$row->key}@{$row->scope}")
            ->values()
            ->all();

        $this->assertSame(
            [],
            $violations,
            'كلّ صفٍّ في `permission_role` نطاقُه داخل `allowed_scopes` المنصوصة في 12.2.2 — بلا استثناء',
        );
    }

    /**
     * ⭐ **والمصالحة قصَّت النطاق ولم تسحب المنح** (12.2.3).
     *
     * فالنصفُ الثاني من الحسم يُحرَس هنا: مفتاحٌ كان يُكتَب `@ALL` لدورٍ نصّ عليه
     * 12.2.3 يبقى **في يد الدور** بعد المصالحة، وإنّما بنطاقٍ داخل السقف. ولولا
     * هذا الحارس لأمكن إرضاءُ الاختبار السابق بحذف الصفوف — وهو نقيض 12.2.3.
     */
    public function test_the_reconciliation_narrowed_the_scope_and_kept_the_grant(): void
    {
        // مفاتيح سقفها دون ALL وكان الأدمن العامّ يحملها `@ALL` قبل المصالحة
        foreach (['tasks.approve' => 'TEAM', 'sub_departments.create' => 'ENTITY', 'course_notes.view' => 'SELF'] as $key => $expected) {
            $row = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->where('roles.key', 'super_admin')
                ->where('permissions.key', $key)
                ->first(['permission_role.scope']);

            $this->assertNotNull($row, "«{$key}» ما تنسحبش من الأدمن العامّ — القصّ نطاقٌ لا سحبُ منح (12.2.3)");
            $this->assertSame($expected, $row->scope, "«{$key}» تُقصّ إلى سقف المصفوفة لا أوسع (12.2.2)");
        }
    }

    /** ومالك المنصّة بعد قصّ صفوفه **كما كان**: سلطته بنيويّة لا تُقرَأ من صفّ (12.2.1-ز-5) */
    public function test_cutting_the_owners_rows_takes_nothing_from_him(): void
    {
        $owner = $this->owner();
        $access = app(AccessEngine::class);

        $this->assertSame('TEAM', DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->where('roles.key', 'platform_owner')
            ->where('permissions.key', 'tasks.approve')
            ->value('permission_role.scope'), 'صفوف المالك مقصوصةٌ كغيرها — للاتّساق');

        $this->assertTrue($access->allows($owner, 'tasks.approve'));
        $this->assertSame('ALL', $access->widestScope($owner, 'tasks.approve'));
    }
}
