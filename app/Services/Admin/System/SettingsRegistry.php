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
                'label' => setting('system.settings_registry.tabs_1', 'إعدادات المنصّة'),
                'groups' => ['system', 'accounts', 'integrations', 'ux', 'feel', 'setup'],
                'hint' => setting('system.settings_registry.tabs_2', 'الاسم واللغة والبريد والتكاملات وسلوك الجلسات والتنصيب.'),
            ],
            'identity' => [
                'label' => setting('system.settings_registry.tabs_3', 'الهويّة والمظهر'),
                // ⭐ `nav` = **لافتات** بنود السايد بار (12.0 · 13.4-ح · 24.5-أ) — وموضعها
                // هنا بنصّ بلوك الإعدادات: «[السايد بار] Toggle وترتيب بالسحب لكلّ عنصر
                // + **نصّ كلّ زرّ** (ع/إ) + صلاحيّة الظهور». والاسم يُعدَّل، والبنية لا (2.13-ب).
                'groups' => ['appearance', 'platform', 'nav'],
                'hint' => setting('system.settings_registry.tabs_4', 'توكنز الألوان والخطوط والمساحات والزخارف والسايد بار والشعار.'),
            ],
            'onboarding' => [
                'label' => setting('system.settings_registry.tabs_5', 'محتوى الـOnboarding'),
                'groups' => ['onboarding'],
                'hint' => setting('system.settings_registry.tabs_6', 'رحلة التسجيل من التعليمات إلى صفحة القبول.'),
            ],
            'public' => [
                'label' => setting('system.settings_registry.tabs_7', 'الصفحة الرئيسيّة العامّة'),
                'groups' => ['home'],
                'hint' => setting('system.settings_registry.tabs_8', 'محتوى الواجهة العامّة قبل تسجيل الدخول وبيانات الميتا.'),
            ],
            'account' => [
                'label' => setting('system.settings_registry.tabs_9', 'الحساب والخصوصيّة'),
                'groups' => ['account'],
                'hint' => setting('system.settings_registry.tabs_10', 'البروفايل العامّ ومستويات الإظهار وتحميل البيانات والبحث.'),
            ],
            'cv' => [
                'label' => setting('system.settings_registry.tabs_11', 'قوالب الـCV'),
                'groups' => ['cv'],
                'hint' => setting('system.settings_registry.tabs_12', 'تكلفة القوالب بالتذاكر وحدود الأقسام والرابط العامّ.'),
            ],
            'learning' => [
                'label' => setting('system.settings_registry.tabs_13', 'التعلّم والتدريبات'),
                'groups' => ['learning', 'paths', 'courses', 'lessons', 'academy', 'availability'],
                'hint' => setting('system.settings_registry.tabs_14', 'المسارات والتدريبات والدروس وXP وفترات الإتاحة.'),
            ],
            'exams' => [
                'label' => setting('system.settings_registry.tabs_15', 'الامتحانات والشهادات'),
                'groups' => ['exams', 'certificates', 'attestations'],
                'hint' => setting('system.settings_registry.tabs_16', 'قواعد الامتحان وإصدار الشهادات والإفادات والتحقّق العامّ.'),
            ],
            'library' => [
                'label' => setting('system.settings_registry.tabs_17', 'المكتبة والقارئ والوسائط'),
                'groups' => ['library', 'reader', 'internal_library', 'media', 'images'],
                'hint' => setting('system.settings_registry.tabs_18', 'مكتبتي والقارئ والعلامة المائيّة ومكتبة الوسائط واستوديو الصور.'),
            ],
            'store' => [
                'label' => setting('system.settings_registry.tabs_19', 'المتجر والمحفظة'),
                'groups' => ['store', 'wallet'],
                'hint' => setting('system.settings_registry.tabs_20', 'المنتجات والباقات والكوبونات والشحن والعملات.'),
            ],
            'engagement' => [
                'label' => setting('system.settings_registry.tabs_21', 'التلعيب والتفاعل'),
                'groups' => [
                    'gamification_xp', 'gamification_badges', 'gamification_streaks',
                    'gamification_leaderboard', 'gamification_wars', 'gamification_celebrations',
                    'gamification_reward_questions',
                    'challenges', 'kudos', 'rewards', 'events', 'engagement',
                    // أسماء قديمة أبقيناها مرساةً بعد مايجريشن التوحيد — فلا يتيتّم مفتاح لو أعاد سيدرٌ زرعها
                    'celebrations', 'streaks', 'leaderboard',
                ],
                'hint' => setting('system.settings_registry.tabs_22', 'XP والشارات والستريك والليدر بورد والحروب والاحتفالات والفعاليّات.'),
            ],
            'volunteer' => [
                'label' => setting('system.settings_registry.tabs_23', 'التطوّع والفرق'),
                'groups' => [
                    'volunteer', 'volunteer_page', 'volunteer_org', 'volunteer_rep',
                    'volunteer_cert', 'volunteer_offboarding', 'volunteer_analytics', 'volunteer_honorary',
                    'recruitment', 'meetings', 'workflow', 'goals', 'performance',
                    'rep', 'offboarding',
                ],
                'hint' => setting('system.settings_registry.tabs_24', 'الهيكل والسعة وRep والمهام والاجتماعات والتوظيف والخروج.'),
            ],
            'dashboards' => [
                'label' => setting('system.settings_registry.tabs_25', 'اللوحات والإحصاءات'),
                'groups' => ['dashboard', 'admin_dashboard', 'stats'],
                'hint' => setting('system.settings_registry.tabs_26', 'كروت اللوحة ورادار الإنجازات والتقارير والمدى الافتراضيّ.'),
            ],
            'comms' => [
                'label' => setting('system.settings_registry.tabs_27', 'التواصل والإشعارات'),
                'groups' => ['notifications', 'announcements', 'complaints', 'help', 'articles'],
                'hint' => setting('system.settings_registry.tabs_28', 'الجرس والإعلانات والشكاوى ومركز المساعدة والمقالات.'),
            ],
            'growth' => [
                'label' => setting('system.settings_registry.tabs_29', 'النموّ والتسويق'),
                'groups' => ['growth', 'ads', 'ambassadors'],
                'hint' => setting('system.settings_registry.tabs_30', 'الدعوات والإحالات وألقاب السفراء وبكسلات الإعلان وجماهيره.'),
            ],
            'governance' => [
                'label' => setting('system.settings_registry.tabs_31', 'المستخدمون والأدوار'),
                'groups' => ['admin_users', 'admin_roles', 'admin_approvals', 'admin_segments', 'admin_content'],
                'hint' => setting('system.settings_registry.tabs_32', 'جداول المستخدمين ونصوص الأدوار والاعتمادات والشرائح.'),
            ],
            'security' => [
                'label' => setting('system.settings_registry.tabs_33', 'الأمان والخصوصيّة'),
                'groups' => ['security'],
                'hint' => setting('system.settings_registry.tabs_34', 'كلمات المرور والجلسات وحدود المحاولات وسلّة المحذوفات.'),
            ],
            'features' => [
                'label' => setting('system.settings_registry.tabs_35', 'مفاتيح المزايا'),
                'groups' => ['features'],
                'hint' => setting('system.settings_registry.tabs_36', 'إطفاء أو تشغيل أيّ ميزة بلا نشر كود — ولا صيانة جزئيّة.'),
            ],
            'countries' => [
                'label' => setting('system.settings_registry.tabs_37', 'بيانات الدول'),
                'groups' => ['countries'],
                'hint' => setting('system.settings_registry.tabs_38', 'مصدر الدول والمحافظات وسياسة الدمج بلا فقد بيانات.'),
            ],
            'maintenance' => [
                'label' => setting('system.settings_registry.tabs_39', 'وضع الصيانة'),
                'groups' => ['maintenance'],
                'hint' => setting('system.settings_registry.tabs_40', 'قفل المنصّة بالكامل مع تجميد كلّ المهل طوال المدّة.'),
            ],
            'updates' => [
                'label' => setting('system.settings_registry.tabs_41', 'التحديثات والترحيل'),
                'groups' => ['updates'],
                'hint' => setting('system.settings_registry.tabs_42', 'الترقية بنقرة دون فقد بيانات، واسترجاع بضغطة عند الفشل.'),
            ],
            'backups' => [
                'label' => setting('system.settings_registry.tabs_43', 'النسخ الاحتياطيّ وصحّة النظام'),
                'groups' => ['backups'],
                'hint' => setting('system.settings_registry.tabs_44', 'النسخ اليدويّة والمجدولة ومراقبة صحّة النظام.'),
            ],
            'misc' => [
                'label' => setting('system.settings_registry.tabs_45', 'متنوّعات'),
                'groups' => [],
                'hint' => setting('system.settings_registry.tabs_46', 'مجموعات لم تأخذ تابها بعد — تظهر هنا كي لا يبقى إعدادٌ بلا شاشة.'),
            ],
            'audit' => [
                'label' => setting('system.settings_registry.tabs_47', 'سجلّ التدقيق'),
                'groups' => [],
                'hint' => setting('system.settings_registry.tabs_48', 'أثر كامل لكلّ تغيير إداريّ — للقراءة فقط.'),
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
            'system' => [setting('system.settings_registry.group_catalog_1', 'النظام'), setting('system.settings_registry.group_catalog_2', 'التوقيت وسلوك المنصّة العامّ.')],
            'accounts' => [setting('system.settings_registry.group_catalog_3', 'الحسابات والتفعيل'), setting('system.settings_registry.group_catalog_4', 'مجانيّة التفعيل والاعتماد الإداريّ وبادئة الكود.')],
            'integrations' => [setting('system.settings_registry.group_catalog_5', 'التكاملات'), setting('system.settings_registry.group_catalog_6', 'البريد والخدمات الخارجيّة.')],
            'ux' => [setting('system.settings_registry.group_catalog_7', 'البساطة أوّلًا'), setting('system.settings_registry.group_catalog_8', 'حدود الكروت والفلاتر والأعمدة ومدد التراجع والـToast.')],
            'feel' => [setting('system.settings_registry.group_catalog_9', 'طبقة الإحساس'), setting('system.settings_registry.group_catalog_10', 'العدّادات والاهتزاز وصوت التوقيع.')],
            'appearance' => [setting('system.settings_registry.group_catalog_11', 'الهويّة البصريّة'), setting('system.settings_registry.group_catalog_12', 'الألوان والخطوط والمساحات والزخارف.')],
            'platform' => [setting('system.settings_registry.group_catalog_13', 'شعار المنصّة'), setting('system.settings_registry.group_catalog_14', 'الشعار الظاهر في الواجهات والمستندات.')],
            'nav' => [setting('system.settings_registry.group_catalog_15', 'لافتات السايد بار والتنقّل'), setting('system.settings_registry.group_catalog_16', 'أسماء بنود سايد بار الإدارة والتطوّع والمتدرّب والهيدر والجرس — الاسم يُعدَّل والبنية (العدد والترتيب والوجهة) لا تُمَسّ.')],
            'setup' => [setting('system.settings_registry.group_catalog_17', 'التنصيب'), setting('system.settings_registry.group_catalog_18', 'خطوات التنصيب ومتطلّباته وحساب المالك الأوّل.')],
            'home' => [setting('system.settings_registry.group_catalog_19', 'الصفحة الرئيسيّة'), setting('system.settings_registry.group_catalog_20', 'البطل والأقسام والميتا وSchema.org.')],
            'engagement' => [setting('system.settings_registry.group_catalog_21', 'الرسائل الإيجابيّة'), setting('system.settings_registry.group_catalog_22', 'المفاجآت والتذاكر وسياقات الظهور.')],
            'ambassadors' => [setting('system.settings_registry.group_catalog_23', 'ألقاب السفراء'), setting('system.settings_registry.group_catalog_24', 'العتبات ولوحة المتصدّرين وإشعار اللقب.')],
            'onboarding' => [setting('system.settings_registry.group_catalog_25', 'التعريف بالمنصّة'), setting('system.settings_registry.group_catalog_26', 'رحلة أوّل دخول.')],
            'account' => [setting('system.settings_registry.group_catalog_27', 'الحساب والخصوصيّة'), setting('system.settings_registry.group_catalog_28', 'مستويات الإظهار والموافقات وتحميل البيانات.')],
            'cv' => [setting('system.settings_registry.group_catalog_29', 'السيرة الذاتيّة'), setting('system.settings_registry.group_catalog_30', 'القوالب وتكلفتها وحدود الأقسام والرابط العامّ.')],
            'learning' => [setting('system.settings_registry.group_catalog_31', 'التعلّم'), setting('system.settings_registry.group_catalog_32', 'الدروس وXP والتقدّم والنصوص التحفيزيّة.')],
            'paths' => [setting('system.settings_registry.group_catalog_33', 'المسارات'), setting('system.settings_registry.group_catalog_34', 'ترتيب المسارات وعرضها.')],
            'courses' => [setting('system.settings_registry.group_catalog_35', 'التدريبات'), setting('system.settings_registry.group_catalog_36', 'الإتاحة والتسجيل والغلاف.')],
            'lessons' => [setting('system.settings_registry.group_catalog_37', 'الدروس'), setting('system.settings_registry.group_catalog_38', 'الفيديو والمرفقات وشروط الإكمال.')],
            'academy' => [setting('system.settings_registry.group_catalog_39', 'الأكاديميّة'), setting('system.settings_registry.group_catalog_40', 'التسجيلات والجلسات.')],
            'availability' => [setting('system.settings_registry.group_catalog_41', 'الإتاحة والتوقيت'), setting('system.settings_registry.group_catalog_42', 'فترات فتح التدريبات والمنطقة الزمنيّة للمتدرّب.')],
            'exams' => [setting('system.settings_registry.group_catalog_43', 'الامتحانات'), setting('system.settings_registry.group_catalog_44', 'المحاولات والزمن والنجاح والرسوب.')],
            'certificates' => [setting('system.settings_registry.group_catalog_45', 'الشهادات'), setting('system.settings_registry.group_catalog_46', 'الإصدار والتصميم والتحقّق العامّ.')],
            'attestations' => [setting('system.settings_registry.group_catalog_47', 'الإفادات'), setting('system.settings_registry.group_catalog_48', 'ورقة الإفادة وأماكن ظهورها.')],
            'library' => [setting('system.settings_registry.group_catalog_49', 'مكتبتي'), setting('system.settings_registry.group_catalog_50', 'الملفّات والعلامة المائيّة والتنزيل.')],
            'reader' => [setting('system.settings_registry.group_catalog_51', 'القارئ'), setting('system.settings_registry.group_catalog_52', 'التصفّح والتظليل والملاحظات.')],
            'internal_library' => [setting('system.settings_registry.group_catalog_53', 'المكتبة الداخليّة'), setting('system.settings_registry.group_catalog_54', 'موارد الفريق ومستويات الوصول.')],
            'media' => [setting('system.settings_registry.group_catalog_55', 'مكتبة الوسائط'), setting('system.settings_registry.group_catalog_56', 'الرفع والأنواع والأحجام.')],
            'images' => [setting('system.settings_registry.group_catalog_57', 'استوديو الصور'), setting('system.settings_registry.group_catalog_58', 'التوليد والكاش والعلامة المائيّة.')],
            'store' => [setting('system.settings_registry.group_catalog_59', 'المتجر'), setting('system.settings_registry.group_catalog_60', 'المنتجات والباقات والكوبونات والشحن وسياسة الاسترجاع.')],
            'wallet' => [setting('system.settings_registry.group_catalog_61', 'المحفظة'), setting('system.settings_registry.group_catalog_62', 'أكواد العملات ومواضع الكسب والصرف.')],
            'gamification_xp' => [setting('system.settings_registry.group_catalog_63', 'XP والمستويات'), setting('system.settings_registry.group_catalog_64', 'مصادر الخبرة وسلّم المستويات.')],
            'gamification_badges' => [setting('system.settings_registry.group_catalog_65', 'الشارات'), setting('system.settings_registry.group_catalog_66', 'شروط المنح والعرض.')],
            'gamification_streaks' => [setting('system.settings_registry.group_catalog_67', 'الستريك ونادي الخامسة'), setting('system.settings_registry.group_catalog_68', 'النافذة والتجميد والمكافأة.')],
            'gamification_leaderboard' => [setting('system.settings_registry.group_catalog_69', 'الليدر بورد'), setting('system.settings_registry.group_catalog_70', 'النطاقات والتجميد وحدّ المشاركين.')],
            'gamification_wars' => [setting('system.settings_registry.group_catalog_71', 'حروب التركيز'), setting('system.settings_registry.group_catalog_72', 'الجولات والفرق والجوائز.')],
            'gamification_celebrations' => [setting('system.settings_registry.group_catalog_73', 'الاحتفالات'), setting('system.settings_registry.group_catalog_74', 'المستويات الثلاثة والصوت والمشاركة.')],
            'gamification_reward_questions' => [setting('system.settings_registry.group_catalog_75', 'أسئلة المكافآت'), setting('system.settings_registry.group_catalog_76', 'بنك أسئلة المكافأة وشروط عرضها.')],
            'celebrations' => [setting('system.settings_registry.group_catalog_77', 'الاحتفالات (نصوص)'), setting('system.settings_registry.group_catalog_78', 'نصوص التهنئة والكونفيتي والإغلاق التلقائيّ.')],
            'streaks' => [setting('system.settings_registry.group_catalog_79', 'الستريك (عرض)'), setting('system.settings_registry.group_catalog_80', 'الخريطة الحراريّة ونافذة نادي الخامسة.')],
            'leaderboard' => [setting('system.settings_registry.group_catalog_81', 'الليدر بورد (عرض)'), setting('system.settings_registry.group_catalog_82', 'عدد الصفوف المعروضة.')],
            'challenges' => [setting('system.settings_registry.group_catalog_83', 'التحديات'), setting('system.settings_registry.group_catalog_84', 'المدد والانضمام والنتائج.')],
            'kudos' => [setting('system.settings_registry.group_catalog_85', 'التقدير'), setting('system.settings_registry.group_catalog_86', 'الحدود اليوميّة ونصوص الشكر.')],
            'rewards' => [setting('system.settings_registry.group_catalog_87', 'المكافآت'), setting('system.settings_registry.group_catalog_88', 'المخزون والصرف والحدود.')],
            'events' => [setting('system.settings_registry.group_catalog_89', 'الفعاليّات'), setting('system.settings_registry.group_catalog_90', 'التسجيل والحضور وكود الحضور والتذكير.')],
            'volunteer' => [setting('system.settings_registry.group_catalog_91', 'التطوّع — عامّ'), setting('system.settings_registry.group_catalog_92', 'القواعد المشتركة لطبقة التطوّع.')],
            'volunteer_page' => [setting('system.settings_registry.group_catalog_93', 'صفحة تطوّع معنا'), setting('system.settings_registry.group_catalog_94', 'العنوان والميثاق والعدّادات وكتل المحتوى.')],
            'volunteer_org' => [setting('system.settings_registry.group_catalog_95', 'الهيكل والسعة'), setting('system.settings_registry.group_catalog_96', 'الكانفاس وعتبات الإشغال والموازن.')],
            'volunteer_rep' => [setting('system.settings_registry.group_catalog_97', 'السمعة (Rep)'), setting('system.settings_registry.group_catalog_98', 'السقوف والتصفير والاعتراض والخمول.')],
            'volunteer_cert' => [setting('system.settings_registry.group_catalog_99', 'شهادات التطوّع'), setting('system.settings_registry.group_catalog_100', 'شروط الإصدار والأنواع والعرض.')],
            'volunteer_offboarding' => [setting('system.settings_registry.group_catalog_101', 'الخروج والعودة'), setting('system.settings_registry.group_catalog_102', 'التصفية والتبريد ومقابلة الخروج.')],
            'volunteer_analytics' => [setting('system.settings_registry.group_catalog_103', 'تحليلات التطوّع'), setting('system.settings_registry.group_catalog_104', 'المدى والمؤشّرات.')],
            'volunteer_honorary' => [setting('system.settings_registry.group_catalog_105', 'المناصب الفخريّة'), setting('system.settings_registry.group_catalog_106', 'الألقاب الفخريّة وشروط منحها وعرضها.')],
            'recruitment' => [setting('system.settings_registry.group_catalog_107', 'التوظيف والترشيح'), setting('system.settings_registry.group_catalog_108', 'الفرز والمقابلات والقبول.')],
            'meetings' => [setting('system.settings_registry.group_catalog_109', 'الاجتماعات'), setting('system.settings_registry.group_catalog_110', 'الحضور والمحضر والمهل.')],
            'workflow' => [setting('system.settings_registry.group_catalog_111', 'المهام والتسليم'), setting('system.settings_registry.group_catalog_112', 'السقوف والتمديد والتعثّر والديدلاين.')],
            'goals' => [setting('system.settings_registry.group_catalog_113', 'الأهداف'), setting('system.settings_registry.group_catalog_114', 'الدورات والقياس والمراجعة.')],
            'performance' => [setting('system.settings_registry.group_catalog_115', 'الأداء'), setting('system.settings_registry.group_catalog_116', 'المؤشّرات ودوريّة التقييم.')],
            'rep' => [setting('system.settings_registry.group_catalog_117', 'السمعة (قواعد عامّة)'), setting('system.settings_registry.group_catalog_118', 'مهل الاعتراض والتصفير الشهريّ.')],
            'offboarding' => [setting('system.settings_registry.group_catalog_119', 'الخروج (قواعد عامّة)'), setting('system.settings_registry.group_catalog_120', 'الخمول والتبريد ومهلة التسليم.')],
            'dashboard' => [setting('system.settings_registry.group_catalog_121', 'لوحة المتدرّب'), setting('system.settings_registry.group_catalog_122', 'الكروت ورادار الإنجازات والعدّادات.')],
            'admin_dashboard' => [setting('system.settings_registry.group_catalog_123', 'لوحة الإدارة'), setting('system.settings_registry.group_catalog_124', 'الكروت والقمع والمدى الافتراضيّ.')],
            'stats' => [setting('system.settings_registry.group_catalog_125', 'الإحصائيّات'), setting('system.settings_registry.group_catalog_126', 'المدى والتصدير وإخفاء التابات.')],
            'notifications' => [setting('system.settings_registry.group_catalog_127', 'الإشعارات'), setting('system.settings_registry.group_catalog_128', 'الجرس والقنوات والتجميع.')],
            'announcements' => [setting('system.settings_registry.group_catalog_129', 'الإعلانات'), setting('system.settings_registry.group_catalog_130', 'الاستهداف والمدّة والأولويّة.')],
            'complaints' => [setting('system.settings_registry.group_catalog_131', 'الشكاوى'), setting('system.settings_registry.group_catalog_132', 'المهل والتصنيف والتصعيد.')],
            'help' => [setting('system.settings_registry.group_catalog_133', 'مركز المساعدة'), setting('system.settings_registry.group_catalog_134', 'الأقسام والبحث.')],
            'articles' => [setting('system.settings_registry.group_catalog_135', 'المقالات'), setting('system.settings_registry.group_catalog_136', 'دورة النشر والمراجعة.')],
            'growth' => [setting('system.settings_registry.group_catalog_137', 'النموّ والدعوات'), setting('system.settings_registry.group_catalog_138', 'الإحالة والمكافأة وحدودها.')],
            'ads' => [setting('system.settings_registry.group_catalog_139', 'الإعلان المدفوع'), setting('system.settings_registry.group_catalog_140', 'البكسلات والجماهير وموافقة التتبّع.')],
            'admin_users' => [setting('system.settings_registry.group_catalog_141', 'إدارة المستخدمين'), setting('system.settings_registry.group_catalog_142', 'الجداول والتابات والأفعال.')],
            'admin_roles' => [setting('system.settings_registry.group_catalog_143', 'الأدوار والصلاحيّات'), setting('system.settings_registry.group_catalog_144', 'نصوص المنع والتصعيد والمجموعة المحميّة.')],
            'admin_approvals' => [setting('system.settings_registry.group_catalog_145', 'الاعتمادات'), setting('system.settings_registry.group_catalog_146', 'قوائم الانتظار والمهل.')],
            'admin_segments' => [setting('system.settings_registry.group_catalog_147', 'الشرائح'), setting('system.settings_registry.group_catalog_148', 'تعريف الشرائح واستخدامها.')],
            'admin_content' => [setting('system.settings_registry.group_catalog_149', 'إدارة المحتوى'), setting('system.settings_registry.group_catalog_150', 'سجلّ التدقيق وحدوده.')],
            'security' => [setting('system.settings_registry.group_catalog_151', 'الأمان'), setting('system.settings_registry.group_catalog_152', 'كلمات المرور والجلسات والمحاولات.')],
            'features' => [setting('system.settings_registry.group_catalog_153', 'مفاتيح المزايا'), setting('system.settings_registry.group_catalog_154', 'تشغيل وإطفاء المزايا.')],
            'countries' => [setting('system.settings_registry.group_catalog_155', 'الدول والمحافظات'), setting('system.settings_registry.group_catalog_156', 'المصدر وسياسة الدمج.')],
            'maintenance' => [setting('system.settings_registry.group_catalog_157', 'وضع الصيانة'), setting('system.settings_registry.group_catalog_158', 'المدّة والرسالة وتجميد المهل.')],
            'updates' => [setting('system.settings_registry.group_catalog_159', 'التحديثات'), setting('system.settings_registry.group_catalog_160', 'الترقية والاسترجاع.')],
            'backups' => [setting('system.settings_registry.group_catalog_161', 'النسخ الاحتياطيّ'), setting('system.settings_registry.group_catalog_162', 'الجدولة والاحتفاظ.')],
            'finance' => [setting('system.settings_registry.group_catalog_163', '🔒 الماليّات'), setting('system.settings_registry.group_catalog_164', 'الأسعار والعمولات والسحب والاسترجاع — لمالك المنصّة وحده.')],
        ];
    }

    /** عنوان المجموعة العربيّ — والمجموعة المجهولة تُعرَض بمفتاحها بلا كسر */
    public function groupLabel(string $group): string
    {
        return $this->groupCatalog()[$group][0] ?? $group;
    }

    public function groupHint(string $group): string
    {
        return $this->groupCatalog()[$group][1] ?? setting('system.settings_registry.group_hint_1', 'مجموعة بلا وصف بعد — أضِف وصفها في `groupCatalog()`.');
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
                'label' => setting('system.settings_registry.tabs_for_1', '🔒 الماليّات'),
                'groups' => ['finance'],
                'hint' => setting('system.settings_registry.tabs_for_2', 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ — مجموعة محميّة.'),
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
                $tabLabel = $tabs[$tabKey]['label'] ?? $this->tabs()[$tabKey]['label'] ?? setting('system.settings_registry.search_1', 'متنوّعات');

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
            return ['saved' => false, 'message' => setting('system.settings_registry.save_1', 'الإعداد ده لمالك المنصّة وحده.'), 'value' => $setting->value];
        }

        if ($this->isDisabled($setting)) {
            return ['saved' => false, 'message' => setting('system.settings_registry.save_2', 'فعّل الميزة أوّلًا.'), 'value' => $setting->value];
        }

        $normalized = $this->normalize($setting, $value);

        if ($normalized === null) {
            return [
                'saved' => false,
                'message' => strtr(setting('system.settings_registry.save_3', 'القيمة خارج النطاق — :p1'), [':p1' => (string) ($this->rangeHint($setting))]),
                'value' => $setting->value,
            ];
        }

        $old = $setting->value;

        if ($old === $normalized) {
            return ['saved' => true, 'message' => setting('system.settings_registry.save_4', 'تم الحفظ ✓'), 'value' => $normalized];
        }

        $setting->update(['value' => $normalized]);
        $this->audit($setting, $old, $normalized, $actor, 'settings.update');
        $this->flush();

        return ['saved' => true, 'message' => setting('system.settings_registry.save_5', 'تم الحفظ ✓'), 'value' => $normalized];
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
            return ['saved' => false, 'message' => setting('system.settings_registry.undo_1', 'مفيش تغيير سابق نرجع له.'), 'value' => $setting->value];
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
            str_contains($setting->key, 'hours') => strtr(setting('system.settings_registry.live_example_1', ':p1 يوم'), [':p1' => (string) (rtrim(rtrim(number_format($value / 24, 2, '.', ''), '0'), '.'))]),
            str_contains($setting->key, 'minutes') => strtr(setting('system.settings_registry.live_example_2', ':p1 ساعة'), [':p1' => (string) (rtrim(rtrim(number_format($value / 60, 2, '.', ''), '0'), '.'))]),
            str_contains($setting->key, 'percent') => strtr(setting('system.settings_registry.live_example_3', 'من كلّ 100 ⟵ :p1'), [':p1' => (string) ($value)]),
            default => null,
        };
    }

    /**
     * النطاق المسموح — **للأرقام وحدها** (12.7-ج · 2.13).
     *
     * كان يُطبَع تحت كلّ إعدادٍ أيًّا كان نوعه، فيظهر تحت «مصدر بيانات الدول»
     * سطرُ «من 0 إلى 1000000» — نطاقٌ لا معنى له لنصّ.
     */
    public function rangeHint(Setting $setting): ?string
    {
        if ($setting->type !== 'number') {
            return null;
        }

        [$min, $max] = $this->range($setting);

        return strtr(setting('system.settings_registry.range_hint_1', 'من :p1 إلى :p2'), [':p1' => (string) ($min), ':p2' => (string) ($max)]);
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

    /**
     * ⭐ النطاق المسموح **من إعدادٍ لا من اسم المفتاح** (2.13).
     *
     * كان يُشتقّ بـ`str_contains($key, 'hours')` وما شابه، ثمّ يرتدّ إلى
     * `[0, 1000000]` محروقة. واشتقاق القاعدة من الاسم يرفض قيمةً مشروعة بصمت
     * (مفتاحٌ اسمه فيه `days` وقيمته الصحيحة صفر مثلًا)، وهو عين ما تمنعه 2.13.
     * فالخريطة الآن مفتاحُ إعداداتٍ يحرّره المالك: `بادئة => [أدنى, أقصى]`،
     * وأدقّ بادئةٍ مطابقة هي الحاكمة، والافتراضيّ العامّ منها كذلك.
     *
     * @return array{0:float,1:float}
     */
    private function range(Setting $setting): array
    {
        $map = setting('ux.settings_ranges', []);
        $map = is_array($map) ? $map : [];

        $best = null;
        $bestLength = -1;

        foreach ($map as $prefix => $bounds) {
            $prefix = (string) $prefix;

            if ($prefix === '*' || ! is_array($bounds) || count($bounds) < 2) {
                continue;
            }

            if (str_contains($setting->key, $prefix) && mb_strlen($prefix) > $bestLength) {
                $best = $bounds;
                $bestLength = mb_strlen($prefix);
            }
        }

        $bounds = $best ?? (is_array($map['*'] ?? null) ? $map['*'] : [0, 1000000]);

        return [(float) $bounds[0], (float) $bounds[1]];
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
