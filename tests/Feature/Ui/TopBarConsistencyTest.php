<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Support\Access\AccessEngine;
use Tests\Feature\Volunteer\Core\VolunteerCoreTestCase;

/**
 * الشريط العلويّ واحد في التخطيطات الثلاثة (المتدرّب والمتطوّع والإدارة):
 * [البحث الموحّد · الجرس · التثبيت · المظهر · وضع متقدّم] بنفس الترتيب، والـBreadcrumb
 * يبدأ بالمنصّة ويحمل اسم الصفحة. كان المتدرّب بلا أيقونة بحثٍ في الشريط فيبقى على
 * الموبايل (حيث السايد بار مخفيّ) بلا بحثٍ أصلًا.
 */
class TopBarConsistencyTest extends VolunteerCoreTestCase
{
    private const ORDER = ['data-palette-open', 'data-bell', 'data-pin-toggle', 'data-theme-toggle', 'data-advanced-toggle'];

    protected function setUp(): void
    {
        parent::setUp();
        // الأدوار بصلاحيّاتها كما في اختبار لوحة القيادة: assignRole يحتاج الصلاحيّات المبذورة
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function assertUnifiedTopBar(string $html, string $screen): void
    {
        $this->assertStringContainsString('id="topbar"', $html, $screen);
        $this->assertMatchesRegularExpression('/<header id="topbar" class="[^"]*\bsticky\b[^"]*\btop-0\b/', $html, $screen.': الشريط مثبَّت');

        $last = -1;
        foreach (self::ORDER as $marker) {
            $pos = strpos($html, $marker);
            $this->assertNotFalse($pos, "{$screen}: {$marker} غائب من الشريط");
            $this->assertGreaterThan($last, $pos, "{$screen}: {$marker} في غير ترتيبه");
            $last = $pos;
        }

        $this->assertMatchesRegularExpression('/<nav class="breadcrumb[^"]*"[^>]*>\s*<a [^>]*>'.preg_quote(config('app.name'), '/').'<\/a>/u', $html, $screen.': جذر المسار المنصّة');
        $this->assertStringContainsString('aria-current="page"', $html, $screen.': اسم الصفحة في المسار');
    }

    public function test_the_trainee_layout_now_carries_the_unified_search_icon_like_the_other_two(): void
    {
        $trainee = $this->makeUser('متدرّب');
        $trainee->assignRole('trainee');
        // اللوحة وتدريباتي خلف enrollments.view كما في اختبارات التعلّم
        $this->grant($trainee, ['enrollments.view'], 'SELF');
        app(AccessEngine::class)->forget();

        $this->assertUnifiedTopBar($this->actingAs($trainee)->get(route('dashboard'))->assertOk()->getContent(), 'المتدرّب');
        $this->assertUnifiedTopBar($this->actingAs($trainee)->get(route('learning.courses'))->assertOk()->getContent(), 'تدريباتي');
    }

    public function test_the_volunteer_and_admin_layouts_share_the_same_bar(): void
    {
        $volunteer = $this->makeUser('متطوّع');
        $this->makeMembership($volunteer, $this->makeEntity(), 'team_leader');
        $this->grant($volunteer, ['personal_reports.view', 'tasks.list']);
        $this->assertUnifiedTopBar($this->actingAs($volunteer)->get(route('volunteer.overview'))->assertOk()->getContent(), 'المتطوّع');

        $owner = $this->makeUser('مالك');
        $owner->assignRole('platform_owner');
        app(AccessEngine::class)->forget();
        $this->assertUnifiedTopBar($this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent(), 'الإدارة');
    }

    public function test_the_membership_selector_is_width_bounded_so_it_never_crowds_the_breadcrumb(): void
    {
        $html = file_get_contents(resource_path('views/partials/header.blade.php'));

        $this->assertMatchesRegularExpression('/<select name="membership"[^>]*class="[^"]*max-w-\[14rem\]/', $html);
    }
}
