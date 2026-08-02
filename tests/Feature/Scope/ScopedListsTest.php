<?php

namespace Tests\Feature\Scope;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Scope\LayerPrecedence;
use App\Support\Scope\ScopeFilter;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐ **النطاق إلزاميّ مع كلّ صلاحيّة** (الدستور 12.2.1-ب).
 *
 * الحارس على المسار يفحص «هل يستطيع مبدئيًّا؟» بلا هدف، فكانت كلّ شاشات القوائم
 * تعرض بيانات المنصّة كاملةً لمن مُنِح نطاقًا ضيّقًا. هنا نثبت أنّ النطاق صار
 * يُطبَّق على **البيانات**: TEAM يرى داونلاينه المباشر، وENTITY لا يعبر كيانه،
 * وALL يرى الكلّ.
 *
 * ونثبت معها **أسبقيّة طبقة المنصّة** (12.2.1-ز-5).
 */
class ScopedListsTest extends TestCase
{
    use RefreshDatabase;

    private Entity $alpha;

    private Entity $alphaChild;

    private Entity $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);

        $track = Track::query()->first() ?? Track::create(['key' => 'departments', 'name_ar' => 'الأقسام']);

        $this->alpha = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم ألفا', 'status' => 'active']);
        $this->alphaChild = Entity::create(['track_id' => $track->id, 'parent_id' => $this->alpha->id, 'name_ar' => 'فرعيّ ألفا', 'status' => 'active']);
        $this->beta = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم بيتا', 'status' => 'active']);
    }

    // ------------------------------------------------------------------ أدوات

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

    private function permission(string $key): Permission
    {
        [$resource, $action] = explode('.', $key);

        return Permission::firstOrCreate(['key' => $key], [
            'resource' => $resource,
            'action' => $action,
            'group' => 'اختبار النطاق',
            'label_ar' => $key,
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);
    }

    /** إسناد فرديّ بنطاق محدّد — أضيق طريق لبناء حالة الاختبار */
    private function grant(User $user, string $key, string $scope): User
    {
        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $this->permission($key)->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    private function place(User $user, Entity $entity, ?Membership $upline = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::query()->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------------ TEAM

    /** TEAM يرى **داونلاينه المباشر وحده** — لا حفيدًا ولا غريبًا (12.2.1-ب). */
    public function test_team_scope_sees_direct_downline_only(): void
    {
        $leader = $this->makeUser('قائد الفريق');
        $leaderMembership = $this->place($leader, $this->alpha);

        $direct = $this->makeUser('عضو مباشر');
        $directMembership = $this->place($direct, $this->alpha, $leaderMembership);

        $grandchild = $this->makeUser('عضو غير مباشر');
        $this->place($grandchild, $this->alpha, $directMembership);

        $stranger = $this->makeUser('غريب');
        $this->place($stranger, $this->beta);

        $this->grant($leader, 'users.list', 'TEAM');
        $this->actingAs($leader);

        $ids = app(ScopeFilter::class)->visibleUserIds($leader, 'users.list');

        $this->assertContains($leader->id, $ids, 'صاحب النطاق يرى نفسه دائمًا');
        $this->assertContains($direct->id, $ids, 'TEAM = الداونلاين المباشر');
        $this->assertNotContains($grandchild->id, $ids, 'TEAM لا يمتدّ لعمقٍ ثانٍ — ذاك SUBTREE');
        $this->assertNotContains($stranger->id, $ids, 'لا سلطة عابرة للكيانات');
    }

    /** والاستعلام نفسه يُحصَر: قائمة المستخدمين تعود بالداونلاين المباشر فقط. */
    public function test_team_scope_narrows_the_users_query(): void
    {
        $leader = $this->makeUser('قائد');
        $leaderMembership = $this->place($leader, $this->alpha);

        $direct = $this->makeUser('عضو مباشر');
        $this->place($direct, $this->alpha, $leaderMembership);

        $stranger = $this->makeUser('غريب');
        $this->place($stranger, $this->beta);

        $this->grant($leader, 'users.list', 'TEAM');
        $this->actingAs($leader);

        $query = User::query();
        app(ScopeFilter::class)->applyToUsers($query, $leader, 'users.list');

        $ids = $query->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$leader->id, $direct->id], $ids);
    }

    // ---------------------------------------------------------------- SUBTREE

    /** SUBTREE يمتدّ لكلّ عمق تحت العضويّة. */
    public function test_subtree_scope_reaches_every_depth(): void
    {
        $head = $this->makeUser('رئيس');
        $headMembership = $this->place($head, $this->alpha);

        $mid = $this->makeUser('وسيط');
        $midMembership = $this->place($mid, $this->alpha, $headMembership);

        $leaf = $this->makeUser('طرف');
        $this->place($leaf, $this->alphaChild, $midMembership);

        $this->grant($head, 'users.list', 'SUBTREE');
        $this->actingAs($head);

        $ids = app(ScopeFilter::class)->visibleUserIds($head, 'users.list');

        $this->assertContains($mid->id, $ids);
        $this->assertContains($leaf->id, $ids);
    }

    // ----------------------------------------------------------------- ENTITY

    /** ENTITY يشمل الكيان وفروعه، و**لا يعبر لكيانٍ آخر** (23-0.2). */
    public function test_entity_scope_never_crosses_to_another_entity(): void
    {
        $director = $this->makeUser('دايركتور ألفا');
        $this->place($director, $this->alpha);

        $inChild = $this->makeUser('عضو الفرعيّ');
        $this->place($inChild, $this->alphaChild);

        $inBeta = $this->makeUser('عضو بيتا');
        $this->place($inBeta, $this->beta);

        $this->grant($director, 'users.list', 'ENTITY');
        $this->actingAs($director);

        $ids = app(ScopeFilter::class)->visibleUserIds($director, 'users.list');

        $this->assertContains($inChild->id, $ids, 'ENTITY يشمل الفروع');
        $this->assertNotContains($inBeta->id, $ids, 'ولا يعبر لكيان آخر');
    }

    /** وسجلّات كيانٍ آخر لا تظهر ولو كان الجدول جدول سجلّات لا أشخاص. */
    public function test_entity_scope_narrows_membership_rows(): void
    {
        $director = $this->makeUser('دايركتور');
        $this->place($director, $this->alpha);

        $other = $this->makeUser('عضو بيتا');
        $this->place($other, $this->beta);

        $this->grant($director, 'memberships.list', 'ENTITY');
        $this->actingAs($director);

        $query = Membership::query();
        app(ScopeFilter::class)->apply($query, $director, 'memberships.list', 'user_id', 'entity_id');

        $entityIds = $query->pluck('entity_id')->unique()->all();

        $this->assertNotContains($this->beta->id, $entityIds);
    }

    // -------------------------------------------------------------------- ALL

    /** ALL يرى الكلّ — بلا قيدٍ على الاستعلام. */
    public function test_all_scope_sees_everyone(): void
    {
        $admin = $this->makeUser('أدمن');
        $a = $this->makeUser('أ');
        $b = $this->makeUser('ب');

        $this->place($a, $this->alpha);
        $this->place($b, $this->beta);

        $this->grant($admin, 'users.list', 'ALL');
        $this->actingAs($admin);

        $this->assertNull(app(ScopeFilter::class)->visibleUserIds($admin, 'users.list'), 'ALL = بلا قيد');

        $query = User::query();
        app(ScopeFilter::class)->applyToUsers($query, $admin, 'users.list');

        $this->assertSame(User::query()->count(), $query->count());
    }

    /** وبلا إسنادٍ أصلًا: صفر صفوف — لا «كلّ شيء» بالخطأ. */
    public function test_no_grant_means_no_rows(): void
    {
        $nobody = $this->makeUser('بلا صلاحيّة');
        $this->makeUser('آخر');

        $query = User::query();
        app(ScopeFilter::class)->applyToUsers($query, $nobody, 'users.list');

        $this->assertSame(0, $query->count());
    }

    // ------------------------------------------- الشاشة نفسها عبر HTTP لا الاستعلام

    /**
     * وشاشة «قائمة المستخدمين» نفسها: صاحب `users.list@TEAM` يفتحها فيجد
     * **داونلاينه المباشر وحده** — لا دليل المنصّة كاملًا كما كان.
     */
    public function test_users_screen_shows_direct_downline_only_for_a_team_scope(): void
    {
        $leader = $this->makeUser('قائد الشاشة');
        $leaderMembership = $this->place($leader, $this->alpha);

        $direct = $this->makeUser('عضو ظاهر');
        $this->place($direct, $this->alpha, $leaderMembership);

        $stranger = $this->makeUser('عضو مخفيّ');
        $this->place($stranger, $this->beta);

        $this->grant($leader, 'users.list', 'TEAM');

        $users = $this->actingAs($leader)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->viewData('users');

        $ids = collect($users->items())->pluck('id')->all();

        $this->assertContains($direct->id, $ids);
        $this->assertNotContains($stranger->id, $ids, 'شاشة المستخدمين كانت تعرض المنصّة كاملةً لأيّ نطاق');
    }

    /** والنطاق ALL على الشاشة نفسها يرى الجميع — فالحصر نطاقٌ لا حجبٌ أعمى. */
    public function test_users_screen_shows_everyone_for_an_all_scope(): void
    {
        $admin = $this->makeUser('أدمن الشاشة');
        $this->place($admin, $this->alpha);

        $stranger = $this->makeUser('عضو بعيد');
        $this->place($stranger, $this->beta);

        $this->grant($admin, 'users.list', 'ALL');

        $users = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->viewData('users');

        $this->assertContains($stranger->id, collect($users->items())->pluck('id')->all());
    }

    // ---------------------------------------------- شاشات بعينها: الشهادات والخروج

    /** سجلّ شهادات التطوّع يُحصَر بالنطاق كبقيّة القوائم. */
    public function test_certificate_ledger_is_scoped(): void
    {
        $type = CertificateType::query()->first();
        $this->assertNotNull($type, 'CoreSeeder يزرع أنواع الشهادات');

        $director = $this->makeUser('دايركتور');
        $this->place($director, $this->alpha);

        $inside = $this->makeUser('داخل الكيان');
        $this->place($inside, $this->alpha);

        $outside = $this->makeUser('خارج الكيان');
        $this->place($outside, $this->beta);

        foreach ([$inside, $outside] as $holder) {
            Certificate::create([
                'user_id' => $holder->id,
                'certificate_type_id' => $type->id,
                'code' => str()->upper(str()->random(10)),
                'hash' => hash('sha256', (string) $holder->id),
                'status' => 'valid',
                'issued_at' => now(),
            ]);
        }

        $this->grant($director, 'certificate_ledger.list', 'ENTITY');
        $this->actingAs($director);

        $query = Certificate::query();
        app(ScopeFilter::class)->apply($query, $director, 'certificate_ledger.list');

        $owners = $query->pluck('user_id')->all();

        $this->assertContains($inside->id, $owners);
        $this->assertNotContains($outside->id, $owners);
    }

    /** وسجلّات الخروج كذلك — والجدول يحمل `user_id` لا `entity_id`. */
    public function test_offboarding_rows_are_scoped(): void
    {
        $supervisor = $this->makeUser('مشرف');
        $supervisorMembership = $this->place($supervisor, $this->alpha);

        $mine = $this->makeUser('عضوي');
        $this->place($mine, $this->alpha, $supervisorMembership);

        $notMine = $this->makeUser('عضو غيري');
        $this->place($notMine, $this->beta);

        foreach ([$mine, $notMine] as $subject) {
            Offboarding::create([
                'user_id' => $subject->id,
                'type' => 'resignation',
                'initiated_by' => $supervisor->id,
                'reason' => 'اختبار',
            ]);
        }

        $this->grant($supervisor, 'offboarding.view', 'TEAM');
        $this->actingAs($supervisor);

        $query = Offboarding::query();
        app(ScopeFilter::class)->apply($query, $supervisor, 'offboarding.view');

        $subjects = $query->pluck('user_id')->all();

        $this->assertContains($mine->id, $subjects);
        $this->assertNotContains($notMine->id, $subjects);
    }

    // ---------------------------------------- أسبقيّة طبقة المنصّة (12.2.1-ز-5)

    /**
     * حين يحكم على نفس الصلاحيّة حكمان من طبقتين، **حكم طبقة المنصّة هو الحاكم**:
     * مشرف عام التطوّع سقف طبقته وحدها، والأدمن العامّ يعلوه في كلّ تعارض.
     */
    public function test_platform_layer_ruling_governs_over_the_volunteer_layer(): void
    {
        $user = $this->makeUser('أدمن ومتطوّع معًا');
        $this->place($user, $this->alpha);

        $permission = $this->permission('volunteers.view');

        $platformRole = Role::create(['key' => 'test_platform_admin', 'name_ar' => 'أدمن اختبار', 'layer' => 'platform']);
        $volunteerRole = Role::create(['key' => 'test_volunteer_gm', 'name_ar' => 'مشرف تطوّع اختبار', 'layer' => 'volunteer']);

        DB::table('permission_role')->insert([
            ['role_id' => $platformRole->id, 'permission_id' => $permission->id, 'scope' => 'ENTITY', 'effect' => 'allow', 'created_at' => now(), 'updated_at' => now()],
            ['role_id' => $volunteerRole->id, 'permission_id' => $permission->id, 'scope' => 'TRACK', 'effect' => 'allow', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $user->assignRole($platformRole);
        $user->assignRole($volunteerRole);
        app(AccessEngine::class)->forget();

        $layers = app(LayerPrecedence::class);

        $this->assertSame('TRACK', app(AccessEngine::class)->widestScope($user, 'volunteers.view'), 'الأوسع مجرّدًا هو حكم طبقة التطوّع');
        $this->assertSame('ENTITY', $layers->governingScope($user, 'volunteers.view'), 'لكنّ حكم طبقة المنصّة هو الحاكم (12.2.1-ز-5)');
    }

    /** ومالك المنصّة فوق الجميع — ولا يعلوه حكم أيّ طبقة. */
    public function test_platform_owner_outranks_every_layer(): void
    {
        $owner = $this->makeUser('مالك المنصّة');
        Role::firstOrCreate(['key' => config('access.owner_role')], ['name_ar' => 'مالك المنصّة', 'layer' => 'platform']);
        $owner->assignRole(config('access.owner_role'));

        $volunteerRole = Role::create(['key' => 'test_gm', 'name_ar' => 'مشرف عام التطوّع', 'layer' => 'volunteer']);
        $gm = $this->makeUser('مشرف عام التطوّع');
        $gm->assignRole($volunteerRole);

        app(AccessEngine::class)->forget();

        $layers = app(LayerPrecedence::class);

        $this->assertTrue($layers->outranks($owner, $gm));
        $this->assertFalse($layers->outranks($gm, $owner));
        $this->assertSame('ALL', $layers->governingScope($owner, 'volunteers.view'));
    }

    /** والأدمن العامّ يعلو مشرف عام التطوّع — نصّ 12.2.1-ز-5 حرفيًّا. */
    public function test_general_admin_outranks_the_volunteer_general_supervisor(): void
    {
        $admin = $this->makeUser('أدمن عامّ');
        $admin->assignRole(Role::create(['key' => 'test_super_admin', 'name_ar' => 'أدمن عامّ', 'layer' => 'platform']));

        $gm = $this->makeUser('مشرف عام التطوّع');
        $gm->assignRole(Role::create(['key' => 'test_volunteer_general', 'name_ar' => 'مشرف عام التطوّع', 'layer' => 'volunteer']));

        app(AccessEngine::class)->forget();

        $layers = app(LayerPrecedence::class);

        $this->assertTrue($layers->outranks($admin, $gm));
        $this->assertSame('platform', $layers->layerOf($admin));
        $this->assertSame('volunteer', $layers->layerOf($gm));
    }
}
