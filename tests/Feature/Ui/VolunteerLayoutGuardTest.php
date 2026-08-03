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

            // سايد بار التطوّع حاضر…
            $response->assertSee('>إشعارات التطوّع<', false);
            $response->assertSee('>رجوع للرئيسيّة<', false);

            // …وسايد بار المتدرّب غائب: البقاء داخله هو عين العطل
            $response->assertDontSee('>تعلّمي<', false);
            $response->assertDontSee('>مكتبتي<', false);
            $response->assertDontSee('>إنجازاتي<', false);
        }
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
    private function placedVolunteer(): User
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

        $this->grant($user, [
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
