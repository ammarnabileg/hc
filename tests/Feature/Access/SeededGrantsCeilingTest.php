<?php

namespace Tests\Feature\Access;

use App\Support\Access\AccessEngine;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐⭐ **سقف المصفوفة يُفرَض على كلّ الأبواب لا على باب الإنتاج وحده** (12.2.2).
 *
 * ================== النصّان الحاكمان ==================
 * • **12.2.2** تحدّد لكلّ مفتاح نطاقاتِه المسموحة ⟵ فصفٌّ خارجها مخالفة.
 * • **12.2.3** تحدّد أيّ الموارد يغطّيها الدور ⟵ فسحبُ المنح مخالفة.
 * والحسم: **يُقصّ النطاق ولا يُسحَب المنح** — ويُصان النصّان معًا.
 *
 * ================== لماذا هذا الملفّ غير `ScopeCeilingTest`؟ ==================
 * ذاك يبذر مسار الإنتاج (`RolePermissionSeeder`) وحده، وفيه صار صفرًا. لكنّ
 * **سيدرات العرض الستّة** كانت تكتب في `permission_role` مباشرةً من بابٍ خلفيّ
 * لا يمرّ بنقطة القصّ، فيعود الخرق كاملًا مع أوّل `DemoSeeder` — وحارسٌ يحرس
 * بابًا من بابين لا يحرس شيئًا. فالمقياس هنا **الشجرة المزروعة كلّها**.
 */
class SeededGrantsCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    /** ⭐ ولا صفَّ واحدًا فوق السقف بعد **الإنتاج والعرض معًا** */
    public function test_not_one_seeded_row_sits_above_the_matrix_ceiling(): void
    {
        $access = app(AccessEngine::class);

        $violations = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->get(['permissions.key as key', 'permission_role.scope', 'roles.key as role'])
            ->reject(fn ($row) => $access->withinAllowedScopes($row->key, $row->scope))
            ->map(fn ($row) => "{$row->role}: {$row->key}@{$row->scope}")
            ->sort()->values()->all();

        $this->assertSame(
            [],
            $violations,
            'كلّ صفٍّ في `permission_role` — بعد سيدرات العرض كذلك — نطاقُه داخل `allowed_scopes` (12.2.2)',
        );
    }

    /**
     * ⭐⭐ **والقصّ نطاقٌ لا سحبُ منح** (12.2.3).
     *
     * لولا هذا الحارس لأمكن إرضاءُ الاختبار السابق بحذف سطور السيدرات — وهو
     * نقيض 12.2.3. فهذه مفاتيحُ كان **كلّ** ما تسمح به المصفوفة أوسعَ من نطاق
     * الدور، فبقيت في يده بأضيق نطاقٍ منصوص بدل أن تسقط:
     * `public_board.view/list` نطاقهما **ALL وحده** لأنّها بنصّ 12.2.2 «لوحة
     * **مفتوحة لكلّ المتطوّعين**» — وإسقاطها يقفل اللوحة في وجه الكوردنيتور
     * الذي يعطيه 12.2.3-ب-16 `public_board.assign`.
     */
    public function test_the_cut_narrowed_the_scope_and_kept_the_grant(): void
    {
        foreach ([
            ['coordinator', 'public_board.view', 'ALL'],
            ['coordinator', 'public_board.list', 'ALL'],
            ['team_leader', 'public_board.view', 'ALL'],
            // وطُلِبت ALL فقُصَّت إلى SELF — «التودو قائمةٌ شخصيّة» (12.2.2)
            ['volunteer_gm', 'todos.create', 'SELF'],
            ['volunteer_gm', 'todos.view', 'TEAM'],
            ['director', 'calendar.view', 'SUBTREE'],
        ] as [$role, $key, $expected]) {
            $scope = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->where('roles.key', $role)
                ->where('permissions.key', $key)
                ->value('permission_role.scope');

            $this->assertNotNull($scope, "«{$key}» ما تنسحبش من «{$role}» — القصّ نطاقٌ لا سحبُ منح (12.2.3)");
            $this->assertSame($expected, $scope, "«{$key}» تُقصّ إلى ما تسمح به المصفوفة لا أوسع (12.2.2)");
        }
    }

    /**
     * ⭐ **ولا بابَ خلفيًّا:** لا سيدر يكتب في `permission_role` بيده.
     *
     * الحارسان أعلاه يقيسان **النتيجة**، وهذا يقيس **القاعدة**: يوم يضيف أحدٌ
     * `DB::table('permission_role')->insert(...)` في سيدرٍ جديد يسقط هنا يوم
     * كتابته — لا بعد أن تُكتَشف عشرات الصفوف في مراجعة.
     */
    public function test_no_seeder_writes_grants_outside_the_single_cut_point(): void
    {
        $offenders = [];

        foreach (glob(database_path('seeders/*.php')) as $path) {
            $source = (string) file_get_contents($path);
            $name = basename($path);

            if (preg_match("/permission_role'\\)\\s*->\\s*(insert|insertOrIgnore|upsert|update|updateOrInsert)/", $source)) {
                $offenders[] = $name;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'الكتابة في `permission_role` تمرّ بـ`GrantsWithinMatrixCeiling` وحدها — '
            .'وإلّا عاد الباب الخلفيّ الذي كان يكتب فوق سقف 12.2.2.',
        );
    }
}
