<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐⭐ **المفتاح لا يتجاوز سنده** — `delegations.create` (الدستور 23 — القسم 6).
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * > «**⭐ وضع «غائب» والتفويض المؤقّت (Delegation) — قاعدة نهائيّة:** …
 * >  **مَن يضيفه:** **مشرف عام التطوّع** أو **مشرف المسار** أو **دايركتور
 * >  الكيان** — لا الشخص نفسه (منعًا للتهرّب).»
 *
 * ================== العطب ==================
 * كان السيدر يمنح `delegations.create` لكلّ أدوار التطوّع، فيأخذها
 * **السوبرفايزر** بـSUBTREE و**التيم ليدر** بـTEAM. وقائمةُ البوزشنات في مسار
 * الإضافة كانت تردّهما، لكنّ **صلاحيّةً أوسع من سندها ثغرةٌ تنتظر حارسًا يسقط**:
 * مسارٌ ثانٍ للإضافة، أو استثناءٌ فرديّ، أو نسيانُ القائمة — والقدرة تنفتح بلا نصّ.
 *
 * ⚠️ والحصر على **`create` وحدها**: 23-6 يقيّد «مَن **يضيفه**» لا مَن يراه —
 * فـ`delegations.list` («استعراض حالات «غائب» والبدلاء المفوَّضين» — 12.2.2)
 * تبقى لكلّ الأدوار، وإلّا اختفى الوسم الذي يوجب 23-6 إظهاره «في الهيكل
 * والبروفايل … فيعرف الجميع لمن يرجعون».
 */
class DelegationCreateGrantTest extends TestCase
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

    private function scopeOf(string $roleKey, string $permissionKey): ?string
    {
        return DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->where('roles.key', $roleKey)
            ->where('permissions.key', $permissionKey)
            ->value('permission_role.scope');
    }

    /** الثلاثة المسمَّون في النصّ يملكونها — ولم يُسحَب منهم شيء */
    public function test_the_three_named_roles_keep_the_key(): void
    {
        foreach (['volunteer_gm' => 'TRACK', 'track_supervisor' => 'TRACK', 'director' => 'ENTITY'] as $role => $expected) {
            $this->assertSame(
                $expected,
                $this->scopeOf($role, 'delegations.create'),
                "«{$role}» من الثلاثة المسمَّين في 23-6 — يملك المفتاح بنطاقٍ داخل سقف 12.2.2",
            );
        }
    }

    /** ومَن ليس منهم لا يملكها — ولو سمحت المصفوفة بنطاقه */
    public function test_roles_outside_the_text_do_not_hold_it(): void
    {
        foreach (['supervisor', 'team_leader', 'coordinator'] as $role) {
            $this->assertNull(
                $this->scopeOf($role, 'delegations.create'),
                "«{$role}» ليس من الثلاثة في 23-6 — والمفتاح لا يتجاوز سنده ولو سمحت 12.2.2 بنطاقه",
            );
        }

        /*
         | ⭐ والمصفوفة **تسمح** بنطاقهما فعلًا («TEAM · SUBTREE · ENTITY · TRACK»)،
         | فالحصر جاء من **23-6** لا من عجز المصفوفة. ولولا هذا السطر لالتبس
         | الحكمان: مفتاحٌ سقط لأنّ نطاقه غير مسموح ≠ مفتاحٌ حُجِب لأنّ النصّ حصره.
         */
        $allowed = (array) Permission::where('key', 'delegations.create')->value('allowed_scopes');

        $this->assertContains('TEAM', $allowed);
        $this->assertContains('SUBTREE', $allowed);
    }

    /** ⭐ ولم يُسحَب ما يفتحه النصّ: **رؤية** الغياب تبقى لهم (12.2.2 · 23-6) */
    public function test_seeing_absences_was_not_withdrawn_from_anyone(): void
    {
        foreach (['supervisor', 'team_leader'] as $role) {
            $this->assertNotNull(
                $this->scopeOf($role, 'delegations.list'),
                "«{$role}» يبقى يرى «حالات غائب والبدلاء المفوَّضين» — القيد على الإضافة لا على الرؤية",
            );
            $this->assertNotNull($this->scopeOf($role, 'delegations.view'));
        }
    }
}
