<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إخراج القيم المحروقة إلى إعدادات (2.13).
 *
 * كانت هذه الأرقام والنصوص مكتوبةً في الكود: عتبات رادار الإنجازات وعناوينه،
 * وقاموس الحالة (الرمز والتسمية لكلّ لون). ولأنّ التنصيبات القائمة لن تُعاد
 * زراعتها، نزرع الصفوف الناقصة هنا كي تظهر الشاشة كاملةً بعد الترقية مباشرة.
 *
 * `insertOrIgnore` عمدًا: لا نلمس قيمةً عدّلها المالك بالفعل.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // قاموس الحالة (2.16) — لا لون بلا رمز، ولا رمز محروق في الكود
        ['ux.state.ok.icon', 'ux', 'رمز حالة «سليم»', 'string', '●'],
        ['ux.state.ok.label', 'ux', 'تسمية حالة «سليم»', 'string', 'سليم'],
        ['ux.state.warn.icon', 'ux', 'رمز حالة «انتبه»', 'string', '▲'],
        ['ux.state.warn.label', 'ux', 'تسمية حالة «انتبه»', 'string', 'انتبه'],
        ['ux.state.danger.icon', 'ux', 'رمز حالة «خطر»', 'string', '◉'],
        ['ux.state.danger.label', 'ux', 'تسمية حالة «خطر»', 'string', 'خطر'],
        ['ux.state.honor.icon', 'ux', 'رمز حالة «تميّز»', 'string', '★'],
        ['ux.state.honor.label', 'ux', 'تسمية حالة «تميّز»', 'string', 'تميّز'],
        ['ux.state.idle.icon', 'ux', 'رمز حالة «غير نشط»', 'string', '○'],
        ['ux.state.idle.label', 'ux', 'تسمية حالة «غير نشط»', 'string', 'غير نشط'],

        ['ux.settings_search.max_results', 'ux', 'أقصى نتائج البحث الموحّد في الإعدادات', 'number', '40'],

        // رادار الإنجازات (10.1) — مصدر واحد يخدم لوحة المتدرّب وتاب البروفايل
        ['dashboard.achievements.tracks', 'dashboard', 'مسارات الإنجاز وترتيبها', 'json', '["account","club_5am","referrals","tickets","learning"]'],
        ['dashboard.achievements.radar_max_level', 'dashboard', 'أقصى مستوى يرسمه الرادار', 'number', '6'],
        ['dashboard.achievements.account.label', 'dashboard', 'عنوان مسار مستوى الحساب', 'string', 'مستوى الحساب'],
        ['dashboard.achievements.account.unit', 'dashboard', 'وحدة مسار مستوى الحساب', 'string', 'XP'],
        ['dashboard.achievements.account.base', 'dashboard', 'عتبة مستوى الحساب — الأساس', 'number', '500'],
        ['dashboard.achievements.account.step', 'dashboard', 'عتبة مستوى الحساب — الزيادة', 'number', '250'],
        ['dashboard.achievements.club_5am.label', 'dashboard', 'عنوان مسار نادي الخامسة', 'string', 'نادي الخامسة صباحًا'],
        ['dashboard.achievements.club_5am.unit', 'dashboard', 'وحدة مسار نادي الخامسة', 'string', 'يوم'],
        ['dashboard.achievements.club_5am.base', 'dashboard', 'عتبة نادي الخامسة — الأساس', 'number', '3'],
        ['dashboard.achievements.club_5am.step', 'dashboard', 'عتبة نادي الخامسة — الزيادة', 'number', '2'],
        ['dashboard.achievements.referrals.label', 'dashboard', 'عنوان مسار الدعوات', 'string', 'دعوة الأصدقاء'],
        ['dashboard.achievements.referrals.unit', 'dashboard', 'وحدة مسار الدعوات', 'string', 'دعوة'],
        ['dashboard.achievements.referrals.base', 'dashboard', 'عتبة الدعوات — الأساس', 'number', '5'],
        ['dashboard.achievements.referrals.step', 'dashboard', 'عتبة الدعوات — الزيادة', 'number', '2'],
        ['dashboard.achievements.tickets.label', 'dashboard', 'عنوان مسار التذاكر', 'string', 'التذاكر'],
        ['dashboard.achievements.tickets.unit', 'dashboard', 'وحدة مسار التذاكر', 'string', 'تذكرة'],
        ['dashboard.achievements.tickets.base', 'dashboard', 'عتبة التذاكر — الأساس', 'number', '15'],
        ['dashboard.achievements.tickets.step', 'dashboard', 'عتبة التذاكر — الزيادة', 'number', '10'],
        ['dashboard.achievements.learning.label', 'dashboard', 'عنوان مسار استمراريّة التعلّم', 'string', 'استمراريّة التعلّم'],
        ['dashboard.achievements.learning.unit', 'dashboard', 'وحدة مسار استمراريّة التعلّم', 'string', 'درس'],
        ['dashboard.achievements.learning.base', 'dashboard', 'عتبة استمراريّة التعلّم — الأساس', 'number', '5'],
        ['dashboard.achievements.learning.step', 'dashboard', 'عتبة استمراريّة التعلّم — الزيادة', 'number', '3'],
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
        DB::table('settings')
            ->whereIn('key', array_column(self::ROWS, 0))
            ->delete();
    }
};
