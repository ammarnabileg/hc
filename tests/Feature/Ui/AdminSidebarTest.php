<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * خريطة سايد بار لوحة الإدارة (الدستور 12.0).
 *
 * القاعدة الحاكمة: **يظهر العنصر لمن له أيّ صلاحيّة داخله فقط**،
 * و**ما لا يملكه المستخدم يُخفى ولا يُعطَّل** (12.2.1 · 2.15-أ-7).
 */
class AdminSidebarTest extends UiTestCase
{
    private function withRole(string $roleKey): User
    {
        $user = User::create([
            'name' => 'مسؤول تجريبيّ',
            'email' => Str::lower(Str::random(8)).'@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => 'A'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        $user->assignRole($roleKey);

        return $user->fresh();
    }

    public function test_owner_sees_every_built_page_that_had_no_entry_before(): void
    {
        $response = $this->actingAs($this->withRole('platform_owner'))->get(route('admin.dashboard'));

        $response->assertOk();

        // الصفحات المبنيّة التي كانت بلا مدخل في السايد بار (12.0)
        foreach ([
            route('admin.studio.index'),
            route('admin.media.index'),
            route('admin.paths.index'),
            route('admin.topups.index'),
            route('admin.ads.index'),
            route('admin.articles.index'),
            route('admin.finance.index'),
            route('admin.positive.index'),
            route('admin.ops.onboarding'),
            route('admin.ops.updates'),
            route('admin.ops.system'),
            // صفحات admin/volunteer/* الفرعيّة
            route('admin.volunteer.org'),
            route('admin.volunteer.org.capacity'),
            route('admin.volunteer.rep'),
            route('admin.volunteer.offboarding'),
            route('admin.volunteer.certificates'),
            route('admin.volunteer.analytics'),
        ] as $url) {
            $response->assertSee($url, false);
        }
    }

    public function test_finance_is_reserved_for_the_platform_owner_alone(): void
    {
        // 🔒 الماليّات مجموعة محميّة (12.0 · 2.13-و) — لا تظهر لغير المالك
        $this->actingAs($this->withRole('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.finance.index'), false);

        $this->actingAs($this->withRole('marketing_admin'))
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.finance.index'), false);
    }

    public function test_a_user_without_a_permission_does_not_even_see_the_entry(): void
    {
        // مسؤول التسويق لا يملك مكتبة الوسائط ولا الشهادات — فلا يراهما أصلًا
        $response = $this->actingAs($this->withRole('marketing_admin'))->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertDontSee(route('admin.volunteer.rep'), false)
            ->assertDontSee(route('admin.topups.index'), false);
    }
}
