<?php

namespace Tests\Feature\Dashboard;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\Streak;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\DashboardStatsService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * لوحة المتدرّب الرئيسيّة (14 · 24.5) — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);

        Permission::create([
            'key' => 'enrollments.view', 'resource' => 'enrollments', 'action' => 'view',
            'group' => 'الأكاديمية', 'label_ar' => 'تسجيلاتي',
            'allowed_scopes' => ['SELF', 'ALL'],
        ]);
    }

    // ------------------------------------------------------------ أدوات

    /** دور المتدرّب: يرى بياناته هو فقط — نطاق SELF (12.2.1) */
    private function traineeRole(): Role
    {
        $role = Role::firstOrCreate(
            ['key' => 'trainee'],
            ['name_ar' => 'متدرّب', 'layer' => 'platform'],
        );

        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'enrollments.view')->value('id'),
            'scope' => 'SELF',
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $role;
    }

    private function trainee(string $name = 'سلمى عبد الرحمن'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);

        $user->assignRole($this->traineeRole());

        return $user;
    }

    /** تدريب بقسمٍ ودروسه، ثمّ تسجيل المستخدم فيه مع إكمال جزءٍ من الدروس */
    private function enrolledCourse(User $user, string $name, int $lessons, int $done, ?int $deadlineDays = 10): Course
    {
        $course = Course::create([
            'slug' => str()->random(10),
            'name_ar' => $name,
            'status' => 'published',
            'xp_before_half' => 300,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title_ar' => 'القسم الأوّل', 'sort_order' => 0]);

        for ($i = 0; $i < $lessons; $i++) {
            $lesson = Lesson::create([
                'section_id' => $section->id,
                'title_ar' => 'الدرس '.($i + 1),
                'type' => 'video',
                'sort_order' => $i,
            ]);

            if ($i < $done) {
                LessonCompletion::create([
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'completed_at' => now()->subDays($lessons - $i),
                ]);
            }
        }

        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'started_at' => now()->subDays(20),
            'deadline_at' => $deadlineDays === null ? null : now()->addDays($deadlineDays),
            'xp_earned' => 120,
            'status' => 'active',
        ]);

        return $course;
    }

    // ------------------------------------------------------------ الاختبارات

    public function test_dashboard_shows_greeting_four_kpis_and_course_cards(): void
    {
        $user = $this->trainee('سلمى عبد الرحمن محمود');
        $this->enrolledCourse($user, 'تصميم واجهات المستخدم', lessons: 4, done: 2);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('أهلًا')
            ->assertSee('أكمل آخر درس')          // الفعل الرئيسيّ الوحيد (2.15-أ-2)
            ->assertSee('تصميم واجهات المستخدم')
            ->assertSee('الدرس 3')                 // القسم/الدرس الحاليّ = أوّل درس غير مكتمل
            ->assertSee('أقرب المواعيد');

        // ⭐ أربعة كروت KPI بحدّ أقصى في الصفّ (2.15-أ-3)
        $this->assertCount(4, app(DashboardService::class)->kpis($user));
    }

    public function test_progress_ring_and_ghost_timer_states_follow_the_state_dictionary(): void
    {
        $user = $this->trainee();
        $this->enrolledCourse($user, 'موعد فات', lessons: 4, done: 1, deadlineDays: -2);
        $this->enrolledCourse($user, 'موعد قرب', lessons: 4, done: 1, deadlineDays: 2);
        $this->enrolledCourse($user, 'موعد متّسع', lessons: 4, done: 1, deadlineDays: 20);

        $rows = app(DashboardService::class)->activeCourses($user)->keyBy(fn ($row) => $row['course']->name_ar);

        $this->assertSame('danger', $rows['موعد فات']['timer']->state);
        $this->assertSame('warn', $rows['موعد قرب']['timer']->state);
        $this->assertSame('ok', $rows['موعد متّسع']['timer']->state);
        $this->assertSame(25, $rows['موعد متّسع']['percent']);

        // اللون لا يحمل المعنى وحده — لكلّ حالة رمزها (2.16-ب)
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('فات الموعد من', false);
    }

    public function test_empty_state_is_one_line_and_one_button(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('لسّه مابدأتش تدريب')
            ->assertSee('تصفّح المتجر')
            ->assertDontSee('أكمل آخر درس');
    }

    public function test_stats_tab_draws_all_five_charts_as_inline_svg(): void
    {
        $user = $this->trainee();
        $this->enrolledCourse($user, 'مهارات العرض', lessons: 5, done: 3);

        $xp = Currency::where('code', 'xp')->firstOrFail();
        Transaction::create([
            'user_id' => $user->id, 'currency_id' => $xp->id, 'amount' => 90,
            'layer' => 'training', 'source' => 'academy',
        ]);

        Streak::create(['user_id' => $user->id, 'current_days' => 4, 'best_days' => 9, 'club_5am_count' => 3]);

        $response = $this->actingAs($user)->get('/dashboard?tab=stats');

        $response->assertOk()
            ->assertSee('XP عبر الزمن')
            ->assertSee('إكمال المسار')
            ->assertSee('خريطة الحضور')
            ->assertSee('مسارات الإنجاز الخمسة')
            ->assertSee('التذاكر: مكتسب ومصروف')
            ->assertSee('<svg', false);

        // بلا أيّ مكتبة رسوم خارجيّة — الأصول محجوبة والرسوم مرسومة بأيدينا (2.16-ج)
        $response->assertDontSee('chart.js')->assertDontSee('cdn');
    }

    public function test_details_tab_holds_the_secondary_numbers(): void
    {
        $user = $this->trainee();
        $this->enrolledCourse($user, 'إدارة الوقت', lessons: 2, done: 2);

        $this->actingAs($user)->get('/dashboard?tab=details')
            ->assertOk()
            ->assertSee('تدريبات مكتملة')
            ->assertSee('ترتيبك في الليدر بورد');

        $details = app(DashboardService::class)->details($user);

        $this->assertSame(1, $details['completed']);
        $this->assertSame(0, $details['active']);
        $this->assertSame(1, $details['rank']);
    }

    public function test_kpis_read_from_wallet_and_certificates(): void
    {
        $user = $this->trainee();
        $this->enrolledCourse($user, 'أساسيّات التطوّع', lessons: 2, done: 1);

        WalletBalance::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'xp')->value('id'),
            'balance' => 1600, 'lifetime_earned' => 1600,
        ]);

        WalletBalance::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'tickets')->value('id'),
            'balance' => 12, 'lifetime_earned' => 30, 'lifetime_spent' => 18,
        ]);

        Certificate::create([
            'code' => 'CRS-TEST-1',
            'hash' => hash('sha256', 'CRS-TEST-1'),
            'user_id' => $user->id,
            'certificate_type_id' => CertificateType::where('key', 'course')->value('id'),
            'issued_at' => now(),
            'status' => 'valid',
        ]);

        $kpis = collect(app(DashboardService::class)->kpis($user))->keyBy('label');

        $this->assertSame(1600, $kpis['مستوى الحساب و XP']['value']);
        $this->assertSame(12, $kpis['التذاكر']['value']);
        $this->assertSame(1, $kpis['الشهادات']['value']);
        $this->assertStringContainsString('المستوى 3', $kpis['مستوى الحساب و XP']['hint']);
    }

    public function test_achievement_levels_follow_the_approved_thresholds(): void
    {
        $stats = app(DashboardStatsService::class);

        // جدول 10.1: مستوى الحساب — base 500 و step 250 ⟵ 500 · 1250 · 2250 · 3500
        $this->assertSame(1, $stats->pathLevel(499, 500, 250)['level']);
        $this->assertSame(2, $stats->pathLevel(500, 500, 250)['level']);
        $this->assertSame(3, $stats->pathLevel(1250, 500, 250)['level']);
        $this->assertSame(4, $stats->pathLevel(2250, 500, 250)['level']);
        $this->assertSame(5, $stats->pathLevel(3500, 500, 250)['level']);

        // ونادي الخامسة — base 3 و step 2 ⟵ 3 · 8 · 15
        $this->assertSame(3, $stats->pathLevel(8, 3, 2)['level']);
        $this->assertSame(4, $stats->pathLevel(15, 3, 2)['level']);
    }

    public function test_demo_seeder_builds_a_full_arabic_dashboard(): void
    {
        $this->traineeRole();
        $this->seed(DashboardDemoSeeder::class);

        $user = User::where('code', 'UDASH001')->firstOrFail();

        $this->assertSame(4, Enrollment::where('user_id', $user->id)->count());

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('سلمى عبد الرحمن')
            ->assertSee('أساسيّات العمل التطوّعيّ')     // جارٍ ⟵ يظهر ككارت
            ->assertDontSee('تصميم واجهات المستخدم')   // مكتمل ⟵ ليس من «الجارية»
            ->assertSee('أقرب المواعيد');

        // المكتمل والشهادة يظهران في تاب «تفاصيل» ولوحة الأرقام
        $this->actingAs($user)->get('/dashboard?tab=details')->assertOk()->assertSee('تدريبات مكتملة');
        $this->assertSame(1, app(DashboardService::class)->certificatesCount($user));

        $this->actingAs($user)->get('/dashboard?tab=stats&days=30')
            ->assertOk()
            ->assertSee('نادي الخامسة')
            ->assertSee('<svg', false);

        $this->actingAs($user)->get('/dashboard?tab=stats&days=7')->assertOk();
    }

    public function test_guest_is_redirected_and_user_without_permission_is_denied(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $stranger = User::create([
            'name' => 'زائر', 'email' => 'stranger@test.local', 'password' => 'secret-password',
            'code' => 'USTRNG01', 'status' => 'active',
        ]);

        // بلا صلاحيّة = ممنوع (12.2.1) — والعنصر يُخفى من الواجهة أصلًا (2.15-أ-7)
        $this->actingAs($stranger)->get('/dashboard')->assertForbidden();
    }

    public function test_last_opened_tab_is_remembered_per_user(): void
    {
        $user = $this->trainee();
        $this->enrolledCourse($user, 'تدريب', lessons: 2, done: 1);

        $this->actingAs($user)->get('/dashboard?tab=details')->assertOk();

        $this->assertSame('details', $user->fresh()->last_tabs['dashboard']);

        // الصفحة تفتح على آخر تاب فُتِح فيها (2.15-د)
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('ترتيبك في الليدر بورد');
    }
}
