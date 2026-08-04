<?php

namespace Tests\Feature\Ui;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\SidebarMap;

/**
 * حارس ب-2 (طبقة التطوّع): **كلّ شاشة تطوّعٍ تمتدّ من `layouts.volunteer`**.
 *
 * العطل الذي يمنعه هذا الحارس: **اثنان وأربعون** قالبًا تحت
 * `resources/views/volunteer/**` كانت تمتدّ من `layouts.app` وأحد عشر وحدها من
 * `layouts.volunteer` — فتُعرَض شاشة التطوّع داخل **سايد بار المتدرّب**
 * (الرئيسيّة · تعلّمي · مكتبتي · المتجر)، ولا يجد المتطوّع من «المرشّحين» بابًا
 * إلى «الاجتماعات» ولا إلى «مهامّي» إلّا بالرجوع إلى لوحة التطوّع من أوّلها.
 * والدستور **13.4-ح** ينصّ على خريطة سايد بارٍ من **ثلاثة عشر عنصرًا** لهذه
 * الشاشات بعينها، و**24.4** يصفها شاشةً شاشةً تحت «لوحة التطوّع».
 *
 * والحارس فحصان لا فحصٌ واحد:
 *   1) **ثابت** — لا قالب صفحةٍ تحت `resources/views/volunteer/**` يمتدّ من غير
 *      `layouts.volunteer`. أعِد واحدًا إلى `layouts.app` يسقط الفحص فورًا.
 *   2) **حيّ** — بجلسة متطوّعٍ حقيقيّة: الشاشة تعرض سايد بار التطوّع، لا سايد
 *      بار المتدرّب.
 *
 * ⭐ **ومعيار «صفحة لا جزئيّة» واحدٌ هنا وهناك:** القالب الذي فيه `@extends`
 *    صفحةٌ تُفتَح بمسار GET، وما لا `@extends` له جزئيّةٌ تُدرَج داخل صفحة —
 *    ومنها تابات بروفايل المتطوّع التي تُحقَن في `profile.show` (شاشة المتدرّب)
 *    فتبقى بلا `@extends` عن قصد.
 */
class VolunteerLayoutGuardTest extends UiTestCase
{
    /** الجزئيّات لا `@extends` لها — والمعيار هنا: القالب الذي يمتدّ قالبًا هو صفحة */
    private function volunteerPageTemplates(): array
    {
        $pages = [];

        foreach (File::allFiles(resource_path('views/volunteer')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $body = (string) file_get_contents($file->getPathname());

            if (! preg_match("/@extends\(['\"]([a-z0-9._-]+)['\"]\)/i", $body, $m)) {
                // جزئيّة أو تاب — تُدرَج داخل صفحة ولا تُفتَح وحدها
                continue;
            }

            $pages[str_replace(resource_path('views').'/', '', $file->getPathname())] = $m[1];
        }

        return $pages;
    }

    public function test_every_volunteer_page_template_extends_the_volunteer_layout(): void
    {
        $pages = $this->volunteerPageTemplates();

        /*
         | شبكة أمان للحارس نفسه: لو أُفرِغ المجلّد أو أُعيدت تسمية اللاحقة
         | لمرّ الفحص على **لا شيء** وقال «تمّ» وهو لم يقِس شيئًا.
         | المرصود عند بناء الحارس: 53 قالب صفحة.
         */
        $this->assertGreaterThan(
            45,
            count($pages),
            'مفيش قوالب صفحات تحت volunteer/ — الحارس بيقيس فراغ.',
        );

        $strays = array_filter($pages, fn (string $layout) => $layout !== 'layouts.volunteer');

        $this->assertSame([], $strays, implode("\n", array_map(
            fn ($file, $layout) => "{$file} يمتدّ من {$layout} بدل layouts.volunteer",
            array_keys($strays),
            $strays,
        )));
    }

    /** والعكس بعينه: ولا قالبَ تطوّعٍ واحدٍ يمتدّ من قالب المتدرّب */
    public function test_no_volunteer_page_extends_the_trainee_layout(): void
    {
        $this->assertNotContains('layouts.app', $this->volunteerPageTemplates());
    }

    /**
     * ⭐ والمجلّد **مأهولٌ فعلًا**: شبكةٌ ثانية تحت الأولى — فلو حُذفت الملفّات
     *    كلّها ما بقيت جزئيّةٌ ولا صفحة، والفحصان فوق يمرّان على العدم.
     */
    public function test_the_volunteer_views_directory_is_not_empty(): void
    {
        $this->assertDirectoryExists(resource_path('views/volunteer'));

        $blades = array_filter(
            File::allFiles(resource_path('views/volunteer')),
            fn ($file) => str_ends_with($file->getFilename(), '.blade.php'),
        );

        $this->assertGreaterThan(80, count($blades), 'مجلّد قوالب التطوّع شبه فاضي — راجع الحارس نفسه.');
    }

    /** ⭐ كلّ صفحةٍ في المجلّد لها **مسار GET** يفتحها — فلا قالبَ ميّتًا يحرسه الحارس */
    public function test_every_volunteer_page_template_is_rendered_by_some_controller(): void
    {
        $sources = collect(File::allFiles(app_path()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
            ->map(fn ($file) => (string) file_get_contents($file->getPathname()))
            ->implode("\n");

        $orphans = [];

        foreach (array_keys($this->volunteerPageTemplates()) as $path) {
            $name = str_replace('/', '.', Str::beforeLast($path, '.blade.php'));

            if (! str_contains($sources, "'{$name}'") && ! str_contains($sources, "\"{$name}\"")) {
                $orphans[] = $name;
            }
        }

        $this->assertSame([], $orphans, 'قوالب صفحاتٍ لا يعرضها أيّ كنترولر: '.implode(' · ', $orphans));
    }

    /**
     * الشاشات الثماني التي رصدها القياس خارج قالبها — وكلٌّ منها **بندٌ منصوصٌ
     * في خريطة 13.4-ح**: المشاريع والأهداف · المعاملات · المكتبة الداخليّة ·
     * المهام (مساهماتي وبانتظار مراجعتي) · الاجتماعات · الأكاديمية · التوظيف.
     */
    public function test_volunteer_screens_render_the_volunteer_sidebar_not_the_trainee_one(): void
    {
        $volunteer = $this->placedVolunteer();

        foreach ([
            'volunteer.goals',         // 🎯 المشاريع والأهداف
            'volunteer.transactions',  // 💳 المعاملات
            'volunteer.library',       // 📚 المكتبة الداخليّة
            'volunteer.contributions', // ✅ المهام — مساهماتي
            'volunteer.reviews',       // ✅ المهام — بانتظار مراجعتي
            'volunteer.meetings',      // 🗓️ الاجتماعات
            'volunteer.academy',       // 🎓 الأكاديمية
            'volunteer.recruitment',   // 🧑‍💼 التوظيف — المرشّحون
            // …وأخواتها التي رصدها الجرد بعد القياس الأوّل
            'volunteer.org',                     // 🏛️ قسمي
            'volunteer.performance.vxp',         // 📈 الأداء
            'volunteer.kudos',                   // 💛 التقدير
            'volunteer.profile.consent.insights', // 13.4-م/ك — طلبات إظهار التواصل
        ] as $name) {
            $response = $this->actingAs($volunteer)->get(route($name));

            $response->assertOk();

            $destinations = SidebarMap::allDestinations($response->getContent());

            /*
             | ⭐ **بالوجهة لا باللافتة** (سجلّ القرارات 2026-08-04 · 2.13-ب):
             | كان الفحص يقرأ `>إشعارات التطوّع<` و`>رجوع للرئيسيّة<` — وهي
             | **لافتات**، وتغييرُها من اللوحة حقٌّ للمالك بنصّ 2.13-ب. فصار
             | المقياس بابَ البند: أين يذهب، لا ماذا كُتِب عليه.
             */
            $this->assertContains(route('volunteer.notifications'), $destinations, 'سايد بار التطوّع غائب عن الشاشة.');
            $this->assertContains(route('dashboard'), $destinations, 'باب الرجوع لطبقة المتدرّب غائب.');

            // …وسايد بار المتدرّب غائب: البقاء داخله هو عين العطل
            foreach ([route('learning.courses'), route('library.index'), route('achievements.badges')] as $trainee) {
                $this->assertNotContains($trainee, $destinations, "وجهة من سايد بار المتدرّب ظهرت في شاشة تطوّع: {$trainee}");
            }
        }
    }

    /**
     * ⭐ **خريطة 13.4-ح كاملةً — بالوجهة لا باللافتة** (سجلّ القرارات 2026-08-04).
     *
     * ثلاثة عشر بندًا بترتيبها المعتمَد: أحدَ عشرَ مجموعةً بدروب-داون واثنان
     * رابطان مفردان. والمحفوظ **بنيتُها** — عدد البنود وترتيبها ووجهتُها ومَن
     * يراها — لا الكلمة المكتوبة عليها: تغييرُ اللافتة حقٌّ للمالك (2.13-ب)،
     * وحذفُ بندٍ أو إزاحتُه أو تحويلُ وجهته **يسقط هذا الحارس**.
     */
    public function test_the_map_of_13_4_h_keeps_its_entries_order_and_destinations(): void
    {
        $html = $this->actingAs($this->fullyPermittedVolunteer())
            ->get(route('volunteer.overview'))
            ->assertOk()
            ->getContent();

        $expected = [];

        foreach ($this->constitutionalEntries() as [, $items, $flat]) {
            $expected[] = $items === null
                ? ['type' => 'link', 'href' => $flat]
                : ['type' => 'group', 'items' => $items];
        }

        // وباب الرجوع لطبقة المتدرّب — آخر رابطٍ في السايد بار
        $expected[] = ['type' => 'link', 'href' => route('dashboard')];

        $this->assertSame(
            $expected,
            SidebarMap::outline($html),
            'بنية خريطة 13.4-ح اتغيّرت — عدد البنود أو ترتيبها أو وجهتها. '.
            '(واللافتة ليست محلّ القياس: تغييرها حقٌّ للمالك بنصّ 2.13-ب.)',
        );

        // ثلاثة عشر بندًا كما نصّت الخريطة — لا اثنا عشر ولا أربعة عشر
        $this->assertCount(13, $this->constitutionalEntries());
    }

    /**
     * **ومَن يراها** — الشقّ الثالث من البنية: الظاهر يتحدّد بصلاحيّات العضويّة
     * النشطة، وما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7 · 13.4-ح).
     */
    public function test_the_volunteer_sidebar_shows_only_what_the_membership_allows(): void
    {
        $volunteer = $this->placedVolunteer(['tasks.list']);

        $html = $this->actingAs($volunteer)->get(route('volunteer.tasks.index'))->assertOk()->getContent();
        $destinations = SidebarMap::allDestinations($html);

        // ما يملكه حاضر
        $this->assertContains(route('volunteer.tasks.index'), $destinations);

        // وما لا يملكه **غائبٌ تمامًا** — لا رابط ولا بندٌ باهت
        foreach ([
            route('volunteer.goals'),
            route('volunteer.performance.vxp'),
            route('volunteer.meetings'),
            route('volunteer.library'),
            route('volunteer.recruitment'),
            route('volunteer.escalations'),
        ] as $forbidden) {
            $this->assertNotContains($forbidden, $destinations, "بند ظهر لمن لا يملكه: {$forbidden}");
        }

        // ولا بندَ معطَّلًا: التعطيل يقول «ممنوع» بدل أن يصمت (2.15-أ-7)
        $aside = SidebarMap::aside($html);
        $this->assertStringNotContainsString('pointer-events-none', $aside);
    }

    /**
     * خريطة 13.4-ح صفًّا صفًّا: اسم البند **للرسالة وحدها** (فاللافتة ليست
     * مقياسًا)، والقياس على وجهته. و`null` في موضع البنود يعني رابطًا مفردًا.
     *
     * @return list<array{0:string,1:list<string>|null,2:string|null}>
     */
    private function constitutionalEntries(): array
    {
        return [
            ['نظرة عامّة', [
                route('volunteer.overview'),
                route('volunteer.report'),
                route('volunteer.calendar'),
            ], null],
            ['المهام', [
                route('volunteer.tasks.index'),
                route('volunteer.tasks.board'),
                route('volunteer.contributions'),
                route('volunteer.reviews'),
            ], null],
            ['المشاريع والأهداف', [
                route('volunteer.goals'),
                route('volunteer.goals.build'),
                route('volunteer.goals.launch'),
                route('volunteer.packages'),
                route('volunteer.project'),
                route('volunteer.recurring'),
            ], null],
            ['الأداء', [
                route('volunteer.performance.vxp'),
                route('volunteer.performance.rep'),
                route('volunteer.performance.champion'),
                route('volunteer.performance.evaluations'),
            ], null],
            ['الاجتماعات', [
                route('volunteer.meetings'),
                route('volunteer.attendance'),
            ], null],
            ['المعاملات', [
                route('volunteer.transactions'),
                route('volunteer.objections'),
            ], null],
            ['قسمي', [
                route('volunteer.department'),
                route('volunteer.org'),
                route('volunteer.health'),
                route('volunteer.capacity'),
            ], null],
            ['التصعيدات', [
                route('volunteer.escalations'),
                route('volunteer.escalations.objections'),
                route('volunteer.arbitrations'),
            ], null],
            ['الأكاديمية', [
                route('volunteer.academy'),
                route('volunteer.academy.recordings'),
            ], null],
            // 📚 المكتبة الداخليّة — رابط مفرد بلا شاشات داخليّة
            ['المكتبة الداخليّة', null, route('volunteer.library')],
            ['التقدير', [
                route('volunteer.kudos'),
                route('volunteer.kudos.wall'),
            ], null],
            ['التوظيف', [
                route('volunteer.recruitment'),
                route('volunteer.interviews'),
                route('volunteer.placement'),
            ], null],
            // 🔔 إشعارات التطوّع — رابط مفرد، وتاب في جرس الهيدر كذلك (2.8)
            ['إشعارات التطوّع', null, route('volunteer.notifications')],
        ];
    }

    /** متطوّعٌ يملك مفاتيح الخريطة كلّها — فتظهر البنود الثلاثة عشر بلا نقص */
    private function fullyPermittedVolunteer(): User
    {
        return $this->placedVolunteer([
            'personal_reports.view', 'calendar.view',
            'tasks.list', 'public_board.list', 'contributions.list', 'tasks.approve',
            'goals.list', 'goals.create', 'goals.approve', 'work_packages.list',
            'operational_projects.view', 'recurring_items.view',
            'leaderboards.view', 'rep_transactions.view', 'evaluations.view',
            'meetings.list', 'meeting_attendance.view',
            'rep_transactions.list', 'objections.view',
            'org_chart.view', 'team_health.view', 'capacity.view',
            'escalations.list', 'objections.list', 'arbitration.list',
            'academy_paths.list', 'academy_recordings.list',
            'internal_library.list',
            'kudos.view', 'thanks_wall.view',
            'candidates.list', 'interviews.list', 'placements.list',
        ]);
    }

    /** ومن أيّ شاشةٍ عميقة يصل المتطوّع لبقيّة الخريطة بنقرة — لا بالرجوع للوحة */
    public function test_a_deep_volunteer_screen_links_to_its_sibling_screens(): void
    {
        $response = $this->actingAs($this->placedVolunteer())->get(route('volunteer.recruitment'));

        foreach ([
            route('volunteer.overview'),
            route('volunteer.tasks.index'),
            route('volunteer.goals'),
            route('volunteer.meetings'),
            route('volunteer.transactions'),
            route('volunteer.library'),
            route('volunteer.academy'),
            route('volunteer.kudos'),
        ] as $url) {
            $response->assertSee($url, false);
        }
    }

    /**
     * متطوّعٌ مُسكَّنٌ حقيقيّ: عضويّةٌ نشطة في كيانٍ حقيقيّ + مفاتيح الشاشات
     * بنطاق ALL — فما يُخفى بعدها يكون مخفيًّا بسببٍ آخر لا بالنطاق.
     */
    private function placedVolunteer(?array $permissions = null): User
    {
        $user = User::create([
            'name' => 'متطوّع تجريبيّ',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => 'V'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        $track = Track::firstOrCreate(['key' => 'department'], ['name_ar' => 'قسم', 'name_en' => 'Department']);

        $entity = Entity::create([
            'track_id' => $track->id,
            'name_ar' => 'كيان الحارس',
            'icon' => '🏛️',
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', 'director')->value('id'),
            'is_primary' => true,
            'started_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $this->grant($user, $permissions ?? [
            'goals.list', 'rep_transactions.list', 'internal_library.list',
            'contributions.list', 'tasks.approve', 'meetings.list',
            'academy_paths.list', 'candidates.list', 'org_chart.view',
            'vxp_transactions.view', 'kudos.view', 'contact_consent.list',
            // …وما يظهر في السايد بار نفسه فيثبت أنّ الانتقال بنقرة
            'tasks.list', 'personal_reports.view',
        ]);

        return $user->fresh();
    }

    /** منح المفاتيح بنطاق ALL مباشرةً للمستخدم — عزلًا عن سيدر الأدوار */
    private function grant(User $user, array $keys): void
    {
        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'التطوّع',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

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

        app(AccessEngine::class)->forget($user);
    }
}
