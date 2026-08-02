<?php

namespace Tests\Feature\Scope;

use App\Models\Permission;
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
 * ⭐ **صفحات مبنيّة بلا مدخل في السايد بار** (الدستور 12.0).
 *
 * كانت هذه الصفحات موجودة ومحروسة و**بلا أيّ رابط وارد** في المشروع كلّه —
 * فوجودها كعدمه. هنا نثبت أنّ لها مدخلًا في موضعها من الخريطة، وأنّها
 * **تُخفى لمن لا يملك صلاحيّتها ولا تُعطَّل** (2.15-أ-7).
 */
class SidebarEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);
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
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole(config('access.owner_role'));
        app(AccessEngine::class)->forget();

        return $user;
    }

    /** مستخدم بصلاحيّة واحدة فقط — لنرى ما يظهر له وما يُخفى عنه */
    private function userWith(string ...$keys): User
    {
        $user = $this->makeUser('صاحب صلاحيّة واحدة');

        foreach ($keys as $key) {
            $permission = Permission::query()->where('key', $key)->first();

            if (! $permission) {
                [$resource, $action] = explode('.', $key);
                $permission = Permission::create([
                    'key' => $key,
                    'resource' => $resource,
                    'action' => $action,
                    'group' => 'اختبار',
                    'label_ar' => $key,
                    'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
                ]);
            }

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget();

        return $user;
    }

    private function sidebar(User $user): string
    {
        return $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();
    }

    /** البنود التي كانت بلا مدخل صار لها مدخل عند مالك المنصّة (12.0). */
    public function test_orphan_pages_now_have_an_entry_point(): void
    {
        $html = $this->sidebar($this->owner());

        foreach ([
            // 📣 التوجيه والدعم (12.6) — الثلاثة كانت بلا أيّ رابط وارد
            'admin/guidance/notifications',
            'admin/guidance/help',
            'admin/guidance/complaints',
            // 🔒 الماليّات (12.0) — أسعار الصرف
            'admin/wallet/rates',
            // ⚙️ آخر بند في الخريطة
            'admin/settings/audit',
            // 🎮 التلعيب: الألعاب والاحتفالات تابان داخل اللوحة
            'tab=games',
            'tab=celebrations',
            // 📚 إعدادات التعلّم
            'tab=learning',
            // 🤝 التوظيف والمرشّحون
            'volunteer/recruitment',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "مدخل ناقص في السايد بار: {$needle}");
        }
    }

    /** وكلّ مدخل جديد **يُخفى** لمن لا يملكه — لا يُعرَض معطَّلًا (2.15-أ-7). */
    public function test_every_new_entry_is_hidden_from_whoever_lacks_it(): void
    {
        // صاحب صلاحيّة واحدة لا علاقة لها بأيٍّ من البنود الجديدة
        $html = $this->sidebar($this->userWith('users.list'));

        foreach ([
            'admin/guidance/notifications',
            'admin/guidance/help',
            'admin/guidance/complaints',
            'admin/wallet/rates',
            'admin/settings/audit',
            'tab=games',
            'tab=celebrations',
            'tab=learning',
            'volunteer/recruitment',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "بند ظهر لمن لا يملكه: {$needle}");
        }
    }

    /** ومَن يملك الصلاحيّة يراه — فالإخفاء بالصلاحيّة لا بالحذف. */
    public function test_the_entry_appears_for_whoever_owns_its_permission(): void
    {
        $html = $this->sidebar($this->userWith('complaints.list'));

        $this->assertStringContainsString('admin/guidance/complaints', $html);
        $this->assertStringContainsString('الشكاوى والمقترحات', $html);

        // وما لا يملكه يبقى مخفيًّا في الشاشة نفسها
        $this->assertStringNotContainsString('admin/guidance/help', $html);
    }

    /**
     * 🔒 «الماليّات» مجموعة محميّة: **شرط مالك المنصّة فوق فحص الصلاحيّة**،
     * فلا تظهر أسعار الصرف لمن مُنِح `exchange_rates.view` وليس مالكًا (12.2.1-ز-3).
     */
    public function test_exchange_rates_stays_owner_only_even_with_the_permission(): void
    {
        $html = $this->sidebar($this->userWith('exchange_rates.view'));

        $this->assertStringNotContainsString('admin/wallet/rates', $html);
        $this->assertStringNotContainsString('🔒 أسعار الصرف', $html);
    }
}
