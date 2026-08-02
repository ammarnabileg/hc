<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الموجة الأخيرة من الأرقام المحروقة (2.13): **حدود القوائم والصفحات**
 * (`limit` · `paginate` · `take`) ونافذة عدّاد استوديو الصور وعدد أحرف الأفاتار.
 *
 * كانت هذه الأرقام مكتوبةً في المتحكّمات والخدمات وقوالب الواجهة، فالمالك
 * الذي يريد جدولًا أطول أو صفحةً أقصر لم يكن أمامه إلّا نشر كود — وهو
 * بالضبط ما تمنعه القاعدة الذهبيّة.
 *
 * كلّ افتراضيّ هنا = **القيمة التي كانت محروقة بالضبط**، فلا يتغيّر أيّ سلوك
 * بالترقية. و`insertOrIgnore` عمدًا: لا نلمس قيمةً عدّلها المالك بالفعل.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---------------- المستخدمون والاعتمادات
        ['admin.users.tab_rows', 'admin_users', 'صفوف كلّ تاب في ملفّ المستخدم', 'number', '25'],

        // ---------------- الاجتماعات
        ['meetings.list_limit', 'meetings', 'عدد الاجتماعات المعروضة في القائمة', 'number', '60'],
        ['admin_meetings.export.max_meetings', 'meetings', 'أقصى اجتماعات في تصدير الحضور', 'number', '2000'],

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

        // ---------------- بنك الأسئلة
        ['question_bank.lessons_picker_limit', 'exams', 'عدد الدروس في منتقي بنك الأسئلة', 'number', '500'],
        ['question_bank.export.max_rows', 'exams', 'أقصى صفوف تصدير بنك الأسئلة', 'number', '50000'],

        // ---------------- الدعوات والإحالة
        ['referral_admin.preview_rows', 'growth', 'عدد صفوف معاينة الدعوات', 'number', '100'],
        ['referral_admin.export.max_rows', 'growth', 'أقصى صفوف تصدير الدعوات', 'number', '50000'],

        // ---------------- التقارير المجدولة
        ['report_schedules.per_page', 'stats', 'عدد الجدولات في الصفحة', 'number', '20'],
        ['report_schedules.runs_per_page', 'stats', 'عدد صفوف سجلّ الإرسال في الصفحة', 'number', '30'],

        // ---------------- المتجر والشحن
        ['topup.admin.user_history_rows', 'store', 'عدد طلبات الشحن السابقة في شاشة المراجعة', 'number', '5'],
        ['topup.history.per_page', 'store', 'عدد طلبات الشحن في صفحة المتدرّب', 'number', '10'],

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
        ['volunteer_cert.pending_scan_limit', 'volunteer_cert', 'أقصى عضويّات يفحصها كشف الاستحقاق', 'number', '200'],
        ['volunteer_cert.pending_rows', 'volunteer_cert', 'عدد المستحقّين المعروضين', 'number', '20'],
        ['volunteer_cert.issued_rows', 'volunteer_cert', 'عدد الشهادات الصادرة المعروضة', 'number', '30'],
        ['rep.admin.recent_rows', 'volunteer_rep', 'عدد حركات السلوك الأخيرة في لوحة Rep', 'number', '15'],
        ['rep.movements_rows', 'volunteer_rep', 'عدد صفوف جدول حركات Rep', 'number', '200'],
        ['recruitment.card.course_scores_shown', 'recruitment', 'عدد درجات التدريبات على كارت المرشّح', 'number', '3'],
        ['performance.vxp.rows', 'performance', 'عدد صفوف لوحة VXP', 'number', '50'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$key, $group, $label, $type, $default]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }
};
