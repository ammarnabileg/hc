<?php

namespace Tests\Feature\Ui;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PermissionExpander;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

            // سايد بار الإدارة حاضر…
            $response->assertSee('لوحة الإدارة', false);
            $response->assertSee('رجوع لحسابي', false);
            $response->assertSee(route('admin.settings.index'), false);

            // …وسايد بار المتدرّب غائب: البقاء داخله هو عين العطل ب-2
            $response->assertDontSee('>تعلّمي<', false);
            $response->assertDontSee('>مكتبتي<', false);
            $response->assertDontSee('>إنجازاتي<', false);
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

    /** خريطة 12.0: الاثنا عشر قسمًا بأسمائها وترتيبها، و«الإعدادات والنظام» آخرها دائمًا */
    public function test_the_twelve_sections_appear_in_the_constitutional_order(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $sections = [
            'لوحة القيادة',
            'إدارة المستخدمين',
            'إدارة التدريب',
            'إدارة الشهادات',
            'إدارة التطوّع',
            'التلعيب والتحديات',
            'المتجر والماليّات',
            'إدارة المكافآت',
            'الفعاليّات',
            'التوجيه والدعم',
            'الإحصائيّات',
            'الإعدادات والنظام',
        ];

        $at = [];

        foreach ($sections as $label) {
            $position = mb_strpos($html, $label);
            $this->assertNotFalse($position, "قسم «{$label}» غائب عن سايد بار الإدارة (12.0).");
            $at[$label] = $position;
        }

        $this->assertSame($sections, array_keys($at), 'ترتيب أقسام 12.0 اتغيّر.');
        $this->assertSame(array_values($at), collect($at)->sort()->values()->all(), 'أقسام 12.0 مش بترتيب الدستور.');

        // «الإعدادات والنظام» آخر قسمٍ دائمًا (12.7)
        $this->assertSame(max($at), $at['الإعدادات والنظام']);
    }

    /** بنود كلّ دروب-داون كما نصّت 12.0 — نقصٌ أو اختلافُ تسميةٍ يسقط الفحص */
    public function test_every_dropdown_carries_the_items_the_map_names(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([
            // 12.13 إدارة المستخدمين
            'قائمة المستخدمين', 'طلبات الاعتماد', 'شرائح الجمهور', 'الأدوار والصلاحيّات',
            // 12.4 إدارة التدريب
            'المسارات', 'التدريبات', 'بنك الأسئلة والامتحانات', 'مكتبة الوسائط', 'إعدادات التعلّم',
            // 12.5 إدارة الشهادات
            'الاعتمادات', 'الأنواع والقوالب', 'إصدار شهادة', 'سجلّ الصادر', 'صفحة التحقّق',
            // إدارة التطوّع
            'الإدارة المركزيّة', 'التوظيف والمرشّحون', 'الهيكل والبوزشنز والسعة',
            'الاجتماعات', 'شهادات التطوّع', 'تحليلات التطوّع',
            // 12.10 التلعيب والتحديات
            'XP والتذاكر', 'الستريك ونادي الخامسة', 'الليدر بورد', 'الشارات والإنجازات',
            'الريفيرال والسفراء', 'الرسائل الإيجابيّة', 'الاحتفالات',
            'أسئلة المكافآت', 'بنك أسئلة الحروب', 'إعدادات الحروب',
            // 12.12 المتجر والماليّات
            'المنتجات والتصنيفات', 'البندلز', 'الكوبونات وOrder-bump',
            'الطلبات والفواتير', 'المكتبة الرقميّة والحماية', 'الماليّات', 'أسعار الصرف',
            // 12.6 التوجيه والدعم
            'التعليمات', 'الإشعارات', 'دليل المستخدم', 'الشكاوى والمقترحات',
            // 12.8 الإحصائيّات
            'المستخدمون', 'المبيعات', 'التفاعل', 'الحضور', 'الحروب', 'التطوّع', 'التقارير المجدولة',
            // 12.7 الإعدادات والنظام
            'إعدادات المنصّة', 'الهويّة والمظهر', 'محتوى الـOnboarding', 'قوالب الـCV',
            'الأمان والخصوصيّة', 'مفاتيح المزايا', 'بيانات الدول',
            'وضع الصيانة', 'التحديثات والترحيل', 'النسخ الاحتياطيّ وصحّة النظام', 'سجلّ التدقيق',
        ] as $item) {
            $this->assertStringContainsString($item, $html, "بند «{$item}» من خريطة 12.0 غائب عن السايد بار.");
        }
    }

    /** ⛔ «الألعاب» ملغاة بقرار المالك (v5.3 — 7.5): لا بند لها في خريطة 12.0 */
    public function test_the_cancelled_games_section_never_returns_to_the_sidebar(): void
    {
        $html = $this->actingAs($this->admin('platform_owner'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $sidebar = Str::between($html, '<aside data-sidebar', '</aside>');

        $this->assertStringNotContainsString('tab=games', $sidebar);
        $this->assertStringNotContainsString('>الألعاب<', $sidebar);
    }

    /** 12.2.1-أ · 2.15-أ-7: بلا صلاحيّة = **مخفيّ لا معطَّل** */
    public function test_a_role_without_a_permission_gets_a_hidden_entry_never_a_disabled_one(): void
    {
        $html = $this->actingAs($this->admin('content_admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // ما لا يملكه: لا رابط ولا اسمٌ باهت
        foreach (['الماليّات', 'أسعار الصرف', 'درجة الالتزام (Rep)', 'طلبات الشحن'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        $this->assertStringNotContainsString(route('admin.finance.index'), $html);
        $this->assertStringNotContainsString(route('admin.wallet.rates'), $html);

        // ولا بندَ معطَّلًا في السايد بار: التعطيل يقول «ممنوع» بدل أن يصمت (2.15-أ-7)
        $sidebar = Str::between($html, '<aside data-sidebar', '</aside>');
        $this->assertStringNotContainsString('pointer-events-none', $sidebar);
        $this->assertStringNotContainsString('disabled', $sidebar);

        // وما يملكه حاضرٌ فعلًا — فالإخفاء ليس تعطيلًا شاملًا
        $this->assertStringContainsString(route('admin.courses.index'), $html);
        $this->assertStringContainsString(route('admin.media.index'), $html);
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
