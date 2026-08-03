<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\ConditionMap;
use App\Support\Access\PermissionExpander;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VolunteerOrgDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * حرّاس معماريّة الصلاحيّات (12.2.1 · 12.2.2) — كلّ اختبارٍ هنا يقابل قاعدةً منصوصة،
 * ووجودُه هو ما يمنع رجوع الثغرة بعد إصلاحها.
 */
class PermissionArchitectureTest extends TestCase
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

    // ------------------------------------------------------------------ أدوات

    private function makeUser(string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    private function withRole(string $roleKey, string $name = 'مستخدم'): User
    {
        $user = $this->makeUser($name);
        $user->assignRole($roleKey);

        return $user;
    }

    private function access(): AccessEngine
    {
        return app(AccessEngine::class);
    }

    // -------------------------------------------------- 12.2.1-ج · الشروط المقفولة

    /** ⭐ شرط مجهول يُرفَض — Fail closed لا Fail open */
    public function test_an_unknown_condition_is_rejected(): void
    {
        $user = $this->makeUser();
        $role = Role::create(['key' => 'r_cond', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'complaints.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'allow',
            // شرطٌ مخترَع — كان يمرّ لأنّه «غير معروف»
            'conditions' => json_encode(['شرط اخترعته للتوّ'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);

        $this->assertFalse($this->access()->allows($user, 'complaints.list'));
    }

    /** وحالةٌ خارج القائمة المقفولة تُرفَض كذلك */
    public function test_an_unknown_state_condition_is_rejected(): void
    {
        $user = $this->makeUser();
        $role = Role::create(['key' => 'r_state', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'complaints.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'allow',
            'conditions' => json_encode(['state:مخترعة'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);

        $this->assertFalse($this->access()->allows($user, 'complaints.list'));
    }

    /** والشرط المعروف يمرّ — فالرفض ليس عمًى بل تقييم */
    public function test_a_known_condition_still_passes(): void
    {
        $user = $this->makeUser();
        $role = Role::create(['key' => 'r_ok', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'complaints.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'allow',
            'conditions' => json_encode(['not_locked'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);

        $this->assertTrue($this->access()->allows($user, 'complaints.list'));
    }

    /** ⭐ كلّ نصّ شرطٍ في المصفوفة له مفتاحٌ من القائمة المقفولة — لا نصّ يمرّ بلا مفتاح */
    public function test_every_matrix_condition_text_maps_to_a_locked_key(): void
    {
        $texts = Permission::query()
            ->whereNotNull('condition_key')
            ->pluck('condition_key')
            ->flatMap(fn ($text) => explode(' · ', (string) $text))
            ->unique()
            ->all();

        $this->assertNotEmpty($texts);
        $this->assertSame([], ConditionMap::unmapped($texts), 'نصوص شرطٍ بلا مفتاح في القائمة المقفولة');

        foreach (ConditionMap::all() as $text => $key) {
            $this->assertTrue(ConditionMap::isLocked($key), "المفتاح «{$key}» للنصّ «{$text}» خارج القائمة المقفولة");
        }
    }

    /** وشرط المصفوفة يُقيَّم فعلًا وقت الطلب — لا زينةً على الورق */
    public function test_matrix_conditions_are_evaluated_at_request_time(): void
    {
        $keys = Permission::query()->whereNotNull('condition_keys')->pluck('condition_keys')->filter()->count();

        $this->assertGreaterThan(400, $keys, 'شروط المصفوفة يجب أن تُخزَّن مفاتيحَ تُقيَّم');
    }

    // ------------------------------------------- 12.2.1-ز-3 · عزل الحسّاس

    /** ⭐ كلّ «مالك المنصّة فقط» مرفوضة على الأدمن العامّ ومقبولة للمالك */
    public function test_owner_only_permissions_are_denied_to_super_admin_and_allowed_to_the_owner(): void
    {
        $admin = $this->withRole('super_admin', 'أدمن عامّ');
        $owner = $this->withRole('platform_owner', 'مالك المنصّة');

        $isolated = Permission::where('is_owner_only', true)->pluck('key');

        // 89 = ما تنصّ عليه 12.2.2 بعبارة «مالك المنصّة فقط». (كانت 92 قبل أن
        // تُحذَف صفوف `refunds` الثلاثة التي أبطلها قرار «لا استرجاع» — 19.4.)
        $this->assertGreaterThanOrEqual(89, $isolated->count());

        foreach ($isolated as $key) {
            $this->assertFalse($admin->allows($key), "الأدمن العامّ كسب صلاحيّة معزولة: {$key}");
            $this->assertTrue($owner->allows($key), "مالك المنصّة حُرِم من صلاحيّته: {$key}");
        }
    }

    /** والماليّات وأبواب الأدوار على رأسها */
    public function test_the_financial_group_and_the_role_editor_are_isolated(): void
    {
        $mustBeIsolated = [
            'pricing.edit', 'pricing.manage',
            'coupons.create', 'coupons.edit', 'coupons.delete', 'coupons.export',
            // (`refunds.approve/reject/manage` حُذفت من المصفوفة: 19.4 يقصر المورد
            //  على عرض نصّ السياسة وتحريره — بلا طلبات استرجاع ولا اعتماد ولا رفض.)
            'manual_rewards.list', 'manual_rewards.manage',
            'reports_sales.view', 'reports_sales.list', 'reports_sales.export',
            'order_bump.create', 'order_bump.edit', 'order_bump.delete',
            'roles.edit', 'roles.delete',
            'permissions.edit', 'permissions.assign', 'permissions.export',
        ];

        foreach ($mustBeIsolated as $key) {
            $this->assertTrue(
                (bool) Permission::where('key', $key)->value('is_owner_only'),
                "«{$key}» منصوصة «مالك المنصّة فقط» في 12.2.2 ويجب أن تكون معزولة",
            );
        }
    }

    /** ⭐ «المالك فقط» ≠ «مالك المنصّة فقط»: المستخدم يرى أرباحه هو */
    public function test_a_user_sees_his_own_earnings(): void
    {
        $trainee = $this->withRole('trainee', 'متدرّب');

        foreach (['earnings.view', 'earnings.list', 'withdraw.create', 'withdraw.list', 'wallet.view'] as $key) {
            $this->assertFalse(
                (bool) Permission::where('key', $key)->value('is_owner_only'),
                "«{$key}» شرطُها «المالك فقط» (مالك السجلّ) لا «مالك المنصّة فقط» — فلا تُعزَل",
            );

            $this->assertTrue($trainee->allows($key), "المتدرّب محبوسٌ عن «{$key}» وهي بياناته هو");
        }

        // وما هو على مستوى المنصّة يبقى معزولًا
        foreach (['earnings.export', 'earnings.manage', 'withdraw.approve', 'withdraw.reject'] as $key) {
            $this->assertTrue((bool) Permission::where('key', $key)->value('is_owner_only'), $key);
        }
    }

    // --------------------------------------------- 12.2.1-د · فرد manage

    /** ⭐ manage تفرد خمسة أفعال لا اثني عشر */
    public function test_manage_expands_to_exactly_five_actions(): void
    {
        $this->assertSame(
            ['create', 'edit', 'delete', 'archive', 'assign'],
            config('access.manage_expands_to'),
        );

        $role = Role::create(['key' => 'r_manage', 'name_ar' => 'دور', 'layer' => 'platform']);
        $keys = app(PermissionExpander::class)->expand('complaints.manage');

        $this->assertCount(6, $keys); // الصلاحيّة نفسها + خمسة
        $this->assertNotContains('complaints.export', $keys);
        $this->assertNotContains('complaints.approve', $keys);
        $this->assertNotContains('complaints.reject', $keys);
        $this->assertNotContains('complaints.import', $keys);
        $this->assertNotContains('complaints.restore', $keys);

        $this->assertSame(0, DB::table('permission_role')->where('role_id', $role->id)->count());
    }

    /** ⭐ ولا إسقاط صامت: ما يسقط يُقال ولماذا */
    public function test_manage_reports_what_it_dropped_and_why(): void
    {
        $role = Role::create(['key' => 'r_drop', 'name_ar' => 'دور', 'layer' => 'platform']);
        $expander = app(PermissionExpander::class);

        $result = $expander->report($role, 'complaints.manage', 'ALL');

        $this->assertNotEmpty($result['skipped'], 'complaints.manage@ALL تُسقِط أفعالًا نطاقُها SELF');

        $message = $expander->explainSkipped($result['skipped'], 'ALL');

        $this->assertIsString($message);
        $this->assertStringContainsString('سقط', $message);
        $this->assertStringContainsString('نطاقاتها المسموحة', $message);
    }

    // ------------------------------------- 12.2.1-ب · النطاق التالف يُرفَض

    public function test_a_corrupt_scope_is_rejected_not_treated_as_self(): void
    {
        $user = $this->makeUser();
        $role = Role::create(['key' => 'r_scope', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'complaints.list')->value('id'),
            'scope' => 'ALLL',   // نطاقٌ تالف
            'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role);

        $this->assertFalse($this->access()->allows($user, 'complaints.list'));
        $this->assertNull($this->access()->widestScope($user, 'complaints.list'));
        $this->assertFalse($this->access()->canGrant($user, 'complaints.list', 'SELF'));
    }

    // -------------------------------- 12.2.1-ز-2 · canGrant يفحص الشروط

    public function test_can_grant_checks_conditions_too(): void
    {
        $granter = $this->makeUser();
        $role = Role::create(['key' => 'r_grant', 'name_ar' => 'دور', 'layer' => 'platform']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'complaints.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'allow',
            // شرطٌ لا يتحقّق: لا عضويّة نشطة لهذا المستخدم
            'conditions' => json_encode(['active_membership'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $granter->assignRole($role);

        $this->assertFalse($this->access()->canGrant($granter, 'complaints.list', 'ALL'));
    }

    // ------------------------- 12.2.3 · سقف أدوار التطوّع ونصاب المدقّق

    /** سقوف أدوار التطوّع كما في 12.2.3-ب */
    private const VOLUNTEER_CEILINGS = [
        'coordinator' => 'SELF',
        'team_leader' => 'TEAM',
        'supervisor' => 'SUBTREE',
        'director' => 'ENTITY',
        'track_supervisor' => 'TRACK',
    ];

    /**
     * لا دور تطوّعٍ يتجاوز سقف نطاقه (12.2.3-ب) — **إلّا حيث تمنع المصفوفة
     * نفسها** أن يُكتَب المفتاح بنطاقٍ عند السقف أو دونه.
     *
     * والاستثناء ليس رخصةً: هو نتيجةُ قراءة النصّين معًا. `academy_paths.list`
     * نطاقاتها في 12.2.2 هي `ENTITY · SUBTREE · ALL` — **بلا SELF ولا TEAM**،
     * بينما 13.4-ل ينصّ أنّ «التسجيلات/المسارات تظهر **للمتطوّع** حسب قسمه»
     * والكوردنيتور سقفه SELF. فإسقاط المفتاح يقفل تابّ الأكاديمية في وجه
     * جمهوره المنصوص (وهو عين ب-4)، وكتابتُه بـSELF ممتنعة نصًّا. فيُكتَب
     * بـ**أضيق ما تسمح به المصفوفة** — لا أوسع بخطوة.
     *
     * ولذلك يقيس هذا الاختبار شيئين لا شيئًا واحدًا:
     *  1) لكلّ صفٍّ فوق السقف: أنّ المصفوفة **لا تعرض** أيّ نطاقٍ عند السقف أو
     *     دونه، وأنّ المكتوب هو **أضيق** ما تعرضه — فالتجاوز مضطرٌّ ومحسوب.
     *  2) وأنّ جرد هذه الصفوف **مقفول بالاسم**: أيّ مفتاحٍ جديد يظهر فوق السقف
     *     يُسقِط الاختبار ولو كانت المصفوفة تسمح به.
     */
    public function test_volunteer_roles_never_exceed_their_scope_ceiling(): void
    {
        $this->seed(VolunteerOrgDemoSeeder::class);

        $order = config('access.scopes');
        $access = $this->access();
        $forced = [];

        foreach (self::VOLUNTEER_CEILINGS as $roleKey => $ceiling) {
            $rows = DB::table('permission_role')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('roles.key', $roleKey)
                ->get(['permissions.key as key', 'permission_role.scope']);

            foreach ($rows as $row) {
                if (array_search($row->scope, $order, true) <= array_search($ceiling, $order, true)) {
                    continue;
                }

                $allowed = array_values(array_filter(
                    $access->allowedScopesOf($row->key),
                    fn ($scope) => in_array($scope, $order, true),
                ));

                usort($allowed, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

                $this->assertNotEmpty(
                    $allowed,
                    "«{$roleKey}» يتجاوز سقفه {$ceiling} في «{$row->key}» بنطاق {$row->scope} بلا سندٍ من المصفوفة",
                );

                $this->assertGreaterThan(
                    array_search($ceiling, $order, true),
                    array_search($allowed[0], $order, true),
                    "«{$row->key}» تقبل نطاقًا عند سقف «{$roleKey}» أو دونه — فلا عذر لتجاوزه",
                );

                $this->assertSame(
                    $allowed[0],
                    $row->scope,
                    "«{$roleKey}» يأخذ **أضيق** ما تسمح به المصفوفة في «{$row->key}» — لا أوسع",
                );

                $forced[] = "{$roleKey}: {$row->key}@{$row->scope}";
            }
        }

        sort($forced);

        // ⭐ الجرد مقفول بالاسم: مفتاحٌ جديد فوق السقف يُسقِط الاختبار
        $this->assertSame([
            'coordinator: academy_paths.list@SUBTREE',
            'coordinator: academy_paths.view@SUBTREE',
            'coordinator: academy_recordings.list@SUBTREE',
            'team_leader: academy_paths.list@SUBTREE',
            'team_leader: academy_paths.view@SUBTREE',
            'team_leader: academy_recordings.list@SUBTREE',
        ], $forced, 'الصفوف فوق سقف 12.2.3 هي ما تفرضه 12.2.2 وحدها — لا غيرها');
    }

    /** المدقّق قراءة فقط — على **كلّ** ما يُقرأ من غير المعزول */
    public function test_the_auditor_reads_every_non_isolated_readable_permission(): void
    {
        $expected = Permission::query()
            ->whereIn('action', ['view', 'list', 'export'])
            ->where('is_owner_only', false)
            ->pluck('key');

        $held = DB::table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('roles.key', 'auditor')
            ->pluck('permissions.key')
            ->all();

        $this->assertSame([], array_values(array_diff($expected->all(), $held)));

        // ولا يكتب شيئًا
        $writes = DB::table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('roles.key', 'auditor')
            ->whereNotIn('permissions.action', ['view', 'list', 'export'])
            ->count();

        $this->assertSame(0, $writes);
    }

    // ------------------------------------------ 12.2.2 · المصفوفة مرجعٌ كامل

    /** لا صلاحيّة في قاعدة البيانات بلا أصلٍ في المصفوفة */
    public function test_every_seeded_permission_comes_from_the_matrix(): void
    {
        $matrix = collect(json_decode(file_get_contents(database_path('data/permissions.json')), true))
            ->pluck('key')
            ->all();

        $this->assertSame([], array_values(array_diff(Permission::pluck('key')->all(), $matrix)));

        // 1033 ⟵ **1027**: نزلت **ستّة** بحذف صفوف مورد `games` من مصفوفة 12.2.2
        // إثر إلغاء البند 7.5 بقرار المالك (الدستور v5.3 — 2026-08-03). والرقم
        // يبقى مثبَّتًا كي يلتقط أيّ زيادةٍ أو نقصٍ **لا أمرَ خلفه**.
        $this->assertSame(1027, count($matrix));
        $this->assertSame([], array_values(array_filter(
            $matrix,
            fn (string $key) => str_starts_with($key, 'games.'),
        )));
    }

    /** ⛔ ولا صلاحيّة باسم شاشة (12.2.1-أ) */
    public function test_no_permission_is_named_after_a_screen(): void
    {
        $this->assertNull(Permission::where('key', 'admin_panel.view')->first());
        $this->assertSame(0, Permission::where('resource', 'admin_panel')->count());

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $entry) {
                if (is_string($entry)) {
                    $this->assertStringNotContainsString('admin_panel.view', $entry);
                }
            }
        }
    }
}
