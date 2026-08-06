<?php

namespace Tests\Feature\Ui;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PermissionExpander;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\SidebarMap;

/**
 * حارس ب-2: **كلّ شاشة إدارة تمتدّ من `layouts.admin`**.
 *
 * العطل الذي يمنعه هذا الحارس: عشرون قالبًا كانت تمتدّ من `layouts.admin`
 * وواحدٌ وستّون من `layouts.app` — فتُعرَض شاشة الإدارة داخل **سايد بار
 * المتدرّب** (الرئيسيّة · تعلّمي · مكتبتي · المتجر)، والأدمن لا ينتقل بين
 * شاشتين إلّا بالرجوع للوحة القيادة. والدستور 12.0 و12.4 · 12.5 · 12.6 · 12.7
 * · 12.9 · 12.10 تنصّ صراحةً على «تاب في **سايد بار الإدارة**».
 *
 * والحارس فحصان لا فحصٌ واحد:
 *   1) **ثابت** — لا قالب صفحةٍ تحت `resources/views/admin/**` يمتدّ من غير
 *      `layouts.admin`. أعِد واحدًا إلى `layouts.app` يسقط الفحص فورًا.
 *   2) **حيّ** — بجلسةٍ حقيقيّة: الشاشة تعرض سايد بار الإدارة، لا سايد بار
 *      المتدرّب، والانتقال منها لأخواتها ممكنٌ بنقرة.
 */
class AdminLayoutGuardTest extends UiTestCase
{
    /** الجزئيّات لا `@extends` لها — والمعيار هنا: القالب الذي يمتدّ قالبًا هو صفحة */
    private function adminPageTemplates(): array
    {
        $pages = [];

        foreach (File::allFiles(resource_path('views/admin')) as $file) {
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

    public function test_every_admin_page_template_extends_the_admin_layout(): void
    {
        $pages = $this->adminPageTemplates();

        // شبكة أمان للحارس نفسه: لو صار المجلّد فارغًا فالفحص يمرّ بلا معنى
        $this->assertGreaterThan(70, count($pages), 'مفيش قوالب صفحات تحت admin/ — الحارس بيقيس فراغ.');

        $strays = array_filter($pages, fn (string $layout) => $layout !== 'layouts.admin');

        $this->assertSame([], $strays, implode("\n", array_map(
            fn ($file, $layout) => "{$file} يمتدّ من {$layout} بدل layouts.admin",
            array_keys($strays),
            $strays,
        )));
    }

    /** والعكس بعينه: ولا قالبَ إدارةٍ واحدٍ يمتدّ من قالب المتدرّب */
    public function test_no_admin_page_extends_the_trainee_layout(): void
    {
        $this->assertNotContains('layouts.app', $this->adminPageTemplates());
    }

    public function test_admin_screens_render_the_admin_sidebar_not_the_trainee_one(): void
    {
        $owner = $this->admin('platform_owner');

        // شاشةٌ من كلّ قسمٍ في خريطة 12.0 — وكلّها كانت تمتدّ من `layouts.app`
        foreach ([
            'admin.courses.index',      // 📚 إدارة التدريب
            'admin.certificates.index', // 🎓 إدارة الشهادات
            'admin.volunteer.index',    // 🤝 إدارة التطوّع
            'admin.gamification.index', // 🎮 التلعيب والتحديات
            'admin.store.index',        // 🛒 المتجر والماليّات
            'admin.rewards.index',      // 🎁 إدارة المكافآت
            'admin.events.index',       // 📅 الفعاليّات
            'admin.guidance.index',     // 📣 التوجيه والدعم
            'admin.stats.index',        // 📊 الإحصائيّات
            'admin.settings.index',     // ⚙️ الإعدادات والنظام
            /*
             | وشاشتا الإدارة اللتان تسكنان خارج `views/admin/**` فأفلتتا من الفحص
             | الثابت: أسعار الصرف (بندٌ في 🔒 الماليّات — 12.0) وحلقات النموّ.
             | فالمعيار «كلّ شاشةِ لوحةٍ» لا «كلّ ملفٍّ في مجلّدٍ بعينه».
             */
            'admin.wallet.rates',
            'admin.growth.index',
        ] as $name) {
            $response = $this->actingAs($owner)->get(route($name));

            $response->assertOk();

            $destinations = SidebarMap::allDestinations($response->getContent());

            /*
             | ⭐ **بالوجهة لا باللافتة** (سجلّ القرارات 2026-08-04 · 2.13-ب):
             | اسم البند قيمةٌ افتراضيّة يملك المالك تغييرها، أمّا العنوان الذي
             | يذهب إليه فهو بنية الخريطة نفسها. فمَن أعاد تسمية «لوحة القيادة»
             | لم يخالف، ومَن حذف بابها أسقط الحارس.
             */
            $this->assertContains(route('admin.dashboard'), $destinations);
            $this->assertContains(route('admin.settings.index', ['tab' => 'platform']), $destinations);
            $this->assertContains(route('dashboard'), $destinations, 'باب الرجوع لطبقة المتدرّب غائب.');

            // …وسايد بار المتدرّب غائب: البقاء داخله هو عين العطل ب-2
            foreach ([route('learning.courses'), route('library.index'), route('achievements.badges')] as $trainee) {
                $this->assertNotContains($trainee, $destinations, "وجهة من سايد بار المتدرّب ظهرت في شاشة إدارة: {$trainee}");
            }
        }
    }

    /** والانتقال يعمل فعلًا: من شاشةٍ في قسمٍ تجد روابط بقيّة الأقسام حاضرة */
    public function test_a_deep_admin_screen_links_to_every_other_section(): void
    {
        $response = $this->actingAs($this->admin('platform_owner'))->get(route('admin.store.index'));

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.courses.index'),
            route('admin.certificates.index', ['tab' => 'ledger']),
            route('admin.volunteer.index'),
            route('admin.gamification.index', ['tab' => 'xp']),
            route('admin.rewards.index'),
            route('admin.events.index'),
            route('admin.guidance.index'),
            route('admin.stats.index', ['tab' => 'users']),
            route('admin.settings.index', ['tab' => 'platform']),
        ] as $url) {
            $response->assertSee($url, false);
        }
    }

    /**
     * ⭐ **خريطة 12.0 كاملةً — بالوجهة لا باللافتة** (سجلّ القرارات 2026-08-04).
     *
     * كان هذا الفحص يقيس **الكلمة المكتوبة** على البند وترتيبَ ظهورها في الـHTML،
     * فيسقط في وجه مالكٍ أعاد تسمية «إدارة التدريب» — وهو **حقٌّ يملكه** بنصّ
     * 2.13-ب: كلّ نصٍّ في الدستور قيمةٌ افتراضيّة قابلة للتعديل ما لم يُنَصّ
     * أنّه ثابتٌ نظاميّ، وأسماءُ القوائم ليست منه.
     *
     * **والمحفوظ من الخريطة بنيتُها:** عدد البنود · ترتيبها · **وجهتُها** ·
     * ومَن يراها. فصار المقياس هو الـ`href` — العنوان الذي يفتحه البند.
     * ومَن غيّر لافتةً مرّ، ومَن حذف بندًا أو أزاحه أو حوّل وجهته سقط.
     *
     * ⚠️ **وهو أقوى من سابقه لا أضعف:** الأوّل كان يكتفي بوجود الكلمة في أيّ
     * موضعٍ من الصفحة وبترتيبها، وهذا يقارن **الشجرة كاملة** — الروابط المفردة
     * والمجموعات وبنودَ كلّ مجموعة — بترتيب المستند نفسه، **من داخل السايد بار
     * وحده**، فلا يُجزئ بندًا ناقصًا ولا زائدًا ولا مُزاحًا من مجموعةٍ لأختها.
     */
    public function test_the_map_of_12_0_keeps_its_entries_order_and_destinations(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $expected = [
            ['type' => 'link', 'href' => route('admin.dashboard')],
        ];

        // 🎁 إدارة المكافآت بندٌ مسطّح (12.0 لا ترسم له دروب-داون)، وما عداه مجموعات
        foreach ($this->constitutionalGroups() as [, $items, $flat]) {
            $expected[] = $items === null
                ? ['type' => 'link', 'href' => $flat]
                : ['type' => 'group', 'items' => $items];
        }

        // وباب الرجوع لطبقة المتدرّب — آخر رابطٍ في السايد بار
        $expected[] = ['type' => 'link', 'href' => route('dashboard')];

        $this->assertSame(
            $expected,
            SidebarMap::outline($html),
            'بنية خريطة 12.0 اتغيّرت — عدد البنود أو ترتيبها أو وجهتها. '.
            '(واللافتة ليست محلّ القياس: تغييرها حقٌّ للمالك بنصّ 2.13-ب.)',
        );
    }

    /** بنود كلّ دروب-داون كما نصّت 12.0 — **بوجهاتها**؛ نقصٌ أو إزاحةٌ يسقط الفحص */
    public function test_every_dropdown_carries_the_items_the_map_names(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $groups = SidebarMap::groups($html);
        $expected = array_values(array_filter(
            $this->constitutionalGroups(),
            fn (array $section) => $section[1] !== null,
        ));

        foreach ($expected as $index => [$section, $items]) {
            $this->assertSame(
                $items,
                $groups[$index] ?? [],
                "بنود مجموعة «{$section}» من خريطة 12.0 اختلفت عددًا أو ترتيبًا أو وجهةً.",
            );
        }

        $this->assertCount(count($expected), $groups, 'عدد مجموعات خريطة 12.0 اتغيّر.');
    }

    /**
     * خريطة 12.0 صفًّا صفًّا: اسم القسم **للرسالة وحدها** (فاللافتة ليست مقياسًا)،
     * والقياس على قائمة الوجهات. و`null` تعني بندًا مسطّحًا بلا دروب-داون.
     *
     * @return list<array{0:string,1:list<string>|null,2:string|null}>
     */
    private function constitutionalGroups(): array
    {
        return [
            // 12.13 إدارة المستخدمين
            ['إدارة المستخدمين', [
                route('admin.users.index'),
                route('admin.users.approvals'),
                route('admin.users.segments'),
                route('admin.roles.index'),
            ], null],
            // 12.4 إدارة التدريب
            ['إدارة التدريب', [
                route('admin.paths.index'),
                route('admin.courses.index'),
                route('admin.question-bank.index'),
                route('admin.media.index'),
                route('admin.settings.index', ['tab' => 'learning']),
                route('admin.availability.index'),
            ], null],
            // 12.5 إدارة الشهادات
            ['إدارة الشهادات', [
                route('admin.certificates.index', ['tab' => 'accreditations']),
                route('admin.certificates.index', ['tab' => 'types']),
                route('admin.certificates.index', ['tab' => 'issue']),
                route('admin.certificates.index', ['tab' => 'ledger']),
                route('verify.certificate'),
            ], null],
            // 🤝 إدارة التطوّع (وتستضيف 13.4-ك)
            ['إدارة التطوّع', [
                route('admin.volunteer.index'),
                route('volunteer.recruitment'),
                route('admin.volunteer.org'),
                route('admin.meetings.index'),
                route('admin.volunteer.certificates'),
                route('admin.volunteer.analytics'),
                route('admin.volunteer.org.capacity'),
                route('admin.volunteer.rep'),
                route('admin.volunteer.delegations'),
                route('admin.volunteer.task-types.index'),
                route('admin.volunteer.offboarding'),
            ], null],
            /*
             | 12.10 التلعيب والتحديات
             | ⛔ ولا بند «ألعاب» ولا تابّ `?tab=games` — ملغًى بقرار المالك (v5.3 · 7.5).
             */
            ['التلعيب والتحديات', [
                route('admin.gamification.index', ['tab' => 'xp']),
                route('admin.gamification.index', ['tab' => 'streaks']),
                route('admin.gamification.index', ['tab' => 'leaderboard']),
                route('admin.gamification.index', ['tab' => 'badges']),
                route('admin.referrals.index'),
                route('admin.positive.index'),
                route('admin.gamification.index', ['tab' => 'celebrations']),
                route('admin.gamification.index', ['tab' => 'reward_questions']),
                route('admin.wars.bank.index'),
                route('admin.gamification.index', ['tab' => 'wars']),
            ], null],
            // 12.12 المتجر والماليّات — و🔒 الماليّات لمالك المنصّة وحده
            ['المتجر والماليّات', [
                route('admin.store.index', ['tab' => 'products']),
                route('admin.store.index', ['tab' => 'bundles']),
                route('admin.store.index', ['tab' => 'coupons']),
                route('admin.store.index', ['tab' => 'orders']),
                route('admin.store.index', ['tab' => 'library']),
                route('admin.topups.index'),
                route('admin.finance.index'),
                route('admin.wallet.rates'),
                route('admin.finance.audit'),
            ], null],
            // 12.9 إدارة المكافآت — بندٌ مسطّح بلا دروب-داون
            ['إدارة المكافآت', null, route('admin.rewards.index')],
            // 12.11 الفعاليّات
            ['الفعاليّات', [
                route('admin.events.index'),
                route('admin.events.registrations.index'),
            ], null],
            // 12.6 التوجيه والدعم
            ['التوجيه والدعم', [
                route('admin.guidance.index'),
                route('admin.guidance.notifications'),
                route('admin.guidance.help'),
                route('admin.guidance.complaints'),
                route('admin.articles.index'),
                route('admin.ads.index'),
                route('admin.growth.index'),
            ], null],
            // 12.8 الإحصائيّات
            ['الإحصائيّات', [
                route('admin.stats.index', ['tab' => 'users']),
                route('admin.stats.index', ['tab' => 'sales']),
                route('admin.stats.index', ['tab' => 'training']),
                route('admin.stats.index', ['tab' => 'engagement']),
                route('admin.stats.index', ['tab' => 'attendance']),
                route('admin.stats.index', ['tab' => 'wars']),
                route('admin.stats.index', ['tab' => 'volunteer']),
                route('admin.stats.index', ['tab' => 'certificates']),
                route('admin.report-schedules.index'),
                route('admin.stats.index', ['tab' => 'acquisition']),
            ], null],
            // 🧩 المطوّرين (12.15 — مستحدَثٌ بأمر المالك 2026-08-06) — فوق الإعدادات دائمًا
            ['المطوّرين', [
                route('admin.developers.index', ['tab' => 'api']),
                route('admin.developers.index', ['tab' => 'webhooks']),
            ], null],
            // 12.7 الإعدادات والنظام — آخر قسم دائمًا
            ['الإعدادات والنظام', [
                route('admin.settings.index', ['tab' => 'platform']),
                route('admin.settings.index', ['tab' => 'identity']),
                route('admin.ops.onboarding'),
                route('admin.cv-templates.index'),
                route('admin.settings.index', ['tab' => 'security']),
                route('admin.settings.index', ['tab' => 'features']),
                route('admin.settings.index', ['tab' => 'countries']),
                route('admin.settings.index', ['tab' => 'maintenance']),
                route('admin.ops.updates'),
                route('admin.ops.system'),
                route('admin.settings.index', ['tab' => 'audit']),
                route('admin.studio.index'),
            ], null],
        ];
    }

    /**
     * ⛔ «الألعاب» ملغاة بقرار المالك (v5.3 — 7.5): لا بند لها في خريطة 12.0.
     *
     * والقياس على **الوجهة** لا على الاسم: بندٌ عاد بلافتةٍ أخرى وتابٍّ `games`
     * عودةٌ للملغى، ولافتةٌ اسمها «الألعاب» على وجهةٍ قائمة إعادةُ تسميةٍ مباحة.
     */
    public function test_the_cancelled_games_section_never_returns_to_the_sidebar(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        foreach (SidebarMap::allDestinations($html) as $href) {
            $this->assertStringNotContainsString('games', $href, "وجهة ملغاة عادت للسايد بار: {$href}");
        }
    }

    /** 12.2.1-أ · 2.15-أ-7: بلا صلاحيّة = **مخفيّ لا معطَّل** */
    public function test_a_role_without_a_permission_gets_a_hidden_entry_never_a_disabled_one(): void
    {
        $html = $this->actingAs($this->admin('content_admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        /*
         | ما لا يملكه: **لا وجهة** — والقياس على العنوان لا على اللافتة، فمالكٌ
         | أعاد تسمية «طلبات الشحن» لم يفتح بابًا لمن لا يملكه (2.13-ب).
         */
        $destinations = SidebarMap::allDestinations($html);

        foreach ([
            route('admin.finance.index'),
            route('admin.wallet.rates'),
            route('admin.volunteer.rep'),
            route('admin.topups.index'),
        ] as $forbidden) {
            $this->assertNotContains($forbidden, $destinations, "بند ظهر لمن لا يملكه: {$forbidden}");
            $this->assertStringNotContainsString($forbidden, $html);
        }

        // ولا بندَ معطَّلًا في السايد بار: التعطيل يقول «ممنوع» بدل أن يصمت (2.15-أ-7)
        $sidebar = Str::between($html, '<aside data-sidebar', '</aside>');
        $this->assertStringNotContainsString('pointer-events-none', $sidebar);
        $this->assertStringNotContainsString('disabled', $sidebar);

        // وما يملكه حاضرٌ فعلًا — فالإخفاء ليس تعطيلًا شاملًا
        $this->assertContains(route('admin.courses.index'), $destinations);
        $this->assertContains(route('admin.media.index'), $destinations);
    }

    /** المسؤول الماليّ لا يرى إلّا بابه — والمالك وحده يرى المجموعة المحميّة (2.13-و) */
    public function test_the_protected_finance_group_stays_with_the_platform_owner(): void
    {
        $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.finance.index'), false);

        $this->actingAs($this->admin('finance_admin'))
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.finance.index'), false);
    }

    /** مستخدمٌ بدورٍ إداريّ حقيقيّ — وباب اللوحة مفتاحٌ واحد يقرؤه السايد بار (12.2.1) */
    private function admin(string $roleKey): User
    {
        $user = User::create([
            'name' => 'مسؤول تجريبيّ',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => 'A'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        $user->assignRole($roleKey);

        Permission::updateOrCreate(
            ['key' => 'admin_panel.view'],
            [
                'resource' => 'admin_panel', 'action' => 'view', 'group' => 'النظام والتقارير',
                'label_ar' => 'لوحة الإدارة', 'allowed_scopes' => ['ALL'],
            ],
        );

        app(PermissionExpander::class)->attachToRole(
            Role::where('key', $roleKey)->firstOrFail(),
            'admin_panel.view',
            'ALL',
        );

        return $user->fresh();
    }
}
