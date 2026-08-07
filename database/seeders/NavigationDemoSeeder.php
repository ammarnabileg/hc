<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * **لافتات التنقّل** — سايد بار الإدارة (12.0) وسايد بار لوحة التطوّع (13.4-ح)
 * وسايد بار المتدرّب (24.5-أ) والهيدر والجرس وويدجت الدعوات.
 *
 * ⭐ **اللافتة تُعدَّل — والبنية لا تُمَسّ.** (سجلّ القرارات 2026-08-04 · 2.13-ب)
 *
 * 2.13-ب تنصّ أنّ «كلّ رقم أو نصّ مذكور في الدستور يُعتبَر **قيمة افتراضيّة
 * قابلة للتعديل** من اللوحة — **إلّا ما نُصَّ صراحةً أنّه ثابت نظاميّ (منطق
 * أمنيّ أو حدود منع تلاعب)**»، وأسماءُ القوائم ليست منه. فما في هذا الملفّ
 * **افتراضيّاتٌ** نصُّها هو المنصوص في 12.0 و13.4-ح **حرفًا بحرف**، ويملك
 * المالك تغييرها من لوحته بلا مخالفة.
 *
 * ⛔ **وما لا يُمَسّ** هو **بنية** الخريطتين: **عدد البنود · ترتيبها · وجهتها
 * (اسم المسار) · ومَن يراها (الصلاحيّة)**. فمَن غيّر لافتةً لم يخالف، ومَن حذف
 * بندًا أو أزاحه أو حوّل وجهته أسقط الحارس. والحارس يقيس **الوجهة لا اللافتة**:
 *   · `tests/Feature/Ui/AdminLayoutGuardTest.php`
 *   · `tests/Feature/Ui/VolunteerLayoutGuardTest.php`
 *   · `tests/Feature/Scope/SidebarEntryPointsTest.php`
 *
 * ولذلك **لا تُكتَب هذه المفاتيح في الاختبارات**: اختبارٌ يقرأ اللافتة يعود
 * حارسًا للنصّ لا للبنية، ويسقط في وجه مالكٍ مارس حقًّا يملكه.
 */
class NavigationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->screenTextSettings();

        Cache::forget('settings');
    }

    /**
     * لافتات التنقّل — الوحدة **جملةٌ كاملة** كما يقرؤها المستخدم (2.13-أ).
     *
     * ⛔ وما **لم** يُنقَل عمدًا من هذه القوالب:
     *   · الأيقونات (`icon="🏠"`) — رموزٌ لا نصّ، ولها بابها في «السايد بار» (5085).
     *   · `partials/sidebar-drawer.blade.php`: أنماط CSS وتعليقات السكربت — لا تُعرَض.
     *   · `partials/floating.blade.php`: بلا نصٍّ ظاهر أصلًا.
     */
    public function screenTextSettings(): void
    {
        $rows = [
            // سايد بار لوحة التطوّع (13.4-ح) — ثلاثة عشر بندًا بترتيبها
            ['nav.volunteer.item_overview_journey', 'nav', 'سايد بار التطوّع: رحلتي في التطوّع', 'رحلتي في التطوّع'],
            ['nav.volunteer.item_overview_report', 'nav', 'سايد بار التطوّع: تقريري الأسبوعيّ', 'تقريري الأسبوعيّ'],
            ['nav.volunteer.item_overview_calendar', 'nav', 'سايد بار التطوّع: تقويم نشاطي', 'تقويم نشاطي'],
            ['nav.volunteer.item_tasks_mine', 'nav', 'سايد بار التطوّع: مهامّي', 'مهامّي'],
            ['nav.volunteer.item_tasks_board', 'nav', 'سايد بار التطوّع: لوحة المهام العامّة', 'لوحة المهام العامّة'],
            ['nav.volunteer.item_tasks_contributions', 'nav', 'سايد بار التطوّع: مساهماتي', 'مساهماتي'],
            ['nav.volunteer.item_tasks_reviews', 'nav', 'سايد بار التطوّع: بانتظار مراجعتي', 'بانتظار مراجعتي'],
            ['nav.volunteer.item_goals_index', 'nav', 'سايد بار التطوّع: الأهداف والمَعالِم', 'الأهداف والمَعالِم'],
            ['nav.volunteer.item_goals_build', 'nav', 'سايد بار التطوّع: بناء الأهداف', 'بناء الأهداف'],
            ['nav.volunteer.item_goals_launch', 'nav', 'سايد بار التطوّع: إطلاق الهدف', 'إطلاق الهدف'],
            ['nav.volunteer.item_goals_packages', 'nav', 'سايد بار التطوّع: حزم العمل وبنودها', 'حزم العمل وبنودها'],
            ['nav.volunteer.item_goals_project', 'nav', 'سايد بار التطوّع: المشروع التشغيليّ', 'المشروع التشغيليّ'],
            ['nav.volunteer.item_goals_recurring', 'nav', 'سايد بار التطوّع: البنود المتكرّرة', 'البنود المتكرّرة'],
            ['nav.volunteer.item_performance_vxp', 'nav', 'سايد بار التطوّع: VXP وترتيبي', 'VXP وترتيبي'],
            ['nav.volunteer.item_performance_rep', 'nav', 'سايد بار التطوّع: درجة الالتزام (Rep)', 'درجة الالتزام (Rep)'],
            ['nav.volunteer.item_performance_champion', 'nav', 'سايد بار التطوّع: مشرف الشهر', 'مشرف الشهر'],
            ['nav.volunteer.item_performance_evaluations', 'nav', 'سايد بار التطوّع: تقييماتي (مؤشّر القيادة)', 'تقييماتي (مؤشّر القيادة)'],
            ['nav.volunteer.item_meetings_index', 'nav', 'سايد بار التطوّع: القادمة والمنتهية', 'القادمة والمنتهية'],
            ['nav.volunteer.item_meetings_attendance', 'nav', 'سايد بار التطوّع: حضوري والمحاضر', 'حضوري والمحاضر'],
            ['nav.volunteer.item_transactions_index', 'nav', 'سايد بار التطوّع: معاملاتي (Rep/VXP)', 'معاملاتي (Rep/VXP)'],
            ['nav.volunteer.item_transactions_objections', 'nav', 'سايد بار التطوّع: اعتراضاتي', 'اعتراضاتي'],
            ['nav.volunteer.item_department_members', 'nav', 'سايد بار التطوّع: الأعضاء والبوزشنز', 'الأعضاء والبوزشنز'],
            ['nav.volunteer.item_department_org', 'nav', 'سايد بار التطوّع: الهيكل التنظيميّ', 'الهيكل التنظيميّ'],
            ['nav.volunteer.item_department_health', 'nav', 'سايد بار التطوّع: صحّة القسم', 'صحّة القسم'],
            ['nav.volunteer.item_department_capacity', 'nav', 'سايد بار التطوّع: السعة والأحمال', 'السعة والأحمال'],
            ['nav.volunteer.item_escalations_index', 'nav', 'سايد بار التطوّع: يحتاج قرارك', 'يحتاج قرارك'],
            ['nav.volunteer.item_escalations_objections', 'nav', 'سايد بار التطوّع: الاعتراضات المصعَّدة', 'الاعتراضات المصعَّدة'],
            ['nav.volunteer.item_escalations_arbitrations', 'nav', 'سايد بار التطوّع: التحكيمات', 'التحكيمات'],
            ['nav.volunteer.item_academy_paths', 'nav', 'سايد بار التطوّع: التدريبات', 'التدريبات'],
            ['nav.volunteer.item_academy_recordings', 'nav', 'سايد بار التطوّع: التسجيلات', 'التسجيلات'],
            ['nav.volunteer.item_recognition_kudos', 'nav', 'سايد بار التطوّع: Kudos', 'Kudos'],
            ['nav.volunteer.item_recognition_wall', 'nav', 'سايد بار التطوّع: حائط الشكر (نادي +9.5)', 'حائط الشكر (نادي +9.5)'],
            ['nav.volunteer.item_recruitment_candidates', 'nav', 'سايد بار التطوّع: المرشّحون (كانبان)', 'المرشّحون (كانبان)'],
            ['nav.volunteer.item_recruitment_interviews', 'nav', 'سايد بار التطوّع: المقابلات والـScorecards', 'المقابلات والـScorecards'],
            ['nav.volunteer.item_recruitment_placement', 'nav', 'سايد بار التطوّع: القوائم والتسكين', 'القوائم والتسكين'],
            ['nav.volunteer.no_entity', 'nav', 'سايد بار التطوّع: بلا كيان', 'بلا كيان'],
            ['nav.volunteer.group_overview', 'nav', 'سايد بار التطوّع: نظرة عامّة', 'نظرة عامّة'],
            ['nav.volunteer.group_tasks', 'nav', 'سايد بار التطوّع: المهام', 'المهام'],
            ['nav.volunteer.group_goals', 'nav', 'سايد بار التطوّع: المشاريع والأهداف', 'المشاريع والأهداف'],
            ['nav.volunteer.group_performance', 'nav', 'سايد بار التطوّع: الأداء', 'الأداء'],
            ['nav.volunteer.group_meetings', 'nav', 'سايد بار التطوّع: الاجتماعات', 'الاجتماعات'],
            ['nav.volunteer.group_transactions', 'nav', 'سايد بار التطوّع: المعاملات', 'المعاملات'],
            ['nav.volunteer.group_department', 'nav', 'سايد بار التطوّع: قسمي', 'قسمي'],
            ['nav.volunteer.group_escalations', 'nav', 'سايد بار التطوّع: التصعيدات', 'التصعيدات'],
            ['nav.volunteer.group_academy', 'nav', 'سايد بار التطوّع: الأكاديمية', 'الأكاديمية'],
            ['nav.volunteer.item_library', 'nav', 'سايد بار التطوّع: المكتبة الداخليّة', 'المكتبة الداخليّة'],
            ['nav.volunteer.group_recognition', 'nav', 'سايد بار التطوّع: التقدير', 'التقدير'],
            ['nav.volunteer.group_recruitment', 'nav', 'سايد بار التطوّع: التوظيف', 'التوظيف'],
            ['nav.volunteer.item_notifications', 'nav', 'سايد بار التطوّع: إشعارات التطوّع', 'إشعارات التطوّع'],
            ['nav.volunteer.back_to_dashboard', 'nav', 'سايد بار التطوّع: رجوع للرئيسيّة', 'رجوع للرئيسيّة'],

            // سايد بار لوحة الإدارة (12.0) — اثنا عشر قسمًا و«الإعدادات والنظام» آخرها
            ['nav.admin.group_users', 'nav', 'سايد بار الإدارة: إدارة المستخدمين', 'إدارة المستخدمين'],
            ['nav.admin.item_users_list', 'nav', 'سايد بار الإدارة: قائمة المستخدمين', 'قائمة المستخدمين'],
            ['nav.admin.item_users_approvals', 'nav', 'سايد بار الإدارة: طلبات الاعتماد', 'طلبات الاعتماد'],
            ['nav.admin.item_users_segments', 'nav', 'سايد بار الإدارة: شرائح الجمهور', 'شرائح الجمهور'],
            ['nav.admin.item_users_roles', 'nav', 'سايد بار الإدارة: الأدوار والصلاحيّات', 'الأدوار والصلاحيّات'],
            ['nav.admin.group_training', 'nav', 'سايد بار الإدارة: إدارة التدريب', 'إدارة التدريب'],
            ['nav.admin.item_training_paths', 'nav', 'سايد بار الإدارة: المسارات', 'المسارات'],
            ['nav.admin.item_training_courses', 'nav', 'سايد بار الإدارة: التدريبات', 'التدريبات'],
            ['nav.admin.item_training_question_bank', 'nav', 'سايد بار الإدارة: بنك الأسئلة والامتحانات', 'بنك الأسئلة والامتحانات'],
            ['nav.admin.item_training_media', 'nav', 'سايد بار الإدارة: مكتبة الوسائط', 'مكتبة الوسائط'],
            ['nav.admin.item_training_settings', 'nav', 'سايد بار الإدارة: إعدادات التعلّم', 'إعدادات التعلّم'],
            ['nav.admin.item_training_availability', 'nav', 'سايد بار الإدارة: الإتاحة والتوقيت', 'الإتاحة والتوقيت'],
            ['nav.admin.group_certificates', 'nav', 'سايد بار الإدارة: إدارة الشهادات', 'إدارة الشهادات'],
            ['nav.admin.item_certificates_accreditations', 'nav', 'سايد بار الإدارة: الاعتمادات', 'الاعتمادات'],
            ['nav.admin.item_certificates_types', 'nav', 'سايد بار الإدارة: الأنواع والقوالب', 'الأنواع والقوالب'],
            ['nav.admin.item_certificates_issue', 'nav', 'سايد بار الإدارة: إصدار شهادة', 'إصدار شهادة'],
            ['nav.admin.item_certificates_ledger', 'nav', 'سايد بار الإدارة: سجلّ الصادر', 'سجلّ الصادر'],
            ['nav.admin.item_certificates_verify', 'nav', 'سايد بار الإدارة: صفحة التحقّق', 'صفحة التحقّق'],
            ['nav.admin.group_volunteer', 'nav', 'سايد بار الإدارة: إدارة التطوّع', 'إدارة التطوّع'],
            ['nav.admin.item_volunteer_central', 'nav', 'سايد بار الإدارة: الإدارة المركزيّة', 'الإدارة المركزيّة'],
            ['nav.admin.item_volunteer_recruitment', 'nav', 'سايد بار الإدارة: التوظيف والمرشّحون', 'التوظيف والمرشّحون'],
            ['nav.admin.item_volunteer_org', 'nav', 'سايد بار الإدارة: الهيكل والبوزشنز والسعة', 'الهيكل والبوزشنز والسعة'],
            ['nav.admin.item_volunteer_meetings', 'nav', 'سايد بار الإدارة: الاجتماعات', 'الاجتماعات'],
            ['nav.admin.item_volunteer_certificates', 'nav', 'سايد بار الإدارة: شهادات التطوّع', 'شهادات التطوّع'],
            ['nav.admin.item_volunteer_analytics', 'nav', 'سايد بار الإدارة: تحليلات التطوّع', 'تحليلات التطوّع'],
            ['nav.admin.item_volunteer_capacity', 'nav', 'سايد بار الإدارة: تقرير السعة', 'تقرير السعة'],
            ['nav.admin.item_volunteer_rep', 'nav', 'سايد بار الإدارة: درجة الالتزام (Rep)', 'درجة الالتزام (Rep)'],
            ['nav.admin.item_volunteer_delegations', 'nav', 'سايد بار الإدارة: الغيابات والتفويض', 'الغيابات والتفويض'],
            ['nav.admin.item_volunteer_task_types', 'nav', 'سايد بار الإدارة: أنواع المهامّ', 'أنواع المهامّ'],
            ['nav.admin.item_volunteer_scorecard_criteria', 'nav', 'سايد بار الإدارة: معايير المقابلة', 'معايير المقابلة'],
            ['nav.admin.item_volunteer_leadership_criteria', 'nav', 'سايد بار الإدارة: معايير مؤشّر القيادة', 'معايير مؤشّر القيادة'],
            ['nav.admin.item_volunteer_offboarding', 'nav', 'سايد بار الإدارة: الخروج والعودة', 'الخروج والعودة'],
            ['nav.admin.group_gamification', 'nav', 'سايد بار الإدارة: التلعيب والتحديات', 'التلعيب والتحديات'],
            ['nav.admin.item_gamification_xp', 'nav', 'سايد بار الإدارة: XP والتذاكر', 'XP والتذاكر'],
            ['nav.admin.item_gamification_streaks', 'nav', 'سايد بار الإدارة: الستريك ونادي الخامسة', 'الستريك ونادي الخامسة'],
            ['nav.admin.item_gamification_leaderboard', 'nav', 'سايد بار الإدارة: الليدر بورد', 'الليدر بورد'],
            ['nav.admin.item_gamification_badges', 'nav', 'سايد بار الإدارة: الشارات والإنجازات', 'الشارات والإنجازات'],
            ['nav.admin.item_gamification_referrals', 'nav', 'سايد بار الإدارة: الريفيرال والسفراء', 'الريفيرال والسفراء'],
            ['nav.admin.item_gamification_positive', 'nav', 'سايد بار الإدارة: الرسائل الإيجابيّة', 'الرسائل الإيجابيّة'],
            ['nav.admin.item_gamification_celebrations', 'nav', 'سايد بار الإدارة: الاحتفالات', 'الاحتفالات'],
            ['nav.admin.item_gamification_reward_questions', 'nav', 'سايد بار الإدارة: أسئلة المكافآت', 'أسئلة المكافآت'],
            ['nav.admin.item_gamification_wars_bank', 'nav', 'سايد بار الإدارة: بنك أسئلة الحروب', 'بنك أسئلة الحروب'],
            ['nav.admin.item_gamification_wars_settings', 'nav', 'سايد بار الإدارة: إعدادات الحروب', 'إعدادات الحروب'],
            ['nav.admin.group_store', 'nav', 'سايد بار الإدارة: المتجر والماليّات', 'المتجر والماليّات'],
            ['nav.admin.item_store_products', 'nav', 'سايد بار الإدارة: المنتجات والتصنيفات', 'المنتجات والتصنيفات'],
            ['nav.admin.item_store_bundles', 'nav', 'سايد بار الإدارة: البندلز', 'البندلز'],
            ['nav.admin.item_store_coupons', 'nav', 'سايد بار الإدارة: الكوبونات وOrder-bump', 'الكوبونات وOrder-bump'],
            ['nav.admin.item_store_orders', 'nav', 'سايد بار الإدارة: الطلبات والفواتير', 'الطلبات والفواتير'],
            ['nav.admin.item_store_library', 'nav', 'سايد بار الإدارة: المكتبة الرقميّة والحماية', 'المكتبة الرقميّة والحماية'],
            ['nav.admin.item_store_topups', 'nav', 'سايد بار الإدارة: طلبات الشحن', 'طلبات الشحن'],
            ['nav.admin.item_store_finance', 'nav', 'سايد بار الإدارة: 🔒 الماليّات', '🔒 الماليّات'],
            ['nav.admin.item_store_rates', 'nav', 'سايد بار الإدارة: 🔒 أسعار الصرف', '🔒 أسعار الصرف'],
            ['nav.admin.item_store_finance_audit', 'nav', 'سايد بار الإدارة: 🔒 سجلّ الماليّات', '🔒 سجلّ الماليّات'],
            ['nav.admin.group_rewards', 'nav', 'سايد بار الإدارة: إدارة المكافآت', 'إدارة المكافآت'],
            ['nav.admin.item_rewards_index', 'nav', 'سايد بار الإدارة: إدارة المكافآت', 'إدارة المكافآت'],
            ['nav.admin.group_events', 'nav', 'سايد بار الإدارة: الفعاليّات', 'الفعاليّات'],
            ['nav.admin.item_events_index', 'nav', 'سايد بار الإدارة: الفعاليّات', 'الفعاليّات'],
            ['nav.admin.group_guidance', 'nav', 'سايد بار الإدارة: التوجيه والدعم', 'التوجيه والدعم'],
            ['nav.admin.item_guidance_announcements', 'nav', 'سايد بار الإدارة: التعليمات', 'التعليمات'],
            ['nav.admin.item_guidance_notifications', 'nav', 'سايد بار الإدارة: الإشعارات', 'الإشعارات'],
            ['nav.admin.item_guidance_help', 'nav', 'سايد بار الإدارة: دليل المستخدم', 'دليل المستخدم'],
            ['nav.admin.item_guidance_complaints', 'nav', 'سايد بار الإدارة: الشكاوى والمقترحات', 'الشكاوى والمقترحات'],
            ['nav.admin.item_guidance_articles', 'nav', 'سايد بار الإدارة: المقالات', 'المقالات'],
            ['nav.admin.item_guidance_ads', 'nav', 'سايد بار الإدارة: الإعلان المدفوع', 'الإعلان المدفوع'],
            ['nav.admin.item_guidance_growth', 'nav', 'سايد بار الإدارة: حلقات النموّ', 'حلقات النموّ'],
            ['nav.admin.group_stats', 'nav', 'سايد بار الإدارة: الإحصائيّات', 'الإحصائيّات'],
            ['nav.admin.item_stats_users', 'nav', 'سايد بار الإدارة: المستخدمون', 'المستخدمون'],
            ['nav.admin.item_stats_sales', 'nav', 'سايد بار الإدارة: المبيعات', 'المبيعات'],
            ['nav.admin.item_stats_training', 'nav', 'سايد بار الإدارة: التدريبات', 'التدريبات'],
            ['nav.admin.item_stats_engagement', 'nav', 'سايد بار الإدارة: التفاعل', 'التفاعل'],
            ['nav.admin.item_stats_attendance', 'nav', 'سايد بار الإدارة: الحضور', 'الحضور'],
            ['nav.admin.item_stats_wars', 'nav', 'سايد بار الإدارة: الحروب', 'الحروب'],
            ['nav.admin.item_stats_volunteer', 'nav', 'سايد بار الإدارة: التطوّع', 'التطوّع'],
            ['nav.admin.item_stats_certificates', 'nav', 'سايد بار الإدارة: الشهادات', 'الشهادات'],
            ['nav.admin.item_stats_schedules', 'nav', 'سايد بار الإدارة: التقارير المجدولة', 'التقارير المجدولة'],
            ['nav.admin.item_stats_acquisition', 'nav', 'سايد بار الإدارة: مصادر الاكتساب', 'مصادر الاكتساب'],
            ['nav.admin.item_settings_platform', 'nav', 'سايد بار الإدارة: إعدادات المنصّة', 'إعدادات المنصّة'],
            ['nav.admin.item_settings_identity', 'nav', 'سايد بار الإدارة: الهويّة والمظهر', 'الهويّة والمظهر'],
            ['nav.admin.item_settings_onboarding', 'nav', 'سايد بار الإدارة: محتوى الـOnboarding', 'محتوى الـOnboarding'],
            ['nav.admin.item_settings_cv', 'nav', 'سايد بار الإدارة: قوالب الـCV', 'قوالب الـCV'],
            ['nav.admin.item_settings_security', 'nav', 'سايد بار الإدارة: الأمان والخصوصيّة', 'الأمان والخصوصيّة'],
            ['nav.admin.item_settings_features', 'nav', 'سايد بار الإدارة: مفاتيح المزايا', 'مفاتيح المزايا'],
            ['nav.admin.item_settings_countries', 'nav', 'سايد بار الإدارة: بيانات الدول', 'بيانات الدول'],
            ['nav.admin.item_settings_maintenance', 'nav', 'سايد بار الإدارة: وضع الصيانة', 'وضع الصيانة'],
            ['nav.admin.item_settings_updates', 'nav', 'سايد بار الإدارة: التحديثات والترحيل', 'التحديثات والترحيل'],
            ['nav.admin.item_settings_system', 'nav', 'سايد بار الإدارة: النسخ الاحتياطيّ وصحّة النظام', 'النسخ الاحتياطيّ وصحّة النظام'],
            ['nav.admin.item_settings_audit', 'nav', 'سايد بار الإدارة: سجلّ التدقيق', 'سجلّ التدقيق'],
            ['nav.admin.item_settings_studio', 'nav', 'سايد بار الإدارة: استوديو الصور', 'استوديو الصور'],
            ['nav.admin.panel_title', 'nav', 'سايد بار الإدارة: لوحة الإدارة', 'لوحة الإدارة'],
            ['nav.admin.owner_badge', 'nav', 'سايد بار الإدارة: مالك المنصّة', 'مالك المنصّة'],
            ['nav.admin.item_dashboard', 'nav', 'سايد بار الإدارة: لوحة القيادة', 'لوحة القيادة'],
            ['nav.admin.group_settings', 'nav', 'سايد بار الإدارة: الإعدادات والنظام', 'الإعدادات والنظام'],
            ['nav.admin.back_to_account', 'nav', 'سايد بار الإدارة: رجوع لحسابي', 'رجوع لحسابي'],

            // سايد بار المتدرّب (24.5-أ)
            ['nav.trainee.xp_progress_toward', 'nav', 'سايد بار المتدرّب: ٪ نحو', '٪ نحو'],
            ['nav.trainee.search_placeholder', 'nav', 'سايد بار المتدرّب: ابحث…', 'ابحث…'],
            ['nav.trainee.search_aria', 'nav', 'سايد بار المتدرّب: بحث سريع', 'بحث سريع'],
            ['nav.trainee.search_submit', 'nav', 'سايد بار المتدرّب: إبحث', 'إبحث'],
            ['nav.trainee.pinned_title', 'nav', 'سايد بار المتدرّب: 📌 المثبَّتة', '📌 المثبَّتة'],
            ['nav.trainee.unpin_aria', 'nav', 'سايد بار المتدرّب: فكّ تثبيت', 'فكّ تثبيت'],
            ['nav.trainee.unpin_title', 'nav', 'سايد بار المتدرّب: فكّ التثبيت', 'فكّ التثبيت'],
            ['nav.trainee.item_dashboard', 'nav', 'سايد بار المتدرّب: الرئيسيّة', 'الرئيسيّة'],
            ['nav.trainee.item_announcements', 'nav', 'سايد بار المتدرّب: التعليمات', 'التعليمات'],
            ['nav.trainee.item_volunteer_panel', 'nav', 'سايد بار المتدرّب: لوحة التطوّع', 'لوحة التطوّع'],
            ['nav.trainee.group_learning', 'nav', 'سايد بار المتدرّب: تعلّمي', 'تعلّمي'],
            ['nav.trainee.group_library', 'nav', 'سايد بار المتدرّب: مكتبتي', 'مكتبتي'],
            ['nav.trainee.group_store', 'nav', 'سايد بار المتدرّب: المتجر', 'المتجر'],
            ['nav.trainee.group_wallet', 'nav', 'سايد بار المتدرّب: المحفظة', 'المحفظة'],
            ['nav.trainee.group_challenges', 'nav', 'سايد بار المتدرّب: التحديات', 'التحديات'],
            ['nav.trainee.group_achievements', 'nav', 'سايد بار المتدرّب: إنجازاتي', 'إنجازاتي'],
            ['nav.trainee.item_events', 'nav', 'سايد بار المتدرّب: الفعاليّات', 'الفعاليّات'],
            ['nav.trainee.group_experience', 'nav', 'سايد بار المتدرّب: خبراتي', 'خبراتي'],
            ['nav.trainee.group_referral', 'nav', 'سايد بار المتدرّب: ادعُ أصدقاءك', 'ادعُ أصدقاءك'],
            ['nav.trainee.item_articles', 'nav', 'سايد بار المتدرّب: المقالات', 'المقالات'],
            ['nav.trainee.group_support', 'nav', 'سايد بار المتدرّب: الدعم', 'الدعم'],
            ['nav.trainee.item_volunteering_landing', 'nav', 'سايد بار المتدرّب: تطوّع معنا', 'تطوّع معنا'],
            ['nav.trainee.group_account', 'nav', 'سايد بار المتدرّب: حسابي', 'حسابي'],
            ['nav.trainee.item_admin_panel', 'nav', 'سايد بار المتدرّب: لوحة الإدارة', 'لوحة الإدارة'],
            ['nav.trainee.item_learning_courses', 'nav', 'سايد بار المتدرّب: تدريباتي', 'تدريباتي'],
            ['nav.trainee.item_learning_paths', 'nav', 'سايد بار المتدرّب: المسارات', 'المسارات'],
            ['nav.trainee.item_learning_certificates', 'nav', 'سايد بار المتدرّب: شهاداتي', 'شهاداتي'],
            ['nav.trainee.item_library_all', 'nav', 'سايد بار المتدرّب: الكلّ', 'الكلّ'],
            ['nav.trainee.item_store_products', 'nav', 'سايد بار المتدرّب: المنتجات', 'المنتجات'],
            ['nav.trainee.item_store_bundles', 'nav', 'سايد بار المتدرّب: البندلز', 'البندلز'],
            ['nav.trainee.item_wallet_index', 'nav', 'سايد بار المتدرّب: رصيدي وشحن', 'رصيدي وشحن'],
            ['nav.trainee.item_wallet_tickets', 'nav', 'سايد بار المتدرّب: التذاكر', 'التذاكر'],
            ['nav.trainee.item_wallet_transactions', 'nav', 'سايد بار المتدرّب: المعاملات والفواتير', 'المعاملات والفواتير'],
            ['nav.trainee.item_challenges_index', 'nav', 'سايد بار المتدرّب: المتاحة', 'المتاحة'],
            ['nav.trainee.item_challenges_mine', 'nav', 'سايد بار المتدرّب: تحدّياتي', 'تحدّياتي'],
            ['nav.trainee.item_challenges_leaderboard', 'nav', 'سايد بار المتدرّب: لوحة الأبطال', 'لوحة الأبطال'],
            ['nav.trainee.item_achievements_leaderboard', 'nav', 'سايد بار المتدرّب: الليدر بورد', 'الليدر بورد'],
            ['nav.trainee.item_achievements_badges', 'nav', 'سايد بار المتدرّب: الشارات', 'الشارات'],
            ['nav.trainee.item_achievements_streak', 'nav', 'سايد بار المتدرّب: الستريك ونادي الخامسة', 'الستريك ونادي الخامسة'],
            ['nav.trainee.item_experience_cv', 'nav', 'سايد بار المتدرّب: السيرة الذاتيّة', 'السيرة الذاتيّة'],
            ['nav.trainee.item_experience_attestations', 'nav', 'سايد بار المتدرّب: الإفادة', 'الإفادة'],
            ['nav.trainee.item_referral_link', 'nav', 'سايد بار المتدرّب: رابط دعوتي', 'رابط دعوتي'],
            ['nav.trainee.item_referral_board', 'nav', 'سايد بار المتدرّب: متصدّرو الدعوات', 'متصدّرو الدعوات'],
            ['nav.trainee.item_referral_kit', 'nav', 'سايد بار المتدرّب: حزمة المحتوى', 'حزمة المحتوى'],
            ['nav.trainee.item_support_complaints', 'nav', 'سايد بار المتدرّب: الشكاوى والمقترحات', 'الشكاوى والمقترحات'],
            ['nav.trainee.item_support_help', 'nav', 'سايد بار المتدرّب: دليل المستخدم', 'دليل المستخدم'],
            ['nav.trainee.item_account_profile', 'nav', 'سايد بار المتدرّب: بروفايلي', 'بروفايلي'],
            ['nav.trainee.item_account_settings', 'nav', 'سايد بار المتدرّب: الإعدادات', 'الإعدادات'],
            ['nav.trainee.item_account_privacy', 'nav', 'سايد بار المتدرّب: الخصوصيّة والأمان', 'الخصوصيّة والأمان'],

            // الهيدر · الدرج · ويدجت الدعوات · جرس الإشعارات · بانر الموافقة
            ['notifications.bell.title', 'notifications', 'الهيدر والجرس: الإشعارات', 'الإشعارات'],
            ['notifications.bell.mark_all', 'notifications', 'الهيدر والجرس: تعليم الكلّ كمقروء', 'تعليم الكلّ كمقروء'],
            ['notifications.bell.tab_all', 'notifications', 'الهيدر والجرس: الكلّ', 'الكلّ'],
            ['notifications.bell.tab_platform', 'notifications', 'الهيدر والجرس: المنصّة', 'المنصّة'],
            ['notifications.bell.tab_volunteer', 'notifications', 'الهيدر والجرس: التطوّع', 'التطوّع'],
            ['notifications.bell.empty', 'notifications', 'الهيدر والجرس: مفيش إشعارات جديدة', 'مفيش إشعارات جديدة'],
            ['nav.header.menu_aria', 'nav', 'الهيدر والجرس: القائمة', 'القائمة'],
            ['nav.header.search_aria', 'nav', 'الهيدر والجرس: بحث موحّد', 'بحث موحّد'],
            ['nav.header.search_title', 'nav', 'الهيدر والجرس: بحث موحّد (Ctrl+K)', 'بحث موحّد (Ctrl+K)'],
            ['nav.header.membership_aria', 'nav', 'الهيدر والجرس: سياق العضويّة', 'سياق العضويّة'],
            ['nav.header.bell_aria', 'nav', 'الهيدر والجرس: الإشعارات', 'الإشعارات'],
            ['ads.consent.banner_aria', 'ads', 'الهيدر والجرس: الموافقة على التتبّع', 'الموافقة على التتبّع'],
            ['nav.drawer.close_aria', 'nav', 'الهيدر والجرس: اقفل القائمة', 'اقفل القائمة'],
            ['nav.referral.copy_done', 'nav', 'الهيدر والجرس: اتنسخ ✓', 'اتنسخ ✓'],
            ['nav.referral.widget_aria', 'nav', 'الهيدر والجرس: دعوة الأصدقاء', 'دعوة الأصدقاء'],
            ['nav.referral.title', 'nav', 'الهيدر والجرس: 👥 ادعُ أصدقاءك', '👥 ادعُ أصدقاءك'],
            ['nav.referral.invited_suffix', 'nav', 'الهيدر والجرس: مدعوّ', 'مدعوّ'],
            ['nav.referral.link_label', 'nav', 'الهيدر والجرس: رابط دعوتك', 'رابط دعوتك'],
            ['nav.referral.copy_action', 'nav', 'الهيدر والجرس: نسخ الرابط', 'نسخ الرابط'],
            ['nav.referral.invite_action', 'nav', 'الهيدر والجرس: دعوة أصدقائك', 'دعوة أصدقائك'],
        ];

        foreach ($rows as [$key, $group, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }
}
