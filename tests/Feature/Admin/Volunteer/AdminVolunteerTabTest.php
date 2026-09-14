<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Entity;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Goals\VxpDistributionService;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الفجوة المُغلَقة هنا: تاب «التطوّع» في صفحة المستخدم بالأدمن (12.1 · 1103 · 2886)
 * كان يظهر — التاب والستاك موجودان فعلًا في `UserDirectory::tabsFor()` و
 * `show.blade.php` — لكن الستاك `admin_user_volunteer_tab` كان فارغًا دائمًا:
 * لا شيء يدفع إليه، فيفتح التاب على قسمٍ فارغ بلا أيٍّ من البنود السبعة
 * المنصوصة (البوزشنز · التسكينات · المهامّ · VXP · تاريخ الالتزام · شهادات
 * التطوّع · الاجتماعات).
 *
 * هذا الاختبار يبني بيانات **حقيقيّة** للسبعة كلّها لمستخدمٍ واحد، ويتأكّد أنّها
 * تظهر فعلًا على `admin.users.show` — لا أنّ التاب موجودٌ فقط (ده كان مضمونًا
 * من الأصل).
 */
class AdminVolunteerTabTest extends TestCase
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

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مالك الاختبار', 'email' => 'admin@volunteer-tab.test',
            'password' => 'secret-password', 'code' => 'ADM-VOL-TAB', 'status' => 'active',
        ]);

        $user->assignRole('super_admin');
        app(AccessEngine::class)->forget();

        return $user;
    }

    /** يبني سجلّ مشرفٍ حقيقيّ كاملًا: بوزشن · تسكين · مهامّ · VXP · Rep · شهادة · اجتماع */
    private function buildVolunteerRecord(): User
    {
        $subject = User::create([
            'name' => 'متطوّع الاختبار', 'email' => 'volunteer@volunteer-tab.test',
            'password' => 'secret-password', 'code' => 'VOL-TAB-1', 'status' => 'active',
        ]);

        $upline = User::create([
            'name' => 'أبلاين الاختبار', 'email' => 'upline@volunteer-tab.test',
            'password' => 'secret-password', 'code' => 'VOL-TAB-UP', 'status' => 'active',
        ]);

        $track = Track::where('key', 'department')->firstOrFail();
        $entity = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم اختبار التطوّع', 'status' => 'active']);

        $directorPosition = Position::where('key', 'director')->firstOrFail();
        $uplineMembership = Membership::create([
            'user_id' => $upline->id, 'entity_id' => $entity->id, 'position_id' => $directorPosition->id,
            'is_primary' => true, 'started_at' => now()->subYear(), 'status' => 'active',
        ]);

        // ---------- 1) البوزشنز · 2) التسكينات: بوزشنان بتاريخين — القديم منتهٍ والحاليّ نشط
        $coordinatorPosition = Position::where('key', 'coordinator')->firstOrFail();
        $teamLeaderPosition = Position::where('key', 'team_leader')->firstOrFail();

        Membership::create([
            'user_id' => $subject->id, 'entity_id' => $entity->id, 'position_id' => $coordinatorPosition->id,
            'upline_id' => $uplineMembership->id, 'is_primary' => false,
            'started_at' => now()->subMonths(8), 'ended_at' => now()->subMonths(3), 'status' => 'ended',
        ]);

        Membership::create([
            'user_id' => $subject->id, 'entity_id' => $entity->id, 'position_id' => $teamLeaderPosition->id,
            'upline_id' => $uplineMembership->id, 'is_primary' => true, 'is_acting' => true,
            'started_at' => now()->subMonths(3), 'status' => 'active',
        ]);

        // ---------- 3) المهامّ: معتمدة وجارية وعدم تسليم — عنوان مميَّز لكلّ حالة
        Task::create([
            'title' => 'تصميم بوستر الحملة السنويّة', 'entity_id' => $entity->id, 'owner_id' => $subject->id,
            'status' => 'approved', 'vxp_value' => 100, 'deadline_at' => now()->subDays(5), 'source' => 'assigned',
        ]);
        Task::create([
            'title' => 'كتابة تقرير الربع الأوّل', 'entity_id' => $entity->id, 'owner_id' => $subject->id,
            'status' => 'in_progress', 'vxp_value' => 60, 'deadline_at' => now()->addDays(3), 'source' => 'assigned',
        ]);
        Task::create([
            'title' => 'مراجعة محتوى الصفحة الرسميّة', 'entity_id' => $entity->id, 'owner_id' => $subject->id,
            'status' => 'no_delivery', 'vxp_value' => 40, 'deadline_at' => now()->subDays(10), 'source' => 'assigned',
        ]);

        // ---------- 4) VXP: حركة حقيقيّة عبر دفتر الأستاذ الموحَّد — لا كتابة مباشرة على الرصيد
        Integrations::credit(
            user: $subject, currencyCode: VxpDistributionService::CURRENCY, amount: 160,
            source: 'task', reason: 'مكافأة اعتماد بوستر الحملة',
        );

        // ---------- 5) تاريخ الالتزام (Rep): حركتان بسببين مختلفين ثمّ تثبيت الدرجة
        Integrations::credit($subject, RepService::CURRENCY, 0.25, 'task', null, 'تسليم قبل الموعد');
        Integrations::debit($subject, RepService::CURRENCY, -0.5, 'behavior', null, 'تنبيه موثّق على تأخير الردّ');
        app(RepService::class)->syncScore($subject);

        // ---------- 6) شهادات التطوّع: شهادة بوزشن حقيقيّة بكود مميَّز
        $certificateType = CertificateType::updateOrCreate(
            ['key' => 'volunteer_position'],
            ['name_ar' => 'شهادة بوزشن تطوّعيّ', 'numbering_prefix' => 'VPS'],
        );

        Certificate::create([
            'code' => 'VPS-TEST-0001', 'hash' => hash('sha256', 'VPS-TEST-0001'),
            'user_id' => $subject->id, 'certificate_type_id' => $certificateType->id,
            'issued_at' => now()->subMonth(), 'status' => 'valid',
        ]);

        // ---------- 7) الاجتماعات: حضورٌ مسجَّل لاجتماع بعنوان مميَّز
        $meeting = Meeting::create([
            'title' => 'اجتماع مراجعة الأداء الشهريّ', 'entity_id' => $entity->id, 'owner_id' => $upline->id,
            'scheduled_at' => now()->subWeek(), 'status' => 'ended', 'ended_at' => now()->subWeek()->addHour(),
        ]);

        MeetingAttendance::create([
            'meeting_id' => $meeting->id, 'user_id' => $subject->id,
            'status' => 'registered', 'registered_at' => now()->subWeek()->addMinutes(30),
        ]);

        return $subject;
    }

    /** الاختبار الجوهريّ: السبعة بنود المنصوصة تظهر فعلًا ببياناتها الحقيقيّة — لا مجرّد تاب فارغ */
    public function test_volunteer_tab_shows_the_real_supervisor_record(): void
    {
        $subject = $this->buildVolunteerRecord();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.users.show', ['user' => $subject, 'tab' => 'volunteer']))
            ->assertOk();

        $response
            // 1) البوزشنز — البوزشن الحاليّ وواحد منتهٍ
            ->assertSee('تيم ليدر')
            ->assertSee('كوردنيتور')
            // 2) التسكينات — الكيان واسم الأبلاين
            ->assertSee('قسم اختبار التطوّع')
            ->assertSee($subject->fresh()->memberships()->first()->upline?->user?->shortName() ?? 'أبلاين الاختبار')
            // 3) المهامّ — عنوان مهمّة معتمدة وأخرى بعدم تسليم
            ->assertSee('تصميم بوستر الحملة السنويّة')
            ->assertSee('مراجعة محتوى الصفحة الرسميّة')
            // 4) VXP — الرصيد المتراكم من حركة الدفتر الحقيقيّة
            ->assertSee(number_format(160, 2))
            // 5) تاريخ الالتزام — سبب حركة Rep حقيقيّة
            ->assertSee('تنبيه موثّق على تأخير الردّ')
            // 6) شهادات التطوّع — كود الشهادة الصادرة فعلًا
            ->assertSee('VPS-TEST-0001')
            // 7) الاجتماعات — عنوان الاجتماع الذي سُجِّل له حضور
            ->assertSee('اجتماع مراجعة الأداء الشهريّ');
    }

    /** مَن لا يملك صلاحيّة التطوّع لا يرى التاب أصلًا — والستاك لا يظهر ولو زار الرابط مباشرة */
    public function test_tab_content_is_hidden_without_the_permission(): void
    {
        $subject = $this->buildVolunteerRecord();

        $plainAdmin = User::create([
            'name' => 'مسؤول بلا صلاحيّة تطوّع', 'email' => 'plain-admin@volunteer-tab.test',
            'password' => 'secret-password', 'code' => 'ADM-PLAIN-1', 'status' => 'active',
        ]);
        $plainAdmin->assignRole('support_admin');
        app(AccessEngine::class)->forget();

        $this->actingAs($plainAdmin)
            ->get(route('admin.users.show', ['user' => $subject, 'tab' => 'volunteer']))
            ->assertOk()
            ->assertDontSee('تصميم بوستر الحملة السنويّة')
            ->assertDontSee('VPS-TEST-0001');
    }

    /** مستخدمٌ لم يتطوّع قطّ: رسالة فارغة واحدة — بلا أثرٍ لأيّ قسم من السبعة */
    public function test_empty_state_for_a_user_who_never_volunteered(): void
    {
        $stranger = User::create([
            'name' => 'حساب بلا تطوّع', 'email' => 'stranger@volunteer-tab.test',
            'password' => 'secret-password', 'code' => 'PLAIN-VOL-1', 'status' => 'active',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', ['user' => $stranger, 'tab' => 'volunteer']))
            ->assertOk()
            ->assertSee('محدّش تطوّع من الحساب ده لسّه');
    }
}
