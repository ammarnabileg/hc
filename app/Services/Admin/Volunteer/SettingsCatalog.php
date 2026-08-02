<?php

namespace App\Services\Admin\Volunteer;

/**
 * كتالوج إعدادات مجال «إدارة التطوّع والتلعيب والمكافآت والفعاليّات».
 *
 * لماذا كتالوج واحد؟ لأنّ القاعدة الذهبيّة (2.13) تمنع أيّ رقم أو نصّ محروق،
 * وزرّ «Reset للافتراضيّ» يحتاج مرجعًا واحدًا للقيمة الافتراضيّة لكلّ مفتاح؛
 * فلو تفرّقت الافتراضيّات بين السيدر والكود اختلفت القيمتان وضاع معنى الـReset.
 *
 * الصيغة: key => [group, label_ar, type, default, hint?]
 */
class SettingsCatalog
{
    /** @return array<string, array{0:string,1:string,2:string,3:string,4?:string}> */
    public static function all(): array
    {
        return array_merge(
            self::volunteerPage(),
            self::org(),
            self::rep(),
            self::offboarding(),
            self::certificates(),
            self::analytics(),
            self::xpAndTickets(),
            self::streaksAndLeaderboard(),
            self::wars(),
            self::celebrations(),
            self::rewards(),
            self::events(),
        );
    }

    /** القيمة الافتراضيّة المعتمَدة لمفتاح — مرجع زرّ الـReset */
    public static function defaultOf(string $key): ?string
    {
        return self::all()[$key][3] ?? null;
    }

    /** مفاتيح مجموعة بعينها — لكلّ تاب مجموعته */
    public static function group(string $group): array
    {
        return array_filter(self::all(), fn ($row) => $row[0] === $group);
    }

    // ---------------------------------------------------------------- صفحة التطوّع (13.4-أ)

    private static function volunteerPage(): array
    {
        return [
            'volunteer_page.hero_title' => ['volunteer_page', 'عنوان صفحة التطوّع', 'string', 'تطوّع معنا واصنع أثرًا'],
            'volunteer_page.hero_subtitle' => ['volunteer_page', 'السطر التعريفيّ', 'text', 'وقتك يقدر يغيّر رحلة متدرّب كامل — ابدأ من هنا.'],
            'volunteer_page.hero_media' => ['volunteer_page', 'رابط فيديو/صورة الأثر', 'string', ''],
            'volunteer_page.cta_label' => ['volunteer_page', 'نصّ الزرّ الرئيسيّ', 'string', 'ابدأ التدريب التأهيليّ'],
            'volunteer_page.charter_text' => ['volunteer_page', 'ميثاق المتطوّع', 'text', 'أتعهّد بالالتزام بمواعيدي، وباحترام فريقي، وبالحفاظ على ما يُؤتمَن عليّ من بيانات.'],
            'volunteer_page.stats_enabled' => ['volunteer_page', 'إظهار الإحصائيّات الحيّة', 'bool', '1'],
            'volunteer_page.stats_offset_volunteers' => ['volunteer_page', 'Offset عدّاد المتطوّعين', 'number', '0'],
            'volunteer_page.stats_offset_trainees' => ['volunteer_page', 'Offset عدّاد المستفيدين', 'number', '0'],
            // كتل المحتوى: تُضاف وتُعدَّل وتُحذَف كلّها من هنا (13.4-أ)
            'volunteer_page.blocks' => ['volunteer_page', 'كتل المحتوى (سؤال شائع · قصّة · أثر)', 'json', '[]'],
            'volunteer_page.empty_message' => ['volunteer_page', 'رسالة الحالة الفارغة', 'string', 'لسّه محتوى الصفحة فاضي — ابدأ بأوّل كتلة.'],
        ];
    }

    // ---------------------------------------------------------------- الهيكل والسعة (13.4-ف)

    private static function org(): array
    {
        return [
            'volunteer.org.canvas_collapse_limit' => ['volunteer_org', 'حدّ الطيّ التلقائيّ للكانفاس (عقدة)', 'number', '30'],
            'volunteer.org.canvas_open_levels' => ['volunteer_org', 'مستويات الفتح الافتراضيّة', 'number', '2'],
            'volunteer.org.capacity_is_blocking' => ['volunteer_org', 'السعة مانعة؟ (مقفول: مؤشّرات لا موانع)', 'bool', '0'],
            'volunteer.org.occupancy_warn_percent' => ['volunteer_org', 'عتبة اللون الأصفر للإشغال (%)', 'number', '80'],
            'volunteer.org.occupancy_danger_percent' => ['volunteer_org', 'عتبة اللون الأحمر للإشغال (%)', 'number', '100'],
            'volunteer.org.unhealthy_alert_enabled' => ['volunteer_org', 'تنبيه «كيان غير صحّيّ»', 'bool', '1'],
            'volunteer.org.balancer_suggest_least_loaded' => ['volunteer_org', 'اقتراح الموازن للأقلّ إشغالًا', 'bool', '1'],
            'volunteer.org.memberships_per_track' => ['volunteer_org', 'حدّ العضويّات لكلّ مسار', 'number', '1'],
            // الملفّ المؤقّت: يفتحه ويُنهيه مشرف عام التطوّع وحده (23-0.2)
            'volunteer.org.case_file_opener_position' => ['volunteer_org', 'البوزشن الذي يفتح الملفّ المؤقّت ويُنهيه', 'string', 'volunteer_gm'],
            'volunteer.org.member_cap_per_span_factor' => ['volunteer_org', 'معامل احتساب سقف أعضاء الكيان من نطاق الإشراف', 'number', '2'],
        ];
    }

    // ---------------------------------------------------------------- ضبط Rep (13.4-ن)

    private static function rep(): array
    {
        return [
            'rep.behavior.monthly_cap_per_granter' => ['volunteer_rep', 'سقف معاملات السلوك شهريًّا لكلّ مانح', 'number', '5'],
            'rep.behavior.severe_approval_window_hours' => ['volunteer_rep', 'نافذة موافقة المستوى الأعلى على المخالفة الجسيمة (ساعة)', 'number', '24'],
            'rep.behavior.justification_min_chars' => ['volunteer_rep', 'الحدّ الأدنى لطول المبرّر (حرف)', 'number', '10'],
            'rep.behavior.attachment_enabled' => ['volunteer_rep', 'السماح بمرفق مع معاملة السلوك', 'bool', '1'],
            'rep.behavior.show_granter_count_in_team_health' => ['volunteer_rep', 'إظهار عدد معاملات المانح في صحّة فريقه', 'bool', '1'],
            'rep.objection.window_days' => ['volunteer_rep', 'مهلة الاعتراض (يوم)', 'number', '5'],
            'rep.inactivity.days_before_alert' => ['volunteer_rep', 'أيّام الخمول قبل التنبيه', 'number', '21'],
            'rep.reset.day_of_month' => ['volunteer_rep', 'يوم التصفير الشهريّ', 'number', '1'],
            'rep.reset.hour' => ['volunteer_rep', 'ساعة التصفير', 'number', '5'],
            'rep.reset.timezone' => ['volunteer_rep', 'توقيت التصفير', 'string', 'Africa/Cairo'],
            'rep.reset.enabled' => ['volunteer_rep', 'تفعيل التصفير الشهريّ', 'bool', '1'],
            'rep.display.min' => ['volunteer_rep', 'أدنى الرقم الظاهر', 'number', '-10'],
            'rep.display.max' => ['volunteer_rep', 'أقصى الرقم الظاهر', 'number', '10'],
            'rep.daily_gain_cap_enabled' => ['volunteer_rep', 'تفعيل سقف المكسب اليوميّ (معطّل افتراضيًّا)', 'bool', '0'],
        ];
    }

    // ---------------------------------------------------------------- الأوفبوردنج (13.4-س · 13.4-ق)

    private static function offboarding(): array
    {
        return [
            'volunteer.offboarding.notice_days' => ['volunteer_offboarding', 'مهلة الإشعار لتسليم العمل (يوم)', 'number', '7'],
            'volunteer.offboarding.cooldown_days.resignation' => ['volunteer_offboarding', 'تبريد العودة بعد الاستقالة (يوم)', 'number', '30'],
            'volunteer.offboarding.cooldown_days.entity_ended' => ['volunteer_offboarding', 'تبريد العودة بعد انتهاء الملفّ (يوم)', 'number', '30'],
            'volunteer.offboarding.cooldown_days.thresholds' => ['volunteer_offboarding', 'تبريد العودة بعد الخروج عبر العتبات (يوم)', 'number', '90'],
            'volunteer.offboarding.exclusion_allows_return' => ['volunteer_offboarding', 'الإقصاء يسمح بالعودة؟ (بقرار مشرف عام التطوّع وحده)', 'bool', '0'],
            'volunteer.offboarding.exit_interview_enabled' => ['volunteer_offboarding', 'تفعيل مقابلة الخروج', 'bool', '1'],
            'volunteer.offboarding.exit_interview_questions' => ['volunteer_offboarding', 'أسئلة مقابلة الخروج', 'json', '["إيه أكتر حاجة عجبتك في تجربتك معنا؟","إيه اللي كان ممكن يخلّيك تكمّل؟","سبب المغادرة باختصار؟"]'],
            'volunteer.offboarding.honorable_certificate_enabled' => ['volunteer_offboarding', 'شهادة خبرة عند الخروج المشرَّف', 'bool', '1'],
            'volunteer.offboarding.reason_published_to_team' => ['volunteer_offboarding', 'نشر سبب الخروج للفريق؟ (مقفول: لا يُنشَر)', 'bool', '0'],
            'volunteer.offboarding.team_message' => ['volunteer_offboarding', 'رسالة الفريق عند الإنهاء', 'string', 'انتهت عضويّة {name} — نتمنّى له كلّ التوفيق.'],
            'volunteer.offboarding.clearance_items' => ['volunteer_offboarding', 'بنود التصفية الإلزاميّة', 'json', '["نقل المهامّ المفتوحة للأبلاين بنفس الديدلاينات","سحب المساهمات الجارية وتحرير الرصيد المعلَّق","حسم الاعتراضات والتحكيمات المفتوحة","تفويض الداونلاين للأبلاين فورًا","نقل الاجتماعات والبنود المتكرّرة","إبقاء مُدخَلات المكتبة الداخليّة للكيان"]'],
            'volunteer.offboarding.cumulative_window_days' => ['volunteer_offboarding', 'نافذة المكتسَب التراكميّ (يوم)', 'number', '90'],
            'volunteer.offboarding.reentry_starts_position' => ['volunteer_offboarding', 'بوزشن العائد', 'string', 'coordinator'],
            'volunteer.offboarding.reentry_exam_required' => ['volunteer_offboarding', 'إلزام الامتحان للعائدين', 'bool', '1'],
            'volunteer.offboarding.cooldown_copy' => ['volunteer_offboarding', 'نصّ صفحة التطوّع داخل التبريد', 'string', 'أهلًا بعودتك 👋 مكانك محفوظ عندنا. تقدر تبدأ من جديد يوم {date}.'],
            'volunteer.offboarding.excluded_copy' => ['volunteer_offboarding', 'نصّ صفحة التطوّع بعد الإقصاء', 'string', 'العودة بعد الاستبعاد بتحتاج قرارًا من مشرف عام التطوّع. تواصل معنا من صفحة الدعم.'],
        ];
    }

    // ---------------------------------------------------------------- شهادات التطوّع (13.4-ع)

    private static function certificates(): array
    {
        return [
            // ⭐ شرطا الاستحقاق المنصوصان
            'volunteer_cert.min_days_in_position' => ['volunteer_cert', 'الحدّ الأدنى للمدّة في البوزشن (يوم)', 'number', '30'],
            'volunteer_cert.require_non_negative_rep' => ['volunteer_cert', 'اشتراط Rep غير سالب وقت الإصدار', 'bool', '1'],
            'volunteer_cert.one_per_position_entity' => ['volunteer_cert', 'شهادة واحدة لكلّ (بوزشن × كيان) — مقفول', 'bool', '1'],
            'volunteer_cert.auto_issue' => ['volunteer_cert', 'الإصدار التلقائيّ عند الاستيفاء', 'bool', '1'],
            'volunteer_cert.notify_on_issue' => ['volunteer_cert', 'إشعار عند الإصدار', 'bool', '1'],
            'volunteer_cert.celebration_tier' => ['volunteer_cert', 'مستوى الاحتفال عند الإصدار (3 = ذروة)', 'number', '3'],
            'volunteer_cert.free_locked' => ['volunteer_cert', 'مجّانيّة 100% — مقفول', 'bool', '1'],
            'volunteer_cert.hide_internal_numbers' => ['volunteer_cert', 'منع أيّ أرقام داخليّة على الشهادة — مقفول', 'bool', '1'],
            'volunteer_cert.revoke_only_on_fraud' => ['volunteer_cert', 'الإلغاء للتزوير المثبَت وحده — مقفول', 'bool', '1'],
            'volunteer_cert.cumulative_duration_on_reentry' => ['volunteer_cert', 'تجميع المدّة تراكميًّا عند العودة', 'bool', '1'],
            'volunteer_cert.types' => ['volunteer_cert', 'الأنواع الأربعة وتفعيلها', 'json', '{"volunteer_position":true,"volunteer_experience":true,"volunteer_case_file":true,"volunteer_appreciation":true}'],
            'volunteer_cert.min_days_by_position' => ['volunteer_cert', 'الحدّ الأدنى للمدّة لكلّ بوزشن (يتجاوز العامّ)', 'json', '{}'],
            'volunteer_cert.show_in_library_and_cv' => ['volunteer_cert', 'الظهور التلقائيّ في مكتبتي والبروفايل والـCV', 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- تحليلات التطوّع

    private static function analytics(): array
    {
        return [
            'volunteer.analytics.default_range_days' => ['volunteer_analytics', 'المدى الزمنيّ الافتراضيّ (يوم)', 'number', '30'],
            'volunteer.analytics.team_health_weights' => ['volunteer_analytics', 'أوزان مؤشّر صحّة الفريق', 'json', '{"commitment":40,"delay":20,"returns":20,"review_speed":20}'],
            'volunteer.analytics.attrition_top' => ['volunteer_analytics', 'عدد أسباب التسرّب المعروضة', 'number', '5'],
            'volunteer.analytics.load_alert_tasks' => ['volunteer_analytics', 'عتبة تنبيه الحمل (عدد مهامّ)', 'number', '10'],
            'volunteer.analytics.retention_risk_visible_to_volunteer' => ['volunteer_analytics', 'عرض مخاطر الفقدان للمتطوّع؟ (مقفول: لا)', 'bool', '0'],
        ];
    }

    // ---------------------------------------------------------------- XP والتذاكر (12.10 · 7 · 7.1)

    private static function xpAndTickets(): array
    {
        return [
            'xp_rules.earn' => ['gamification_xp', 'مصادر كسب XP', 'json', '[{"key":"lesson.completed","label":"إكمال درس","value":50,"daily_cap":0,"enabled":true},{"key":"streak.day","label":"يوم ستريك","value":100,"daily_cap":0,"enabled":true},{"key":"referral.success","label":"دعوة ناجحة","value":200,"daily_cap":0,"enabled":true},{"key":"placement_test","label":"اختبار تمهيديّ","value":30,"daily_cap":1,"enabled":true},{"key":"positive_message","label":"رسالة إيجابيّة","value":10,"daily_cap":3,"enabled":true},{"key":"game.session","label":"لعبة","value":20,"daily_cap":5,"enabled":true},{"key":"war.win","label":"فوز حرب","value":150,"daily_cap":0,"enabled":true},{"key":"five_am_club","label":"حضور نادي الخامسة","value":100,"daily_cap":1,"enabled":true},{"key":"qualifying.completed","label":"إتمام المسار التأهيليّ","value":1000,"daily_cap":0,"enabled":true}]'],
            'xp_rules.spend' => ['gamification_xp', 'أوجه الصرف', 'json', '[{"key":"course.exam","label":"الامتحان النهائيّ","currency":"tickets","cost":1,"moment":"on_enter","enabled":true},{"key":"game.enter","label":"دخول لعبة","currency":"tickets","cost":1,"moment":"on_enter","enabled":true},{"key":"cv.export","label":"السيرة الذاتيّة","currency":"tickets","cost":2,"moment":"on_export","enabled":true},{"key":"streak.freeze","label":"تجميد ستريك","currency":"tickets","cost":1,"moment":"on_use","enabled":true},{"key":"war.focus.create","label":"إنشاء حرب تركيز","currency":"tickets","cost":5,"moment":"on_create","enabled":true},{"key":"war.join","label":"الانضمام لحرب","currency":"tickets","cost":1,"moment":"on_join","enabled":true}]'],
            // ⭐ قيمتا التدريب قبل/بعد نصف المهلة (12.10 — بلوك الإعدادات)
            'tickets.before_half_deadline' => ['gamification_xp', 'تذاكر إتمام التدريب قبل نصف المهلة', 'number', '2'],
            'tickets.after_half_deadline' => ['gamification_xp', 'تذاكر إتمام التدريب بعد نصف المهلة', 'number', '1'],
            'tickets.midpoint_percent' => ['gamification_xp', 'نقطة المنتصف من المهلة (%)', 'number', '50'],
            'xp_rules.decay_mode' => ['gamification_xp', 'نمط تناقص XP الدرس', 'string', 'linear'],
            'xp_rules.decay_min' => ['gamification_xp', 'الحدّ الأدنى بعد التناقص', 'number', '0'],
            'xp_rules.course_xp_once_locked' => ['gamification_xp', 'XP إكمال الكورس مرّة واحدة أيًّا كان السياق — مقفول', 'bool', '1'],
            'levels.enabled' => ['gamification_xp', 'تفعيل المستويات', 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- الستريك والليدر بورد (7.2 · 7.3)

    private static function streaksAndLeaderboard(): array
    {
        return [
            'streaks.enabled' => ['gamification_streaks', 'تفعيل الستريك ونادي الخامسة', 'bool', '1'],
            'streaks.club5am.window_start' => ['gamification_streaks', 'بداية نافذة نادي الخامسة (توقيت المستخدم)', 'string', '04:50'],
            'streaks.club5am.window_end' => ['gamification_streaks', 'نهاية نافذة نادي الخامسة', 'string', '05:20'],
            'streaks.xp_ladder' => ['gamification_streaks', 'سلّم XP الحضور المتدرّج', 'json', '[{"from":1,"to":10,"xp":100},{"from":11,"to":25,"xp":150},{"from":26,"to":45,"xp":200},{"from":46,"to":75,"xp":250},{"from":76,"to":125,"xp":300},{"from":126,"to":0,"xp":350}]'],
            'streaks.reward_days' => ['gamification_streaks', 'أيّام الستريك المتواصلة للمكافأة', 'number', '7'],
            'streaks.freeze_cost_tickets' => ['gamification_streaks', 'تكلفة تجميد الستريك (تذاكر)', 'number', '1'],
            'streaks.max_freezes_per_month' => ['gamification_streaks', 'أقصى تجميدات شهريًّا', 'number', '2'],
            'streaks.celebration_tier' => ['gamification_streaks', 'مستوى الاحتفال بالستريك', 'number', '2'],

            'leaderboard.enabled' => ['gamification_leaderboard', 'تفعيل الليدر بورد', 'bool', '1'],
            'leaderboard.ranges' => ['gamification_leaderboard', 'النطاقات المفعّلة (أيّام)', 'json', '[7,30]'],
            'leaderboard.custom_range_enabled' => ['gamification_leaderboard', 'السماح بفترة مخصّصة', 'bool', '1'],
            'leaderboard.min_participants' => ['gamification_leaderboard', 'حدّ أدنى للمشاركين لإظهار اللوحة', 'number', '5'],
            'leaderboard.rows_per_load' => ['gamification_leaderboard', 'عدد الصفوف لكلّ تحميل', 'number', '20'],
            'leaderboard.recalc_hours' => ['gamification_leaderboard', 'دوريّة إعادة الاحتساب (ساعة)', 'number', '6'],
            'leaderboard.frozen' => ['gamification_leaderboard', 'تجميد اللوحة مؤقّتًا', 'bool', '0'],
            'leaderboard.hide_suspended' => ['gamification_leaderboard', 'إخفاء الموقوفين والمحذوفين', 'bool', '1'],

            'badges.enabled' => ['gamification_badges', 'تفعيل نظام الشارات', 'bool', '1'],
            'badges.max_on_profile' => ['gamification_badges', 'أقصى شارات ظاهرة على البروفايل', 'number', '6'],
            'badges.celebration_tier' => ['gamification_badges', 'مستوى الاحتفال عند المنح', 'number', '2'],
            'badges.show_holders_count' => ['gamification_badges', 'إظهار عدد الحاصلين للمستخدم', 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- الحروب (12.10-ج · 15.0)

    private static function wars(): array
    {
        return [
            'wars.shared.arena_ratio' => ['gamification_wars', 'نسبة أسئلة الساحة (%)', 'number', '70'],
            'wars.shared.training_ratio' => ['gamification_wars', 'نسبة أسئلة التدريبات (%)', 'number', '30'],
            'wars.shared.ready_tickets' => ['gamification_wars', 'شرط الاستعداد (تذاكر)', 'number', '12'],
            'wars.shared.win' => ['gamification_wars', 'مكافأة الفوز', 'number', '2'],
            'wars.shared.loss' => ['gamification_wars', 'خصم الخسارة', 'number', '-2'],
            'wars.shared.withdraw' => ['gamification_wars', 'خصم الانسحاب', 'number', '-10'],
            'wars.shared.loss_rule_count' => ['gamification_wars', 'قاعدة عدد الخسارات', 'number', '3'],
            'wars.shared.decision_seconds' => ['gamification_wars', 'مؤقّت الحسم (ثانية)', 'number', '20'],
            'wars.shared.question_seconds' => ['gamification_wars', 'وقت السؤال (ثانية)', 'number', '15'],
            'wars.shared.focus_durations' => ['gamification_wars', 'مدد التركيز (دقيقة)', 'json', '[5,15,25,50]'],
            'wars.shared.max_visible_fighters' => ['gamification_wars', 'أقصى محاربين ظاهرين', 'number', '10'],
            'wars.shared.max_active_focus' => ['gamification_wars', 'أقصى تحديات تركيز نشطة', 'number', '5'],
            'wars.shared.create_focus_tickets' => ['gamification_wars', 'تكلفة إنشاء حرب تركيز (تذاكر)', 'number', '5'],
            'wars.shared.join_tickets' => ['gamification_wars', 'تكلفة الانضمام (تذاكر)', 'number', '1'],
            'wars.shared.min_active_questions' => ['gamification_wars', 'حدّ أدنى للأسئلة المفعّلة قبل التشغيل', 'number', '20'],
            // ⭐ قفل الإعدادات أثناء حرب نشطة (12.10-ج)
            'wars.lock_while_active' => ['gamification_wars', 'قفل الإعدادات أثناء حرب نشطة', 'bool', '1'],
            'wars.lock_message' => ['gamification_wars', 'رسالة القفل', 'string', 'تعذّر الحفظ — حرب نشطة الآن، حاول بعد انتهائها.'],
        ];
    }

    // ---------------------------------------------------------------- الاحتفالات (2.14)

    private static function celebrations(): array
    {
        return [
            'celebrations.enabled' => ['gamification_celebrations', 'تفعيل نظام الاحتفالات', 'bool', '1'],
            // نفس مفاتيح خدمة الاحتفالات المشتركة — مصدر واحد لا نسختان (2.14-ب)
            'celebrations.peak.daily_cap' => ['gamification_celebrations', 'الحدّ اليوميّ لمستوى الذروة', 'number', '3'],
            'celebrations.auto_dismiss_seconds' => ['gamification_celebrations', 'الانتهاء التلقائيّ (ثانية)', 'number', '6'],
            // ⭐ الأنيميشن دائم بلا توجل — الصوت وحده له توجل (2.14-ب)
            'celebrations.animation_always_on' => ['gamification_celebrations', 'الأنيميشن حاضر دائمًا — مقفول', 'bool', '1'],
            'celebrations.sound.enabled' => ['gamification_celebrations', 'تفعيل الصوت (يخضع لتوجل البروفايل)', 'bool', '1'],
            'celebrations.tiers_locked' => ['gamification_celebrations', 'ثلاثة مستويات لا رابع — مقفول', 'bool', '1'],
            'celebrations.once_per_event' => ['gamification_celebrations', 'مرّة واحدة لكلّ حدث (Server-side) — مقفول', 'bool', '1'],
            'celebrations.share_button' => ['gamification_celebrations', 'زرّ المشاركة في مستوى الذروة', 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- إدارة المكافآت (12.9)

    private static function rewards(): array
    {
        return [
            // ⭐ الخصم ينزل تحت الصفر مسموح صراحةً (12.9)
            'rewards.allow_negative_balance' => ['rewards', 'السماح بالنزول تحت الصفر في الخصم — مقفول ON', 'bool', '1'],
            'rewards.batch_size' => ['rewards', 'حجم دفعة المعالجة (كود)', 'number', '500'],
            'rewards.max_codes' => ['rewards', 'أقصى عدد أكواد في العمليّة الواحدة', 'number', '2000'],
            'rewards.notify_recipient' => ['rewards', 'إشعار المستلِم', 'bool', '1'],
            'rewards.celebration_tier' => ['rewards', 'مستوى الاحتفال عند المنح', 'number', '2'],
            'rewards.grant_message' => ['rewards', 'نصّ إشعار المنح', 'string', 'وصلك رصيد جديد: {amount} {currency} — {reason}'],
            'rewards.deduct_message' => ['rewards', 'نصّ إشعار الخصم', 'string', 'اتخصم من رصيدك {amount} {currency} — {reason}'],
            'rewards.card_enabled' => ['rewards', 'بطاقة التهنئة بعد المنح', 'bool', '1'],
            'rewards.card_title' => ['rewards', 'عنوان بطاقة التهنئة', 'string', 'مبروك يا {name} 🎉'],
            'rewards.currencies' => ['rewards', 'العملات المسموح منحها', 'json', '["xp","coins","tickets"]'],
            'rewards.reasons' => ['rewards', 'قائمة أسباب التحويل', 'json', '{"bonus":"بونص/ماينص","purchases":"مشتريات","transfers":"تحويلات","tech_fix":"تصحيح خطأ تقنيّ"}'],
            'rewards.reasons_requiring_reference' => ['rewards', 'الأسباب التي تُظهر حقل المرجع', 'json', '["purchases","transfers","tech_fix"]'],
            'rewards.reasons_requiring_reference_strict' => ['rewards', 'الأسباب التي المرجع فيها إلزاميّ', 'json', '["tech_fix"]'],
            'rewards.notes' => ['rewards', 'قائمة الملاحظات', 'json', '{"rewards":"مكافآت","violations":"مخالفات","participation":"مشاركات","other":"أخرى"}'],
            'rewards.segments' => ['rewards', 'الشرائح الجاهزة للاستهداف', 'json', '{"all_active":"كلّ الحسابات النشطة","volunteers":"المتطوّعون النشطون","zero_balance":"أصحاب الرصيد صفر"}'],
        ];
    }

    // ---------------------------------------------------------------- الفعاليّات (12.11 · 13.3)

    private static function events(): array
    {
        return [
            'events.default_view' => ['events', 'العرض الافتراضيّ', 'string', 'table'],
            'events.default_tab' => ['events', 'التبويب الافتراضيّ', 'string', 'upcoming'],
            'events.modes' => ['events', 'أنواع الفعاليّة', 'json', '{"offline":"أوفلاين","online":"أونلاين","hybrid":"هجين"}'],
            'events.reminders' => ['events', 'مواعيد التذكير قبل الموعد (ساعة)', 'json', '[24,1]'],
            'events.reminder_channels' => ['events', 'قنوات التذكير', 'json', '["bell","toast"]'],
            'events.qr_refresh_seconds' => ['events', 'ثوانٍ تجديد QR التشيك-إن', 'number', '30'],
            'events.online_link_minutes_before' => ['events', 'ظهور رابط الأونلاين قبل الموعد (دقيقة)', 'number', '30'],
            'events.attendance_code_persistent' => ['events', 'كود الحضور مستمرّ لا يقفل — مقفول', 'bool', '1'],
            'events.attendance_code_length' => ['events', 'طول كود الحضور', 'number', '6'],
            'events.reward_tiers_default' => ['events', 'جدول المكافأة المتدرّجة الافتراضيّ', 'json', '[{"hours":24,"xp":200,"tickets":1},{"hours":72,"xp":100,"tickets":0},{"hours":168,"xp":50,"tickets":0}]'],
            'events.close_registration_when_full' => ['events', 'إغلاق التسجيل عند الاكتمال (بلا قائمة انتظار)', 'bool', '1'],
            'events.show_registrant_avatars' => ['events', 'أفاتارات المسجّلين (دليل اجتماعيّ)', 'bool', '1'],
            'events.add_to_calendar' => ['events', 'زرّ «أضِف لتقويمي»', 'bool', '1'],
            'events.friend_invite' => ['events', 'دعوة صديق', 'bool', '1'],
            'events.reward_countdown' => ['events', 'عدّاد المكافأة النازل عند المستخدم', 'bool', '1'],
            'events.empty_message' => ['events', 'رسالة الحالة الفارغة', 'string', 'لا فعاليّات — أنشئ أوّل لقاء.'],
        ];
    }
}
