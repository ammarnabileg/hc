<?php

namespace Tests\Feature\Access;

use App\Models\Course;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\ScopeResolver;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerOrgDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐ **النطاق يُقيَّم على الطلب لا على المبدأ** (الدستور 12.2.1-ب).
 *
 * الثغرة الأصليّة: `EnsurePermission` ينادي `allows($user, $key)` **بلا هدف** مهما
 * حمل المسار من هدف، و`ScopeResolver::covers()` تُرجع `true` حين لا هدف — فكانت
 * حمايةُ النطاق كلّها **تسقط عند الباب**: صاحب `org_chart.view@SELF` يفتح
 * `/volunteer/org/node/{membership}` لعضويّةٍ ليست له ويقرأ بياناتها.
 * والوجه المقلوب: صاحب نطاقٍ **يغطّي** الهدف يُردّ 403 لأنّ الكنترولر كان يستبدل
 * النطاق بقاعدة كيانٍ خاصّة به.
 *
 * وكلّ اختبارٍ هنا يقيس **الطلب نفسه** لا الدالّة وحدها: لو رجع الحارس إلى النداء
 * بلا هدف سقط أوّلُ اختبارٍ فورًا، ولو أُعيدت قاعدةُ الكيان الخاصّة سقط الثاني.
 */
class ScopeOnRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VolunteerOrgDemoSeeder::class);
    }

    // ------------------------------------------------------------------ أدوات

    private function actor(string $code, string $roleKey): User
    {
        $user = User::where('code', $code)->firstOrFail();
        $user->assignRole($roleKey, $user->memberships()->where('status', 'active')->firstOrFail());
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    private function membershipOf(string $code): Membership
    {
        return User::where('code', $code)->firstOrFail()
            ->memberships()->where('status', 'active')->firstOrFail();
    }

    private function plainUser(string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    /** إسناد صفٍّ خامٍ بنطاقٍ بعينه — نتجاوز به المحرّر عمدًا لنقيس المحرّك */
    private function grant(User $user, string $permissionKey, string $scope, string $layer = 'platform'): void
    {
        $role = Role::create([
            'key' => 'r_'.str()->random(8),
            'name_ar' => 'دور اختبار',
            'layer' => $layer,
        ]);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', $permissionKey)->value('id'),
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);
        app(AccessEngine::class)->forget();
    }

    // ------------------------------------- ⭐ الهجوم نفسه: SELF على هدفٍ أجنبيّ

    /**
     * `org_chart.view@SELF` على عضويّة **غيره** ⟵ 403.
     * (كانت 200 ببيانات أجنبيّة — أ-3 في الأوديت.)
     */
    public function test_a_self_scope_is_refused_on_a_foreign_membership(): void
    {
        $coordinator = $this->actor('VOL-C1', 'coordinator');
        $foreign = $this->membershipOf('VOL-C3');

        $this->assertSame(
            'SELF',
            DB::table('permission_role')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('roles.key', 'coordinator')
                ->where('permissions.key', 'org_chart.view')
                ->value('permission_role.scope'),
            'الاختبار قائم على أنّ سقف الكوردنيتور SELF (12.2.3-ب-16)',
        );

        $this->actingAs($coordinator)
            ->getJson(route('volunteer.org.node', $foreign))
            ->assertForbidden();

        /*
         | ⚠️ **وبوب-أب «قسمي» رجع لأصحابه — والتفريق مقصود لا نقضٌ لأ-3.**
         |
         | كان هنا سطرٌ يقيس `volunteer.department.member` بنفس المسطرة، وهو ما
         | أغلق الشاشة في وجه أصحابها: نصّ **24.4-7** يجعلها «تاب لكلّ **عضوٍ في
         | القسم** — القسم كاملًا حتى لو كنتُ في فرعيّ»، فمصدر الحقّ فيها
         | **عضويّة القسم** لا مشيُ سلسلة الإشراف. أمّا **«الهيكل التنظيميّ»**
         | أعلاه فشجرةٌ تُمشى بالنطاق ⟵ يبقى على المحرّك كما أصلحه أ-3، وهو
         | المقيس في هذه الحالة.
         |
         | وحارس «قسمي» وحدَّاه مقيسان في
         | `tests/Feature/Volunteer/Org/DepartmentMembershipGuardTest.php`:
         | عضوُ القسم ⟵ 200 على زميله · ومن قسمٍ آخر ⟵ 403 · وبلا المفتاح ⟵ 403.
         */
    }

    /** ونفسه على **عضويّته هو** ⟵ 200 — فالرفض تقييمٌ لا عمًى */
    public function test_the_same_self_scope_still_opens_his_own_node(): void
    {
        $coordinator = $this->actor('VOL-C1', 'coordinator');

        $this->actingAs($coordinator)
            ->getJson(route('volunteer.org.node', $this->membershipOf('VOL-C1')))
            ->assertOk();
    }

    // ------------------------------------- ⭐ الوجه المقلوب: نطاقٌ يغطّي الهدف

    /**
     * `org_chart.view@TRACK` على عضويّةٍ في **قسمٍ آخر من نفس المسار** ⟵ 200.
     *
     * وكانت 403: الكنترولر يبني «قسمي» حول **المشاهِد** ثمّ يشترط أن تكون العقدة
     * داخله — وهي قاعدة كيانٍ خاصّة تحلّ محلّ النطاق، فتردّ صاحبَ النطاق الأوسع
     * عن هدفٍ **يغطّيه نطاقُه** نصًّا (12.2.1-ب: TRACK = المسار كاملًا).
     */
    public function test_a_scope_that_covers_the_target_opens_it(): void
    {
        $foreign = $this->membershipOf('VOL-C3');
        $ownDepartment = Entity::find($foreign->entity_id);

        // قسمٌ آخر في **نفس المسار** — فالهدف خارج قسم المشاهِد وداخل نطاقه
        $otherDepartment = Entity::create([
            'track_id' => $ownDepartment->track_id,
            'parent_id' => null,
            'name_ar' => 'قسم آخر في نفس المسار',
            'status' => 'active',
            'opened_at' => now()->subYear(),
        ]);

        $watcher = $this->plainUser('مشرف المسار');
        Membership::create([
            'user_id' => $watcher->id,
            'entity_id' => $otherDepartment->id,
            'position_id' => Position::where('key', 'coordinator')->value('id'),
            'status' => 'active',
            'is_primary' => true,
            'started_at' => now()->subMonths(6),
        ]);

        $this->grant($watcher, 'org_chart.view', 'TRACK', 'volunteer');

        $this->assertNotSame(
            (int) $otherDepartment->id,
            (int) $ownDepartment->id,
            'الهدف لازم يكون خارج قسم المشاهِد وإلّا قاس الاختبار قاعدةَ الكيان لا النطاق',
        );

        $this->actingAs($watcher)
            ->getJson(route('volunteer.org.node', $foreign))
            ->assertOk();

        $this->actingAs($watcher)
            ->getJson(route('volunteer.department.member', $foreign))
            ->assertOk();
    }

    // --------------------------------------------- سلّم النطاقات على الطلب كلّه

    /** المسار **بلا هدف** يبقى فحصًا مبدئيًّا — فلا ينقلب الإصلاح قفلًا عامًّا */
    public function test_a_route_without_a_target_stays_a_principle_check(): void
    {
        $this->actingAs($this->actor('VOL-C1', 'coordinator'))
            ->get(route('volunteer.org'))
            ->assertOk();
    }

    /**
     * وهدفٌ **لا يحمل صاحبًا ولا كيانًا** (فعاليّة · تدريب) لا يقيس عليه النطاق شيئًا،
     * فيبقى على حارسه في المجال — وهي نفس قاعدة `guard => 'domain'` المعتمَدة.
     */
    public function test_a_target_the_scope_cannot_measure_is_left_to_its_domain(): void
    {
        $resolver = app(ScopeResolver::class);
        $user = $this->plainUser('متدرّب');
        $foreignMembership = $this->membershipOf('VOL-C3');

        // سجلٌّ يحمل صاحبًا وكيانًا ⟵ يُقاس، ويُرفَض حين لا يغطّيه النطاق
        $this->assertFalse($resolver->covers('SELF', $user, $foreignMembership, null));

        // وسجلٌّ لا يحمل صاحبًا ولا كيانًا (تدريب) ⟵ لا مقياس، فيحرسه المجال
        $course = Course::query()->first() ?? Course::create([
            'name_ar' => 'تدريب اختبار', 'slug' => 'test-course-'.str()->random(6), 'status' => 'draft',
        ]);

        $this->assertFalse(
            array_key_exists('user_id', $course->getAttributes())
            || array_key_exists('entity_id', $course->getAttributes()),
            'لو صار للتدريب صاحبٌ أو كيانٌ فهذا الاختبار يجب أن يُعاد بناؤه',
        );

        $this->assertTrue($resolver->covers('SELF', $user, $course, null));
    }

    // ------------------------------------------------- المحرّك نفسه على الأهداف

    /** SELF يرى نفسه ولا يرى غيره · وALL يرى الكلّ — على الهدف لا على المبدأ */
    public function test_the_engine_measures_the_target_for_every_scope(): void
    {
        $access = app(AccessEngine::class);

        $narrow = $this->plainUser('صاحب SELF');
        $wide = $this->plainUser('صاحب ALL');
        $stranger = $this->plainUser('غريب');

        $this->grant($narrow, 'users.view', 'SELF');
        $this->grant($wide, 'users.view', 'ALL');

        $this->assertTrue($access->allows($narrow, 'users.view', $narrow), 'SELF يرى نفسه');
        $this->assertFalse($access->allows($narrow, 'users.view', $stranger), 'SELF لا يرى غيره');
        $this->assertTrue($access->allows($wide, 'users.view', $stranger), 'ALL يرى الكلّ');

        // وبلا هدف يبقى الفحص مبدئيًّا للاثنين
        $this->assertTrue($access->allows($narrow, 'users.view'));
        $this->assertTrue($access->allows($wide, 'users.view'));
    }

    /** والحارس يوصل الهدف فعلًا: نفس المفتاح ونفس المسار، والفارق الهدف وحده */
    public function test_the_guard_passes_the_target_over_http(): void
    {
        $narrow = $this->plainUser('صاحب SELF');
        $wide = $this->plainUser('صاحب ALL');
        $this->grant($narrow, 'users.view', 'SELF');
        $this->grant($wide, 'users.view', 'ALL');

        $this->actingAs($narrow)->get(route('admin.users.show', $narrow))->assertOk();
        $this->actingAs($narrow)->get(route('admin.users.show', $wide))->assertForbidden();
        $this->actingAs($wide)->get(route('admin.users.show', $narrow))->assertOk();
    }
}
