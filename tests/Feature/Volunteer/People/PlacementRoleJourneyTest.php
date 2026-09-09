<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Models\VolunteerRecording;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Volunteer\People\PlacementService;
use App\Services\Volunteer\People\PositionRoleAssigner;
use App\Support\Access\AccessEngine;
use App\Support\Access\MembershipContext;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **ب-1: التسكين يمنح دور البوزشن — والرحلة تنتهي إلى لوحةٍ تُفتَح لا إلى 403.**
 *
 * كان `PlacementService::activate()` يُنشئ `Membership` بلا إسناد أيّ دور، فالمتطوّع
 * يمرّ بالرحلة المنصوصة كاملةً (13.4-هـ: «الزرّ يتحوّل لوحة التطوّع») ثمّ يجد **403
 * على كلّ تابّ**. وهذا الملفّ يقطع الرحلة من طرفها إلى طرفها ويقيس **الطلب نفسه**:
 * التوابّ المنصوصة **200** بعد القبول، و**403** بعد الأوفبوردنج.
 *
 * ومعه **ب-4** (13.4-ل): تابّا الأكاديمية داخل نفس القائمة — «التسجيلات/المسارات
 * تظهر للمتطوّع **حسب قسمه**».
 */
class PlacementRoleJourneyTest extends PeopleTestCase
{
    /**
     * توابّ لوحة التطوّع المنصوصة (24.4 · 13.4-هـ · 13.4-ل) — بأسماء مساراتها
     * لا بروابط محروقة، فلو تغيّر الرابط بقي الاختبار يقيس الشاشة نفسها.
     */
    private const TABS = [
        'volunteer.overview',
        'volunteer.tasks.index',
        'volunteer.department',
        'volunteer.org',
        'volunteer.kudos',
        'volunteer.transactions',
        'volunteer.contributions',
        'volunteer.reviews',
        'volunteer.academy',
        'volunteer.academy.recordings',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // الأدوار العشرون وإسناداتها (12.2.3) — بدونها لا معنى لقياس «الدور يُمنَح»
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /** الرحلة كاملةً: مرشّح ⟵ طلب تسكين ⟵ قبول المرشّح نفسه */
    private function place(string $name = 'متطوّع الرحلة', string $positionKey = 'coordinator', ?int $entityId = null): array
    {
        $service = app(PlacementService::class);

        $user = $this->makeUser($name);

        $candidate = RecruitmentCandidate::create([
            'user_id' => $user->id,
            'stage' => 'final_list',
            'qualifying_score' => 92,
            'applied_at' => now()->subDays(4),
        ]);

        $entity = $entityId
            ? Entity::findOrFail($entityId)
            : $this->makeEntity('قسم '.$name);

        $position = Position::firstWhere('key', $positionKey);

        $request = $service->request($candidate, $entity, $position, $this->makeUser('مشرف التوظيف'));
        $service->respond($request, 'accepted', $user);

        $membership = Membership::where('user_id', $user->id)->where('entity_id', $entity->id)->firstOrFail();

        app(AccessEngine::class)->forget();
        app(MembershipContext::class)->set(null);

        return [$user->fresh(), $membership, $entity];
    }

    private function seedClearanceItems(): void
    {
        Setting::updateOrCreate(['key' => 'volunteer.offboarding.clearance_items'], [
            'group' => 'volunteer',
            'label_ar' => 'بنود التصفية',
            'type' => 'json',
            'default_value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
            'value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
        ]);

        Cache::forget('settings');
    }

    private function offboard(User $user): void
    {
        $actor = $this->makeUser('دايركتور الإنهاء');
        $this->seedClearanceItems();

        $record = OffboardingService::open($user, 'resignation', 'ظروف دراسة', $actor, []);
        $record->forceFill([
            'clearance_checklist' => collect(OffboardingService::clearanceItems())
                ->map(fn ($label) => ['label' => $label, 'done' => true])->all(),
        ])->save();

        OffboardingService::complete($record->fresh(), $actor);

        app(AccessEngine::class)->forget();
        app(MembershipContext::class)->set(null);
    }

    // ------------------------------------------------------------- ب-1

    /** ⭐ التسكين يُسنِد دور البوزشن **داخل العضويّة** — لا فوقها ولا بلا سياق */
    public function test_placement_assigns_the_position_role_inside_the_membership(): void
    {
        [$user, $membership] = $this->place();

        $row = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->first(['roles.key as role_key', 'role_user.membership_id']);

        $this->assertNotNull($row, 'التسكين بلا دور = لوحة تطوّع على 403 (13.4-هـ)');
        $this->assertSame('coordinator', $row->role_key, 'الدور المُسنَد هو دور البوزشن المُسنَد نفسه (12.2.3-ب)');
        $this->assertSame($membership->id, (int) $row->membership_id, 'الإسناد داخل العضويّة — فلا سلطة عابرة للكيانات');
    }

    /**
     * ⭐⭐ **الرحلة كاملةً تنتهي إلى لوحةٍ تُفتَح:** كلّ تابّ منصوص = **200**.
     * وهذا هو عين ما كان **403** قبل الإصلاح.
     */
    public function test_every_stated_tab_answers_200_after_the_placement_is_accepted(): void
    {
        [$user] = $this->place();

        foreach (self::TABS as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertStatus(200, "التابّ «{$route}» لازم يفتح للمتطوّع المُسكَّن (13.4-هـ · 13.4-ل)");
        }
    }

    /** والوجه المقلوب: **بلا تسكين لا لوحة** — فالاختبار يقيس أثر التسكين لا حالةً عامّة */
    public function test_the_same_tabs_are_403_for_a_user_who_was_never_placed(): void
    {
        $stranger = $this->makeUser('بلا تسكين');

        foreach (self::TABS as $route) {
            $this->actingAs($stranger)
                ->get(route($route))
                ->assertStatus(403, "التابّ «{$route}» ما ينفتحش لغير المُسكَّن");
        }
    }

    /** ⭐ وبإنهاء العضويّة **يُسحَب الدور** — فالمُقصى لا يبقى يملك ما لا يستحقّ (13.4-س) */
    public function test_offboarding_takes_the_position_role_back(): void
    {
        [$user] = $this->place('خارج بعد خدمة');

        $this->assertSame(1, DB::table('role_user')->where('user_id', $user->id)->count());

        $this->offboard($user);

        $this->assertSame(0, DB::table('role_user')->where('user_id', $user->id)->count(),
            'الدور يذهب مع العضويّة — وإلّا بقي الخارج حاملًا صلاحيّات لم تعد له');

        foreach (self::TABS as $route) {
            $this->actingAs($user->fresh())
                ->get(route($route))
                ->assertStatus(403, "التابّ «{$route}» لازم يقفل بعد الأوفبوردنج");
        }
    }

    /**
     * ⭐ **مَن له عضويّتان ببوزشنين:** سحب دورِ إحداهما لا يجرّده من الأخرى.
     * والقيد الفريد `(role_id, user_id, membership_id)` يسمح بالصفّين — والإسناد
     * عبر `syncWithoutDetaching` كان يُحرّك الصفّ الواحد بينهما بدل أن يفتح ثانيًا.
     */
    public function test_ending_one_of_two_memberships_keeps_the_other_role(): void
    {
        [$user, $first] = $this->place('صاحب عضويّتين', 'coordinator');

        /*
         | ⭐ بوزشن ثانٍ **في مسارٍ آخر** — لا قسمٍ آخر: «يجوز للمتطوّع الجمع بين
         | بوزشنات مختلفة في المسارات الثلاثة في نفس الوقت» (23-0.2)، لكنّ الحدّ
         | **1 لكلّ مسار** يمنع قسمين معًا لنفس الشخص (`TrackCapacityGuard`).
         */
        $service = app(PlacementService::class);
        $second = Entity::create([
            'track_id' => Track::firstWhere('key', 'governorate')->id,
            'name_ar' => 'محافظة الإشراف',
            'status' => 'active',
        ]);

        $candidate = RecruitmentCandidate::where('user_id', $user->id)->firstOrFail();
        $request = $service->request($candidate->fresh(), $second, Position::firstWhere('key', 'supervisor'), $this->makeUser('مشرف'));
        $service->respond($request, 'accepted', $user);

        $secondMembership = Membership::where('user_id', $user->id)->where('entity_id', $second->id)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['coordinator', 'supervisor'],
            DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)->pluck('roles.key')->all(),
            'العضويّتان دوران — لا دورٌ واحد يتنقّل بينهما',
        );

        // إنهاء الأولى وحدها
        app(PositionRoleAssigner::class)->revoke($first);

        $this->assertSame(
            ['supervisor'],
            DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)->pluck('roles.key')->all(),
            'سحب دور عضويّةٍ لا يمسّ دور الأخرى',
        );

        // ولا يزال يفتح لوحته من داخل العضويّة الباقية
        app(AccessEngine::class)->forget();
        app(MembershipContext::class)->set($secondMembership);

        $this->assertTrue($user->fresh()->allows('personal_reports.view', null, $secondMembership));
    }

    /** «أخوكم» عنصر شرفيّ **بلا صلاحيّات** (13.4-ص) — فبوزشنه لا يقابله دور */
    public function test_the_honorary_position_grants_no_role(): void
    {
        $assigner = app(PositionRoleAssigner::class);

        $this->assertNull($assigner->roleFor(Position::firstWhere('key', 'brother')));
        $this->assertNotNull($assigner->roleFor(Position::firstWhere('key', 'coordinator')));
    }

    /** كلّ بوزشنٍ يُقابله دورُه هو — لا دورٌ واحد للجميع (12.2.3-ب) */
    public function test_each_position_maps_to_its_own_role(): void
    {
        $assigner = app(PositionRoleAssigner::class);

        foreach (['coordinator', 'team_leader', 'supervisor', 'director', 'track_supervisor', 'volunteer_gm'] as $key) {
            $role = $assigner->roleFor(Position::firstWhere('key', $key));

            $this->assertInstanceOf(Role::class, $role);
            $this->assertSame($key, $role->key);
            $this->assertSame('volunteer', $role->layer, 'دور بوزشنٍ يُسنَد داخل عضويّة — طبقتُه `volunteer`');
        }
    }

    // ------------------------------------------------------------- ب-4

    /**
     * ⭐ **الأكاديمية تصل جمهورها** (13.4-ل) — والنطاق **حسب قسمه** لا أوسع:
     * تسجيلُ قسمٍ آخر لا يُطالَب به المتطوّع.
     */
    public function test_the_academy_is_scoped_to_the_volunteers_own_department(): void
    {
        [$user, $membership, $entity] = $this->place('متطوّع الأكاديمية');

        $mine = VolunteerRecording::create([
            'entity_id' => $entity->id,
            'title' => 'تسجيل قسمي',
            'url' => 'https://drive.example/mine',
            'otp' => '4321',
            'status' => 'published',
        ]);

        $foreign = VolunteerRecording::create([
            'entity_id' => $this->makeEntity('قسم أجنبيّ')->id,
            'title' => 'تسجيل قسم تانٍ',
            'url' => 'https://drive.example/foreign',
            'otp' => '4321',
            'status' => 'published',
        ]);

        app(AccessEngine::class)->forget();
        app(MembershipContext::class)->set($membership);

        $this->assertTrue($user->allows('academy_recordings.view', $mine, $membership), 'تسجيل قسمه يُقرَأ');
        $this->assertFalse($user->allows('academy_recordings.view', $foreign, $membership), 'وتسجيل قسمٍ أجنبيّ لا');
    }

    /** ولا يحمل المتطوّع **كتابةً** في الأكاديمية — الإضافة لمسؤول القسم بصلاحيّة (13.4-ل · 12.2.3-ب-18) */
    public function test_a_volunteer_gets_read_only_on_the_academy(): void
    {
        $coordinator = Role::where('key', 'coordinator')->firstOrFail();

        $actions = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $coordinator->id)
            ->whereIn('permissions.resource', ['academy_paths', 'academy_recordings'])
            ->pluck('permissions.action')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['list', 'view'], $actions, 'الكوردنيتور يقرأ الأكاديمية ولا يكتب فيها');
    }
}
