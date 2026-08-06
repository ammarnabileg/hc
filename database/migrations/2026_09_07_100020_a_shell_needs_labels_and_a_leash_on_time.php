<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 🧩 المطوّرين — الطرفيّة: كلّ نصّ ورقم ظاهر في تاب Terminal عبر `setting()`
 * لا محروقًا (2.13-أ)، **في هجرة لا سيدرٍ وحده** لأنّ الإنتاج لا يشغّل
 * السيدرات — نفس أسلوب هجرتَي إعدادات تابَي API وWebhooks.
 *
 * ثلاث مجموعات:
 * 1) `developers.terminal.*` — القيم التشغيليّة المنصوصة حرفيًّا في 12.15-و:
 *    تفعيل التاب · مهلة التنفيذ بالثواني · حدّ أسطر المخرَجات · مدّة الاحتفاظ
 *    بالسجلّ — زائد رسالة تجاوز المهلة التي يستخدمها `TerminalService`.
 * 2) `developers.admin.terminal_*` — كلّ نصّ في شاشة الأدمن (التحذير · الفورم ·
 *    منطقة المخرَجات · جدول السجلّ).
 * 3) `nav.admin.item_developers_terminal` — لافتة بند السايد بار الثالث تحت
 *    «🧩 المطوّرين»، بنفس مجموعة لافتات `nav` القابلة للتعديل (2.13-ب).
 *
 * ⛔ **لا صلاحيّة جديدة هنا ولا في أيّ مكانٍ آخر لهذا التاب (12.15-هـ):** الوصول
 * `isPlatformOwner()` مباشرةً لا `permission:` — الدستور صريح: «مالك المنصّة
 * حصرًا — لا صلاحيّة تُمنَح لأيّ دورٍ آخر، ولا استثناء».
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---- 1) القيم التشغيليّة (12.15-و)
        ['developers.terminal.enabled', 'developers', 'تفعيل تاب الطرفيّة (Terminal)', 'bool', '1'],
        ['developers.terminal.timeout_seconds', 'developers', 'مهلة تنفيذ الأمر بالثواني', 'number', '60'],
        ['developers.terminal.max_output_lines', 'developers', 'أقصى عدد أسطر مخرَجات مُعروضة', 'number', '500'],
        ['developers.terminal.log_retention_count', 'developers', 'عدد أوامر سجلّ الطرفيّة المحتفَظ بها', 'number', '200'],
        ['developers.terminal.timeout_msg', 'developers', 'رسالة تجاوز مهلة تنفيذ الأمر', 'string', 'الأمر تجاوز المهلة المسموحة وأُوقِف.'],

        // ---- 2) شاشة الأدمن — تاب الطرفيّة
        ['developers.admin.tab_terminal', 'developers', 'عنوان تاب الطرفيّة', 'string', 'الطرفيّة'],
        ['developers.admin.terminal_warning_title', 'developers', 'عنوان تحذير الطرفيّة', 'string', 'تحذير: تنفيذٌ مباشر على الخادم'],
        ['developers.admin.terminal_warning_body', 'developers', 'نصّ تحذير الطرفيّة', 'string', 'أيّ أمرٍ هنا يُنفَّذ مباشرةً على الخادم — لا قيود ولا تراجع، وكلّ أمرٍ مسجَّل.'],
        ['developers.admin.terminal_field_command', 'developers', 'حقل: نصّ الأمر', 'string', 'الأمر'],
        ['developers.admin.terminal_command_placeholder', 'developers', 'نصّ توضيحيّ لحقل الأمر', 'string', 'اكتب أمر الطرفيّة هنا…'],
        ['developers.admin.terminal_run_cta', 'developers', 'زرّ تنفيذ الأمر', 'string', 'تنفيذ'],
        ['developers.admin.terminal_running_label', 'developers', 'نصّ أثناء تنفيذ الأمر', 'string', 'جارٍ التنفيذ…'],
        ['developers.admin.terminal_output_title', 'developers', 'عنوان منطقة المخرَجات', 'string', 'المخرَجات'],
        ['developers.admin.terminal_output_empty', 'developers', 'نصّ منطقة المخرَجات قبل أوّل تنفيذ', 'string', 'لا مخرَجات بعد — نفّذ أمرًا لعرضها هنا.'],
        ['developers.admin.terminal_exit_code_label', 'developers', 'تسمية كود الخروج بعد التنفيذ', 'string', 'كود الخروج'],
        ['developers.admin.terminal_duration_label', 'developers', 'تسمية مدّة التنفيذ', 'string', 'المدّة'],
        ['developers.admin.terminal_error_generic', 'developers', 'رسالة فشل إرسال الأمر (خطأ شبكة/خادم)', 'string', 'تعذّر تنفيذ الأمر — حاول ثانيةً.'],
        ['developers.admin.terminal_disabled_msg', 'developers', 'رسالة تعطيل تاب الطرفيّة', 'string', 'تاب الطرفيّة معطَّل حاليًّا من الإعدادات.'],

        // ---- جدول سجلّ الأوامر (آخر 200)
        ['developers.admin.terminal_log_title', 'developers', 'عنوان جدول سجلّ الأوامر', 'string', 'سجلّ الأوامر (آخر 200)'],
        ['developers.admin.terminal_log_empty', 'developers', 'الحالة الفارغة لسجلّ الأوامر', 'string', 'لا أوامر منفَّذة بعد.'],
        ['developers.admin.terminal_col_user', 'developers', 'عمود سجلّ الأوامر: مَن نفّذ', 'string', 'مَن نفّذ'],
        ['developers.admin.terminal_col_command', 'developers', 'عمود سجلّ الأوامر: الأمر', 'string', 'الأمر'],
        ['developers.admin.terminal_col_exit_code', 'developers', 'عمود سجلّ الأوامر: كود الخروج', 'string', 'كود الخروج'],
        ['developers.admin.terminal_col_duration', 'developers', 'عمود سجلّ الأوامر: المدّة', 'string', 'المدّة'],
        ['developers.admin.terminal_col_time', 'developers', 'عمود سجلّ الأوامر: الوقت', 'string', 'الوقت'],
        ['developers.admin.terminal_view_output_cta', 'developers', 'زرّ عرض مخرَجات صفّ في السجلّ', 'string', 'عرض المخرَجات'],

        // ---- لافتة بند السايد بار الثالث (12.0)
        ['nav.admin.item_developers_terminal', 'nav', 'لافتة بند السايد بار: الطرفيّة', 'string', 'الطرفيّة'],
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
