<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * تعريفات «حدود الشاشات» — الموجة الأخيرة من الأرقام المحروقة (2.13).
 *
 * لماذا سيدر مستقلّ؟ لأنّ هذه المفاتيح **عابرة للمجالات**: طول جدول هنا وحجم
 * صفحة هناك ونافذة عدّاد في ثالث؛ فلو وُزّعت على سيدرات المجالات تفرّقت القاعدة
 * الواحدة («لا رقم محروق في حدّ قائمة») على عشرة ملفّات فضاعت مراجعتها.
 *
 * ولا بيانات عرض هنا إطلاقًا — تعريفاتٌ فقط، يلتقطها `SettingDefinitionsSeeder`
 * تلقائيًّا لأنّ اسم الميثود ينتهي بـ`settings`.
 *
 * `firstOrCreate` عمدًا: لا نلمس قيمةً عدّلها المالك بالفعل (2.13-د).
 */
class ScreenLimitsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
    }

    /** [key, group, label_ar, type, default] */
    public function settings(): void
    {
        $rows = [
            // ---------------- المستخدمون والاعتمادات
            ['admin.users.tab_rows', 'admin_users', 'صفوف كلّ تاب في ملفّ المستخدم', 'number', '25'],

            // ---------------- الاجتماعات
            ['meetings.list_limit', 'meetings', 'عدد الاجتماعات المعروضة في القائمة', 'number', '60'],
            ['admin_meetings.export.max_meetings', 'meetings', 'أقصى اجتماعات في تصدير الحضور', 'number', '2000'],
            // عناوين حالات الاجتماع (MeetingsMirror::statuses — 2.13-ب)
            ['admin_meetings.status.scheduled', 'meetings', 'حالة: قادم', 'string', 'قادم'],
            ['admin_meetings.status.running', 'meetings', 'حالة: جارٍ', 'string', 'جارٍ'],
            ['admin_meetings.status.ended', 'meetings', 'حالة: منتهٍ', 'string', 'منتهٍ'],

            // ---------------- الإعلان المدفوع
            ['ads.audiences.per_page', 'ads', 'عدد الشرائح في الصفحة', 'number', '20'],
            ['ads.exports.rows', 'ads', 'عدد صفوف سجلّ تصدير الشرائح', 'number', '10'],

            // ---------------- النسخ الاحتياطيّ
            ['backups.prune_batch', 'backups', 'حجم دفعة حذف النسخ الزائدة', 'number', '1000'],

            // ---------------- التلعيب
            ['badges.export_rows', 'gamification_badges', 'عدد الشارات في بطاقة الاستخراج', 'number', '6'],
            ['wars.focus.avatars_shown', 'gamification_wars', 'عدد أفاتار المنضمّين الظاهرة', 'number', '4'],
            ['kudos.search_limit', 'kudos', 'عدد نتائج البحث عن زميل', 'number', '8'],
            ['rewards.codes_preview_rows', 'rewards', 'عدد الأكواد في معاينة المكافأة', 'number', '100'],

            // ---------------- الفعاليّات
            ['events.admin.list_limit', 'events', 'عدد الفعاليّات في جدول الإدارة', 'number', '50'],
            ['events.admin.registrations_limit', 'events', 'عدد التسجيلات المعروضة للفعاليّة', 'number', '100'],

            // ---------------- الوسائط والصور
            ['images.rate_limit_window_minutes', 'images', 'نافذة عدّاد حدّ التوليد (دقائق)', 'number', '2'],
            ['images.sample_users_limit', 'images', 'عدد المستخدمين في معاينة الاستوديو', 'number', '20'],
            ['media.picker.limit', 'media', 'عدد عناصر منتقي الوسائط', 'number', '12'],

            // ---------------- الإشعارات
            ['notifications.bell.max_items', 'notifications', 'عدد إشعارات الجرس', 'number', '20'],
            ['notifications.toast.poll_seconds', 'notifications', 'دوريّة استطلاع الإشعارات اللحظيّة (ثوانٍ)', 'number', '20'],
            ['notifications.toast.poll_limit', 'notifications', 'أقصى إشعارات في كلّ استطلاع لحظيّ', 'number', '10'],

            // ---------------- بنك الأسئلة
            ['question_bank.lessons_picker_limit', 'exams', 'عدد الدروس في منتقي بنك الأسئلة', 'number', '500'],
            ['question_bank.export.max_rows', 'exams', 'أقصى صفوف تصدير بنك الأسئلة', 'number', '50000'],

            // ---------------- الدعوات والإحالة
            ['referral_admin.preview_rows', 'growth', 'عدد صفوف معاينة الدعوات', 'number', '100'],
            ['referral_admin.export.max_rows', 'growth', 'أقصى صفوف تصدير الدعوات', 'number', '50000'],
            // عناوين حالات الدعوة والمكافأة (ReferralAdmin::statuses/payouts — 2.13-ب)
            ['referral_admin.status.completed', 'growth', 'حالة دعوة: مكتمل', 'string', 'مكتمل'],
            ['referral_admin.status.waiting', 'growth', 'حالة دعوة: بانتظار التفعيل', 'string', 'بانتظار التفعيل'],
            ['referral_admin.status.incomplete', 'growth', 'حالة دعوة: لم يكمل التسجيل', 'string', 'لم يكمل التسجيل'],
            ['referral_admin.payout.pending', 'growth', 'حالة مكافأة: معلّقة', 'string', 'معلّقة'],
            ['referral_admin.payout.paid', 'growth', 'حالة مكافأة: مصروفة', 'string', 'مصروفة'],
            ['referral_admin.payout.held', 'growth', 'حالة مكافأة: موقوفة', 'string', 'موقوفة'],

            // ---------------- التقارير المجدولة
            ['report_schedules.per_page', 'stats', 'عدد الجدولات في الصفحة', 'number', '20'],
            ['report_schedules.runs_per_page', 'stats', 'عدد صفوف سجلّ الإرسال في الصفحة', 'number', '30'],

            // ---------------- المتجر والشحن
            ['topup.admin.user_history_rows', 'store', 'عدد طلبات الشحن السابقة في شاشة المراجعة', 'number', '5'],
            ['topup.history.per_page', 'store', 'عدد طلبات الشحن في صفحة المتدرّب', 'number', '10'],
            ['finance.withdraw.admin.per_page', 'store', 'عدد طلبات السحب لكلّ صفحة', 'number', '20'],
            ['finance.withdraw.admin.user_history_rows', 'store', 'عدد طلبات السحب السابقة في شاشة المراجعة', 'number', '5'],

            // ---------------- المحفظة
            ['wallet.recent_rows', 'wallet', 'عدد المعاملات الأخيرة في المحفظة', 'number', '5'],
            ['wallet.transactions.per_page', 'wallet', 'عدد المعاملات في الصفحة', 'number', '20'],

            // ---------------- البساطة أوّلًا
            ['ux.avatar.initials_count', 'ux', 'عدد أحرف الأفاتار البديلة', 'number', '2'],

            // ---------------- التطوّع
            ['workflow.work_items.picker_limit', 'workflow', 'عدد البنود في منتقي المهمّة', 'number', '50'],
            ['volunteer.overview.objectionable_rows', 'volunteer', 'عدد الحركات القابلة للاعتراض في اللوحة', 'number', '5'],
            ['volunteer.transactions.max_rows', 'volunteer', 'أقصى صفوف جدول معاملات التطوّع', 'number', '200'],
            ['volunteer.calendar.events_per_day', 'volunteer', 'عدد بنود اليوم في التقويم', 'number', '3'],
            ['volunteer.admin.recent_placements', 'volunteer', 'عدد التعيينات الأخيرة في لوحة التطوّع', 'number', '6'],
            ['volunteer.admin.loads_preview', 'volunteer', 'عدد صفوف معاينة الأحمال', 'number', '5'],
            ['volunteer.org.members_limit', 'volunteer_org', 'عدد الأعضاء في شاشة الهيكل', 'number', '50'],
            ['volunteer.org.span_rows', 'volunteer_org', 'عدد صفوف نطاق الإشراف', 'number', '30'],
            ['volunteer.analytics.capacity_rows', 'volunteer_analytics', 'عدد صفوف تقرير السعة', 'number', '20'],
            ['volunteer.analytics.top_rows', 'volunteer_analytics', 'عدد صفوف قوائم «الأعلى» في التحليلات', 'number', '10'],
            ['volunteer.offboarding.admin_rows', 'volunteer_offboarding', 'عدد صفوف جداول الخروج', 'number', '50'],
            ['volunteer.offboarding.reentry_rows', 'volunteer_offboarding', 'عدد طلبات العودة المفتوحة المعروضة', 'number', '30'],
            ['volunteer_investigation.admin_rows', 'volunteer_investigation', 'عدد صفوف جداول لجنة التحقيق', 'number', '50'],
            ['volunteer_cert.pending_scan_limit', 'volunteer_cert', 'أقصى عضويّات يفحصها كشف الاستحقاق', 'number', '200'],
            ['volunteer_cert.pending_rows', 'volunteer_cert', 'عدد المستحقّين المعروضين', 'number', '20'],
            ['volunteer_cert.issued_rows', 'volunteer_cert', 'عدد الشهادات الصادرة المعروضة', 'number', '30'],
            ['volunteer_cert.ledger_per_page', 'volunteer_cert', 'شريحة سجلّ الشهادات في كلّ تمرير تدريجيّ', 'number', '20'],
            ['volunteer_cert.export_limit', 'volunteer_cert', 'أقصى صفوف في تصدير سجلّ شهادات التطوّع CSV', 'number', '5000'],
            ['rep.admin.recent_rows', 'volunteer_rep', 'عدد حركات السلوك الأخيرة في لوحة Rep', 'number', '15'],
            ['rep.movements_rows', 'volunteer_rep', 'عدد صفوف جدول حركات Rep', 'number', '200'],
            ['recruitment.card.course_scores_shown', 'recruitment', 'عدد درجات التدريبات على كارت المرشّح', 'number', '3'],
            ['performance.vxp.rows', 'performance', 'عدد صفوف لوحة VXP', 'number', '50'],

            // ---------------- نصوص ساحات الحروب ورسائلها — كانت محروقة في المتحكّم
            ['wars.arena.knowledge.headline', 'gamification_wars', 'عنوان ساحة حرب المعلومات', 'string', 'ساحة الحرب'],
            ['wars.arena.knowledge.tagline', 'gamification_wars', 'سطر ساحة حرب المعلومات', 'text', 'اختبر مهاراتك الذهنية والسرعة، وواجه خصمك وجهًا لوجه!'],
            ['wars.arena.survival.headline', 'gamification_wars', 'عنوان ساحة البقاء', 'string', 'ساحة البقاء'],
            ['wars.arena.survival.tagline', 'gamification_wars', 'سطر ساحة البقاء', 'text', 'جاوب صح وابقى… أول غلطة تخرجك!'],
            ['wars.arena.estimation.headline', 'gamification_wars', 'عنوان ساحة التقدير', 'string', 'ساحة التقدير'],
            ['wars.arena.estimation.tagline', 'gamification_wars', 'سطر ساحة التقدير', 'text', 'قدّر الرقم الأقرب للصح واكسب!'],
            ['wars.arena.focus.headline', 'gamification_wars', 'عنوان ساحة التركيز', 'string', 'ساحة التركيز'],
            ['wars.arena.focus.tagline', 'gamification_wars', 'سطر ساحة التركيز', 'text', 'عمل عميق بلا مقاطعة — والعدّ مبنيّ على أمانتك.'],
            ['wars.messages.ready', 'gamification_wars', 'رسالة الاستعداد', 'string', 'إنت دلوقتي مستعدّ ⚔️'],
            ['wars.messages.ready_cancelled', 'gamification_wars', 'رسالة إلغاء الاستعداد', 'string', 'اتلغى استعدادك — ارجع للساحة وقت ما تحبّ.'],
            ['wars.messages.withdrew_match', 'gamification_wars', 'رسالة الانسحاب من المواجهة', 'string', 'انسحبت من المواجهة — والخصم كسبها.'],
            ['wars.messages.withdrew_penalty', 'gamification_wars', 'رسالة الانسحاب المكلِّف', 'string', 'انسحبت — والانسحاب بيكلّف، خلّي بالك المرّة الجاية.'],
            ['wars.messages.not_your_match', 'gamification_wars', 'رسالة مواجهة ليست لك', 'string', 'دي مواجهة ناس تانية.'],
            ['wars.messages.focus_started', 'gamification_wars', 'رسالة بدء حرب التركيز', 'string', 'التحدّي بدأ — ركّز وإحنا معاك 🧘'],
            ['wars.messages.focus_joined', 'gamification_wars', 'رسالة الانضمام لحرب التركيز', 'string', 'انضممت — تذكرتك راحت لصاحب التحدّي 🎟️'],
            ['wars.messages.focus_cancelled', 'gamification_wars', 'رسالة إلغاء حرب التركيز', 'string', 'اتلغى التحدّي ✓'],
            ['wars.messages.focus_cancelled_refund', 'gamification_wars', 'رسالة الإلغاء مع ردّ التذاكر (:n للعدد)', 'string', 'اتلغى التحدّي ورجعت :n تذكرة للمنضمّين ✓'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::query()->firstOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
            ]);
        }
    }
}
