<?php

namespace App\Services\Admin\System;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * دماغ شاشة الإعدادات الواحدة بتاباتها الجانبيّة (2.13-و · 2.15-د).
 *
 * لماذا صنف واحد؟ لأنّ قواعد الإعدادات (البحث بالمسار · التصدير/الاستيراد ·
 * الحفظ التلقائيّ · Reset · Audit · عزل الماليّ) قاعدةٌ واحدة لا تتكرّر في كلّ تاب،
 * فلو تكرّرت اختلفت من مكان لمكان — وهذا أخطر ما يصيب لوحة إعدادات.
 */
class SettingsRegistry
{
    /** @var array<int, string>|null ذاكرة الطلب للمجموعات بلا تاب */
    private ?array $unmapped = null;

    /**
     * التابات الجانبيّة: المفتاح ⟵ [العنوان · المجموعات · سطر تعريفيّ].
     * وترتيبها هو ترتيب العرض — ولا يُبنى من قاعدة البيانات حتى لا يتغيّر بالصدفة.
     *
     * ⚠️ قاعدة ملزِمة (2.13): **كلّ مجموعة إعدادات لها تاب هنا**. المجموعة التي
     * لا تجد تابها تسقط في تاب «متنوّعات» فلا يبقى إعدادٌ بلا شاشة أبدًا،
     * ويُبلِّغ عنها `php artisan settings:coverage` لتأخذ تابها الصحيح.
     *
     * @return array<string, array{label:string, groups:array<int,string>, hint:string}>
     */
    public function tabs(): array
    {
        return [
            'platform' => [
                'label' => 'إعدادات المنصّة',
                'groups' => ['system', 'accounts', 'integrations', 'ux', 'feel', 'setup'],
                'hint' => 'الاسم واللغة والبريد والتكاملات وسلوك الجلسات والتنصيب.',
            ],
            'identity' => [
                'label' => 'الهويّة والمظهر',
                'groups' => ['appearance', 'platform'],
                'hint' => 'توكنز الألوان والخطوط والمساحات والزخارف والسايد بار والشعار.',
            ],
            'onboarding' => [
                'label' => 'محتوى الـOnboarding',
                'groups' => ['onboarding'],
                'hint' => 'رحلة التسجيل من التعليمات إلى صفحة القبول.',
            ],
            'public' => [
                'label' => 'الصفحة الرئيسيّة العامّة',
                'groups' => ['home'],
                'hint' => 'محتوى الواجهة العامّة قبل تسجيل الدخول وبيانات الميتا.',
            ],
            'account' => [
                'label' => 'الحساب والخصوصيّة',
                'groups' => ['account'],
                'hint' => 'البروفايل العامّ ومستويات الإظهار وتحميل البيانات والبحث.',
            ],
            'cv' => [
                'label' => 'قوالب الـCV',
                'groups' => ['cv'],
                'hint' => 'تكلفة القوالب بالتذاكر وحدود الأقسام والرابط العامّ.',
            ],
            'learning' => [
                'label' => 'التعلّم والتدريبات',
                'groups' => ['learning', 'paths', 'courses', 'lessons', 'academy', 'availability'],
                'hint' => 'المسارات والتدريبات والدروس وXP وفترات الإتاحة.',
            ],
            'exams' => [
                'label' => 'الامتحانات والشهادات',
                'groups' => ['exams', 'certificates', 'attestations'],
                'hint' => 'قواعد الامتحان وإصدار الشهادات والإفادات والتحقّق العامّ.',
            ],
            'library' => [
                'label' => 'المكتبة والقارئ والوسائط',
                'groups' => ['library', 'reader', 'internal_library', 'media', 'images'],
                'hint' => 'مكتبتي والقارئ والعلامة المائيّة ومكتبة الوسائط واستوديو الصور.',
            ],
            'store' => [
                'label' => 'المتجر والمحفظة',
                'groups' => ['store', 'wallet'],
                'hint' => 'المنتجات والباقات والكوبونات والشحن والعملات.',
            ],
            'engagement' => [
                'label' => 'التلعيب والتفاعل',
                'groups' => [
                    'gamification_xp', 'gamification_badges', 'gamification_streaks',
                    'gamification_leaderboard', 'gamification_wars', 'gamification_celebrations',
                    'gamification_reward_questions',
                    'challenges', 'kudos', 'games', 'gamification_games', 'rewards', 'events', 'engagement',
                    // أسماء قديمة أبقيناها مرساةً بعد مايجريشن التوحيد — فلا يتيتّم مفتاح لو أعاد سيدرٌ زرعها
                    'celebrations', 'streaks', 'leaderboard',
                ],
                'hint' => 'XP والشارات والستريك والليدر بورد والحروب والاحتفالات والفعاليّات.',
            ],
            'volunteer' => [
                'label' => 'التطوّع والفرق',
                'groups' => [
                    'volunteer', 'volunteer_page', 'volunteer_org', 'volunteer_rep',
                    'volunteer_cert', 'volunteer_offboarding', 'volunteer_analytics', 'volunteer_honorary',
                    'recruitment', 'meetings', 'workflow', 'goals', 'performance',
                    'rep', 'offboarding',
                ],
                'hint' => 'الهيكل والسعة وRep والمهام والاجتماعات والتوظيف والخروج.',
            ],
            'dashboards' => [
                'label' => 'اللوحات والإحصاءات',
                'groups' => ['dashboard', 'admin_dashboard', 'stats'],
                'hint' => 'كروت اللوحة ورادار الإنجازات والتقارير والمدى الافتراضيّ.',
            ],
            'comms' => [
                'label' => 'التواصل والإشعارات',
                'groups' => ['notifications', 'announcements', 'complaints', 'help', 'articles'],
                'hint' => 'الجرس والإعلانات والشكاوى ومركز المساعدة والمقالات.',
            ],
            'growth' => [
                'label' => 'النموّ والتسويق',
                'groups' => ['growth', 'ads', 'ambassadors'],
                'hint' => 'الدعوات والإحالات وألقاب السفراء وبكسلات الإعلان وجماهيره.',
            ],
            'governance' => [
                'label' => 'المستخدمون والأدوار',
                'groups' => ['admin_users', 'admin_roles', 'admin_approvals', 'admin_segments', 'admin_content'],
                'hint' => 'جداول المستخدمين ونصوص الأدوار والاعتمادات والشرائح.',
            ],
            'security' => [
                'label' => 'الأمان والخصوصيّة',
                'groups' => ['security'],
                'hint' => 'كلمات المرور والجلسات وحدود المحاولات وسلّة المحذوفات.',
            ],
            'features' => [
                'label' => 'مفاتيح المزايا',
                'groups' => ['features'],
                'hint' => 'إطفاء أو تشغيل أيّ ميزة بلا نشر كود — ولا صيانة جزئيّة.',
            ],
            'countries' => [
                'label' => 'بيانات الدول',
                'groups' => ['countries'],
                'hint' => 'مصدر الدول والمحافظات وسياسة الدمج بلا فقد بيانات.',
            ],
            'maintenance' => [
                'label' => 'وضع الصيانة',
                'groups' => ['maintenance'],
                'hint' => 'قفل المنصّة بالكامل مع تجميد كلّ المهل طوال المدّة.',
            ],
            'updates' => [
                'label' => 'التحديثات والترحيل',
                'groups' => ['updates'],
                'hint' => 'الترقية بنقرة دون فقد بيانات، واسترجاع بضغطة عند الفشل.',
            ],
            'backups' => [
                'label' => 'النسخ الاحتياطيّ وصحّة النظام',
                'groups' => ['backups'],
                'hint' => 'النسخ اليدويّة والمجدولة ومراقبة صحّة النظام.',
            ],
            'misc' => [
                'label' => 'متنوّعات',
                'groups' => [],
                'hint' => 'مجموعات لم تأخذ تابها بعد — تظهر هنا كي لا يبقى إعدادٌ بلا شاشة.',
            ],
            'audit' => [
                'label' => 'سجلّ التدقيق',
                'groups' => [],
                'hint' => 'أثر كامل لكلّ تغيير إداريّ — للقراءة فقط.',
            ],
        ];
    }

    /**
     * عنوان عربيّ ووصف لكلّ مجموعة — رأس الكارت داخل التاب.
     * لماذا هنا؟ لأنّ الكارت يُبنى بمولّد عامّ لا بشاشة يدويّة لكلّ مجموعة (2.15).
     *
     * @return array<string, array{0:string,1:string}>
     */
    public function groupCatalog(): array
    {
        return [
            'system' => ['النظام', 'التوقيت وسلوك المنصّة العامّ.'],
            'accounts' => ['الحسابات والتفعيل', 'مجانيّة التفعيل والاعتماد الإداريّ وبادئة الكود.'],
            'integrations' => ['التكاملات', 'البريد والخدمات الخارجيّة.'],
            'ux' => ['البساطة أوّلًا', 'حدود الكروت والفلاتر والأعمدة ومدد التراجع والـToast.'],
            'feel' => ['طبقة الإحساس', 'العدّادات والاهتزاز وصوت التوقيع.'],
            'appearance' => ['الهويّة البصريّة', 'الألوان والخطوط والمساحات والزخارف.'],
            'platform' => ['شعار المنصّة', 'الشعار الظاهر في الواجهات والمستندات.'],
            'setup' => ['التنصيب', 'خطوات التنصيب ومتطلّباته وحساب المالك الأوّل.'],
            'home' => ['الصفحة الرئيسيّة', 'البطل والأقسام والميتا وSchema.org.'],
            'engagement' => ['الرسائل الإيجابيّة', 'المفاجآت والتذاكر وسياقات الظهور.'],
            'ambassadors' => ['ألقاب السفراء', 'العتبات ولوحة المتصدّرين وإشعار اللقب.'],
            'onboarding' => ['التعريف بالمنصّة', 'رحلة أوّل دخول.'],
            'account' => ['الحساب والخصوصيّة', 'مستويات الإظهار والموافقات وتحميل البيانات.'],
            'cv' => ['السيرة الذاتيّة', 'القوالب وتكلفتها وحدود الأقسام والرابط العامّ.'],
            'learning' => ['التعلّم', 'الدروس وXP والتقدّم والنصوص التحفيزيّة.'],
            'paths' => ['المسارات', 'ترتيب المسارات وعرضها.'],
            'courses' => ['التدريبات', 'الإتاحة والتسجيل والغلاف.'],
            'lessons' => ['الدروس', 'الفيديو والمرفقات وشروط الإكمال.'],
            'academy' => ['الأكاديميّة', 'التسجيلات والجلسات.'],
            'availability' => ['الإتاحة والتوقيت', 'فترات فتح التدريبات والمنطقة الزمنيّة للمتدرّب.'],
            'exams' => ['الامتحانات', 'المحاولات والزمن والنجاح والرسوب.'],
            'certificates' => ['الشهادات', 'الإصدار والتصميم والتحقّق العامّ.'],
            'attestations' => ['الإفادات', 'ورقة الإفادة وأماكن ظهورها.'],
            'library' => ['مكتبتي', 'الملفّات والعلامة المائيّة والتنزيل.'],
            'reader' => ['القارئ', 'التصفّح والتظليل والملاحظات.'],
            'internal_library' => ['المكتبة الداخليّة', 'موارد الفريق ومستويات الوصول.'],
            'media' => ['مكتبة الوسائط', 'الرفع والأنواع والأحجام.'],
            'images' => ['استوديو الصور', 'التوليد والكاش والعلامة المائيّة.'],
            'store' => ['المتجر', 'المنتجات والباقات والكوبونات والشحن وسياسة الاسترجاع.'],
            'wallet' => ['المحفظة', 'أكواد العملات ومواضع الكسب والصرف.'],
            'gamification_xp' => ['XP والمستويات', 'مصادر الخبرة وسلّم المستويات.'],
            'gamification_badges' => ['الشارات', 'شروط المنح والعرض.'],
            'gamification_streaks' => ['الستريك ونادي الخامسة', 'النافذة والتجميد والمكافأة.'],
            'gamification_leaderboard' => ['الليدر بورد', 'النطاقات والتجميد وحدّ المشاركين.'],
            'gamification_wars' => ['حروب التركيز', 'الجولات والفرق والجوائز.'],
            'gamification_celebrations' => ['الاحتفالات', 'المستويات الثلاثة والصوت والمشاركة.'],
            'gamification_reward_questions' => ['أسئلة المكافآت', 'بنك أسئلة المكافأة وشروط عرضها.'],
            'celebrations' => ['الاحتفالات (نصوص)', 'نصوص التهنئة والكونفيتي والإغلاق التلقائيّ.'],
            'streaks' => ['الستريك (عرض)', 'الخريطة الحراريّة ونافذة نادي الخامسة.'],
            'leaderboard' => ['الليدر بورد (عرض)', 'عدد الصفوف المعروضة.'],
            'challenges' => ['التحديات', 'المدد والانضمام والنتائج.'],
            'kudos' => ['التقدير', 'الحدود اليوميّة ونصوص الشكر.'],
            'games' => ['الألعاب', 'الكتالوج وتكلفة اللعبة بالتذاكر.'],
            'gamification_games' => ['ضبط الألعاب', 'التفعيل وتكلفة الدخول والسقوف اليوميّة.'],
            'rewards' => ['المكافآت', 'المخزون والصرف والحدود.'],
            'events' => ['الفعاليّات', 'التسجيل والحضور وكود الحضور والتذكير.'],
            'volunteer' => ['التطوّع — عامّ', 'القواعد المشتركة لطبقة التطوّع.'],
            'volunteer_page' => ['صفحة تطوّع معنا', 'العنوان والميثاق والعدّادات وكتل المحتوى.'],
            'volunteer_org' => ['الهيكل والسعة', 'الكانفاس وعتبات الإشغال والموازن.'],
            'volunteer_rep' => ['السمعة (Rep)', 'السقوف والتصفير والاعتراض والخمول.'],
            'volunteer_cert' => ['شهادات التطوّع', 'شروط الإصدار والأنواع والعرض.'],
            'volunteer_offboarding' => ['الخروج والعودة', 'التصفية والتبريد ومقابلة الخروج.'],
            'volunteer_analytics' => ['تحليلات التطوّع', 'المدى والمؤشّرات.'],
            'volunteer_honorary' => ['المناصب الفخريّة', 'الألقاب الفخريّة وشروط منحها وعرضها.'],
            'recruitment' => ['التوظيف والترشيح', 'الفرز والمقابلات والقبول.'],
            'meetings' => ['الاجتماعات', 'الحضور والمحضر والمهل.'],
            'workflow' => ['المهام والتسليم', 'السقوف والتمديد والتعثّر والديدلاين.'],
            'goals' => ['الأهداف', 'الدورات والقياس والمراجعة.'],
            'performance' => ['الأداء', 'المؤشّرات ودوريّة التقييم.'],
            'rep' => ['السمعة (قواعد عامّة)', 'مهل الاعتراض والتصفير الشهريّ.'],
            'offboarding' => ['الخروج (قواعد عامّة)', 'الخمول والتبريد ومهلة التسليم.'],
            'dashboard' => ['لوحة المتدرّب', 'الكروت ورادار الإنجازات والعدّادات.'],
            'admin_dashboard' => ['لوحة الإدارة', 'الكروت والقمع والمدى الافتراضيّ.'],
            'stats' => ['الإحصائيّات', 'المدى والتصدير وإخفاء التابات.'],
            'notifications' => ['الإشعارات', 'الجرس والقنوات والتجميع.'],
            'announcements' => ['الإعلانات', 'الاستهداف والمدّة والأولويّة.'],
            'complaints' => ['الشكاوى', 'المهل والتصنيف والتصعيد.'],
            'help' => ['مركز المساعدة', 'الأقسام والبحث.'],
            'articles' => ['المقالات', 'دورة النشر والمراجعة.'],
            'growth' => ['النموّ والدعوات', 'الإحالة والمكافأة وحدودها.'],
            'ads' => ['الإعلان المدفوع', 'البكسلات والجماهير وموافقة التتبّع.'],
            'admin_users' => ['إدارة المستخدمين', 'الجداول والتابات والأفعال.'],
            'admin_roles' => ['الأدوار والصلاحيّات', 'نصوص المنع والتصعيد والمجموعة المحميّة.'],
            'admin_approvals' => ['الاعتمادات', 'قوائم الانتظار والمهل.'],
            'admin_segments' => ['الشرائح', 'تعريف الشرائح واستخدامها.'],
            'admin_content' => ['إدارة المحتوى', 'سجلّ التدقيق وحدوده.'],
            'security' => ['الأمان', 'كلمات المرور والجلسات والمحاولات.'],
            'features' => ['مفاتيح المزايا', 'تشغيل وإطفاء المزايا.'],
            'countries' => ['الدول والمحافظات', 'المصدر وسياسة الدمج.'],
            'maintenance' => ['وضع الصيانة', 'المدّة والرسالة وتجميد المهل.'],
            'updates' => ['التحديثات', 'الترقية والاسترجاع.'],
            'backups' => ['النسخ الاحتياطيّ', 'الجدولة والاحتفاظ.'],
            'finance' => ['🔒 الماليّات', 'الأسعار والعمولات والسحب والاسترجاع — لمالك المنصّة وحده.'],
        ];
    }

    /** عنوان المجموعة العربيّ — والمجموعة المجهولة تُعرَض بمفتاحها بلا كسر */
    public function groupLabel(string $group): string
    {
        return $this->groupCatalog()[$group][0] ?? $group;
    }

    public function groupHint(string $group): string
    {
        return $this->groupCatalog()[$group][1] ?? 'مجموعة بلا وصف بعد — أضِف وصفها في `groupCatalog()`.';
    }

    /** المجموعات المسنَدة لتاب ⟵ التاب (خريطة البحث الموحّد ومرجع التغطية) */
    public function groupToTab(): array
    {
        $map = [];

        foreach ($this->tabs() as $tabKey => $tab) {
            foreach ($tab['groups'] as $group) {
                $map[$group] = $tabKey;
            }
        }

        // 🔒 الماليّات تاب خاصّ بمالك المنصّة، لكنّ خريطة البحث تعرف مكانها دائمًا
        $map['finance'] = 'finance';

        return $map;
    }

    /**
     * المجموعات الموجودة في قاعدة البيانات بلا تاب مخصّص — تسقط في «متنوّعات».
     * وتُحسَب مرّةً واحدة في الطلب: `tabsFor()` تُستدعى أكثر من مرّة في الصفحة.
     */
    public function unmappedGroups(): array
    {
        if ($this->unmapped !== null) {
            return $this->unmapped;
        }

        $mapped = $this->groupToTab();

        return $this->unmapped = Setting::query()
            ->select('group')
            ->distinct()
            ->pluck('group')
            ->reject(fn (string $group) => isset($mapped[$group]))
            ->values()
            ->all();
    }

    /**
     * ⭐ المجموعة الماليّة معزولة لمالك المنصّة وحده (2.13-و · 24.3).
     * والتاب لا يظهر أصلًا لغيره — لا معطَّلًا ولا رماديًّا (2.15-أ-7).
     */
    public function tabsFor(User $user): array
    {
        $tabs = $this->tabs();

        if ($user->isPlatformOwner()) {
            $tabs['finance'] = [
                'label' => '🔒 الماليّات',
                'groups' => ['finance'],
                'hint' => 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ — مجموعة محميّة.',
            ];
        }

        // تاب «متنوّعات» لا يظهر إلّا إن كان فيه فعلًا مجموعةٌ بلا تاب — وإلّا فهو ضجيج (2.15)
        if ($tabs['misc']['groups'] === [] && $this->unmappedGroups() === []) {
            unset($tabs['misc']);
        }

        return $tabs;
    }

    /** إعدادات تاب بعينه، مرتّبةً ومصفّاةً بحسب مَن ينظر */
    public function forTab(string $tab, User $user, string $search = ''): Collection
    {
        $groups = $tab === 'misc'
            ? $this->unmappedGroups()
            : ($this->tabsFor($user)[$tab]['groups'] ?? []);

        if ($groups === []) {
            return collect();
        }

        return $this->visible($user)
            ->whereIn('group', $groups)
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (Setting $s) => str_contains($s->key, $search) || str_contains($s->label_ar, $search)
            ))
            ->sortBy('key')
            ->values();
    }

    /**
     * ⭐ مادّة العرض: إعدادات التاب مقسَّمةً على كروت مجموعاتها بعناوين عربيّة.
     * لماذا مقسَّمة؟ لأنّ تابًا فيه 190 مفتاحًا مسطّحًا شاشةٌ لا تُقرَأ (2.15)،
     * والكارت المطويّ يجعل «سؤالًا واحدًا» ظاهرًا في المرّة.
     *
     * @return Collection<string, Collection<int, Setting>>
     */
    public function groupedForTab(string $tab, User $user, string $search = ''): Collection
    {
        $order = $tab === 'misc'
            ? $this->unmappedGroups()
            : ($this->tabsFor($user)[$tab]['groups'] ?? []);

        $rows = $this->forTab($tab, $user, $search)->groupBy('group');

        return collect($order)
            ->filter(fn (string $group) => $rows->has($group))
            ->mapWithKeys(fn (string $group) => [$group => $rows->get($group)]);
    }

    /**
     * ⭐ بحث موحّد داخل كلّ الإعدادات بالاسم أو بالقيمة، والنتيجة **بمسارها الكامل**
     * (التاب › المجموعة › الحقل) لتنقل للحقل مع تظليل مؤقّت (2.13-و).
     *
     * @return array<int, array{key:string,label:string,tab:string,tab_label:string,group:string,path:string,value:?string}>
     */
    public function search(string $term, User $user): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $tabs = $this->tabsFor($user);
        $groupToTab = $this->groupToTab();

        return $this->visible($user)
            ->filter(fn (Setting $s) => str_contains(mb_strtolower($s->key), mb_strtolower($term))
                || str_contains($s->label_ar, $term)
                || str_contains((string) $s->value, $term))
            ->take(max(5, (int) setting('ux.settings_search.max_results', 40)))
            ->map(function (Setting $s) use ($tabs, $groupToTab) {
                // ⭐ المفتاح يفتح **تابه هو**؛ والمجموعة بلا تاب تفتح «متنوّعات»
                // — لا «إعدادات المنصّة» كما كان، فالنتيجة كانت تودّي لتابٍ لا تسكنه.
                $tabKey = $groupToTab[$s->group] ?? 'misc';
                $tabLabel = $tabs[$tabKey]['label'] ?? $this->tabs()[$tabKey]['label'] ?? 'متنوّعات';

                return [
                    'key' => $s->key,
                    'label' => $s->label_ar,
                    'tab' => $tabKey,
                    'tab_label' => $tabLabel,
                    'group' => $s->group,
                    'path' => $tabLabel.' › '.$this->groupLabel($s->group).' › '.$s->label_ar,
                    'value' => $s->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * حفظ حقل واحد (حفظ تلقائيّ مع «تم الحفظ» جنب الحقل — 2.13-و).
     * وحماية من الحفظ الناقص: الرقم لا يُحفَظ إلّا داخل نطاقه.
     *
     * @return array{saved:bool, message:string, value:?string}
     */
    public function save(Setting $setting, mixed $value, User $actor): array
    {
        if (! $this->mayEdit($setting, $actor)) {
            return ['saved' => false, 'message' => 'الإعداد ده لمالك المنصّة وحده.', 'value' => $setting->value];
        }

        if ($this->isDisabled($setting)) {
            return ['saved' => false, 'message' => 'فعّل الميزة أوّلًا.', 'value' => $setting->value];
        }

        $normalized = $this->normalize($setting, $value);

        if ($normalized === null) {
            return [
                'saved' => false,
                'message' => 'القيمة خارج النطاق — '.$this->rangeHint($setting),
                'value' => $setting->value,
            ];
        }

        $old = $setting->value;

        if ($old === $normalized) {
            return ['saved' => true, 'message' => 'تم الحفظ ✓', 'value' => $normalized];
        }

        $setting->update(['value' => $normalized]);
        $this->audit($setting, $old, $normalized, $actor, 'settings.update');
        $this->flush();

        return ['saved' => true, 'message' => 'تم الحفظ ✓', 'value' => $normalized];
    }

    /** ↺ Reset لحقل واحد — والافتراضيّ يبقى ظاهرًا دائمًا كـPlaceholder (مرساة) */
    public function reset(Setting $setting, User $actor): array
    {
        return $this->save($setting, $setting->default_value, $actor);
    }

    /** الرجوع لآخر قيمة قبل التغيير الأخير — «تراجع عن آخر تغيير» */
    public function undo(Setting $setting, User $actor): array
    {
        $last = $this->lastChange($setting);

        if (! $last) {
            return ['saved' => false, 'message' => 'مفيش تغيير سابق نرجع له.', 'value' => $setting->value];
        }

        return $this->save($setting, $last['old'], $actor);
    }

    /**
     * ⭐ Audit بالـHover — آخر تغيير فقط (قيمة سابقة واحدة) مع رابط لبروفايل المحرّر.
     * والسجلّ نفسه يبقى Append-only في صفحة سجلّ التدقيق — فلا تعارض.
     *
     * @return array{old:?string,new:?string,by:?string,by_url:?string,at:string}|null
     */
    public function lastChange(Setting $setting): ?array
    {
        $log = AuditLog::query()
            ->where('auditable_type', $setting->getMorphClass())
            ->where('auditable_id', $setting->getKey())
            ->latest('id')
            ->first();

        if (! $log) {
            return null;
        }

        $editor = $log->user;

        return [
            'old' => $log->old_values['value'] ?? null,
            'new' => $log->new_values['value'] ?? null,
            'by' => $editor?->name,
            'by_url' => $editor?->profileUrl(),
            'at' => $log->created_at?->diffForHumans() ?? '',
        ];
    }

    /** تصدير الإعدادات المرئيّة لهذا المستخدم كـJSON (نقل التخصيص في ثوانٍ) */
    public function export(User $user): array
    {
        return $this->visible($user)
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value])
            ->all();
    }

    /**
     * استيراد JSON — يتخطّى المفاتيح المجهولة والممنوعة بصمت مُحصى،
     * فلا يفتح الاستيرادُ بابًا خلفيًّا على المجموعة الماليّة.
     *
     * @return array{applied:int, skipped:int}
     */
    public function import(array $payload, User $user): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ($payload as $key => $value) {
            $setting = Setting::query()->where('key', $key)->first();

            if (! $setting || ! $this->mayEdit($setting, $user)) {
                $skipped++;

                continue;
            }

            $result = $this->save($setting, $value, $user);
            $result['saved'] ? $applied++ : $skipped++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /** هل يجوز لهذا المستخدم تعديل هذا الإعداد؟ (عزل الحسّاس) */
    public function mayEdit(Setting $setting, User $user): bool
    {
        if ($setting->is_owner_only || $setting->group === 'finance') {
            return $user->isPlatformOwner();
        }

        return true;
    }

    /**
     * ⭐ إعدادات ميزة موقوفة تظهر **معطَّلة** بسطر «فعّل الميزة أوّلًا» (2.13-و)
     * — بدل تعديل بلا أثر يوهم الأدمن أنّه غيّر شيئًا.
     */
    public function isDisabled(Setting $setting): bool
    {
        $toggle = $this->togglerOf($setting->key);

        return $toggle !== null && ! setting($toggle, true);
    }

    /** مفتاح الميزة الحاكم لهذا الإعداد (إن وُجد) */
    public function togglerOf(string $key): ?string
    {
        foreach ($this->toggleMap() as $prefix => $toggle) {
            if ($key !== $toggle && str_starts_with($key, $prefix)) {
                return $toggle;
            }
        }

        return null;
    }

    /** مثال بالقيمة يتحدّث لحظيًّا مع الكتابة («72 = 3 أيّام») — خطّ الدفاع الأوّل */
    public function liveExample(Setting $setting): ?string
    {
        if ($setting->type !== 'number') {
            return null;
        }

        $value = (float) ($setting->value ?? 0);

        return match (true) {
            str_contains($setting->key, 'hours') => rtrim(rtrim(number_format($value / 24, 2, '.', ''), '0'), '.').' يوم',
            str_contains($setting->key, 'minutes') => rtrim(rtrim(number_format($value / 60, 2, '.', ''), '0'), '.').' ساعة',
            str_contains($setting->key, 'percent') => 'من كلّ 100 ⟵ '.$value,
            default => null,
        };
    }

    /** النطاق المسموح لكلّ نوع رقميّ — يُعرَض كسطر خفيف لا كخطأ أحمر صارخ */
    public function rangeHint(Setting $setting): string
    {
        [$min, $max] = $this->range($setting);

        return "من {$min} إلى {$max}";
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int, Setting> */
    private function visible(User $user): Collection
    {
        $query = Setting::query();

        if (! $user->isPlatformOwner()) {
            $query->where('is_owner_only', false)->where('group', '!=', 'finance');
        }

        return $query->orderBy('key')->get();
    }

    /** @return array{0:float,1:float} */
    private function range(Setting $setting): array
    {
        return match (true) {
            str_contains($setting->key, 'percent') => [0, 100],
            str_contains($setting->key, 'hours') => [1, 8760],
            str_contains($setting->key, 'days') => [1, 3650],
            default => [0, 1000000],
        };
    }

    private function normalize(Setting $setting, mixed $value): ?string
    {
        if ($setting->type === 'bool') {
            return in_array($value, [true, 1, '1', 'true', 'on'], true) ? '1' : '0';
        }

        if ($setting->type === 'number') {
            if (! is_numeric($value)) {
                return null;
            }

            [$min, $max] = $this->range($setting);

            return ($value < $min || $value > $max) ? null : (string) ($value + 0);
        }

        if ($setting->type === 'json') {
            $decoded = is_array($value) ? $value : json_decode((string) $value, true);

            return $decoded === null ? null : json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /** سطر Audit — والصفحة تعرض آخر واحد فقط، والجدول يبقى Append-only */
    public function audit(Setting $setting, ?string $old, ?string $new, User $actor, string $action, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $setting->getMorphClass(),
            'auditable_id' => $setting->getKey(),
            'old_values' => ['value' => $old],
            'new_values' => array_filter(['value' => $new, 'reason' => $reason], fn ($v) => $v !== null),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    private function flush(): void
    {
        Cache::forget('settings');
    }

    /** بادئة الإعداد ⟵ مفتاح الميزة الحاكم */
    private function toggleMap(): array
    {
        return [
            'store.' => 'store.enabled',
            'bundles.' => 'bundles.enabled',
            'coupons.' => 'coupons.enabled',
            'order_bump.' => 'order_bump.enabled',
            'topup.manual.' => 'topup.manual.enabled',
            'topup.gateway.' => 'topup.gateway.enabled',
            'articles.' => 'articles.enabled',
            'images.' => 'images.enabled',
            'ads.' => 'ads.tracking.enabled',
        ];
    }
}
