<?php

namespace App\Services\AdminScreens;

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Cache;

/**
 * كتالوج إعدادات الشاشات الأربع الناقصة من القسم 24
 * (بنك الأسئلة · الريفيرال والسفراء · التقارير المجدولة · مرآة الاجتماعات).
 *
 * 🏆 القاعدة الذهبيّة (2.13): لا رقم ولا نصّ محروق في كود هذه الشاشات —
 * وكلّ مفتاح هنا له **شاشة يعدّله منها الأدمن** داخل بلوك إعدادات شاشته،
 * وله **افتراضيّ ظاهر كمرساة** و**Reset**. والكتالوج مغلق: أيّ مفتاح خارجه
 * لا يُكتَب، منعًا لتلويث جدول الإعدادات من فورم مزوَّر.
 */
class ScreenSettings
{
    /** الشاشات — كلّ شاشة تقرأ مفاتيحها وحدها في بلوك إعداداتها */
    public const SCREEN_BANK = 'bank';

    public const SCREEN_REFERRAL = 'referral';

    public const SCREEN_REPORTS = 'reports';

    public const SCREEN_MEETINGS = 'meetings';

    /**
     * المفتاح ⟵ [الشاشة، **مجموعة الإعدادات**، اللافتة، النوع، الافتراضيّ، الشرح، لمالك المنصّة وحده؟].
     *
     * لماذا الشاشة والمجموعة منفصلتان؟ لأنّ الشاشة تحدّد **أين يعدّله الأدمن**،
     * أمّا المجموعة فتحدّد **تحت أيّ تاب يظهر في شاشة الإعدادات الموحّدة** —
     * ولذلك تُسنَد المفاتيح لمجموعات مسجَّلة سلفًا (`exams` · `growth` · `stats`
     * · `meetings`) فلا تصير مجموعةً يتيمة بلا شاشة (2.13).
     * 🔒 والحقول الماليّة تُسنَد لمجموعة `finance` المحميّة مهما كانت شاشتها.
     *
     * @return array<string,array{0:string,1:string,2:string,3:string,4:string,5:string,6:bool}>
     */
    public static function catalog(): array
    {
        return [
            // ------------------------------------------------ بنك الأسئلة (24.1-3)
            'question_bank.exam_question_cap' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_1', 'سقف أسئلة الامتحان النهائيّ'), 'number', '20', setting('exams.screen_settings.catalog_2', 'العدد الذي يُبنى منه الامتحان — والبنك ينبّه لو الأسئلة العامّة أقلّ منه.'), false],
            'question_bank.general_minimum' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_3', 'الحدّ الأدنى من الأسئلة العامّة'), 'number', '20', setting('exams.screen_settings.catalog_4', 'أقلّ منه = تحذير أحمر خافت أعلى الجدول.'), false],
            'question_bank.default_pass_score' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_5', 'درجة النجاح الافتراضيّة (%)'), 'number', '60', setting('exams.screen_settings.catalog_6', 'يرثها كلّ امتحان جديد.'), false],
            'question_bank.shuffle_questions' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_7', 'ترتيب عشوائيّ للأسئلة'), 'bool', '1', setting('exams.screen_settings.catalog_8', 'يمنع حفظ ترتيب الإجابات بين الطلّاب.'), false],
            'question_bank.shuffle_options' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_9', 'ترتيب عشوائيّ للخيارات'), 'bool', '1', '', false],
            'question_bank.server_side_grading' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_10', 'التصحيح على الخادم'), 'bool', '1', setting('exams.screen_settings.catalog_11', 'إيقافه يكشف الإجابات للمتصفّح — لا تُوقفه إلّا لسببٍ تعرفه.'), false],
            'question_bank.low_general_warning' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_12', 'تحذير نقص الأسئلة العامّة'), 'bool', '1', '', false],
            'question_bank.import_enabled' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_13', 'تفعيل استيراد CSV'), 'bool', '1', setting('exams.screen_settings.catalog_14', 'إيقافه يخفي زرّ الاستيراد لمن يملك صلاحيّته.'), false],
            'question_bank.import_max_kb' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_15', 'أقصى حجم ملفّ الاستيراد (كيلوبايت)'), 'number', '2048', '', false],
            'question_bank.import_columns' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_16', 'أعمدة قالب CSV'), 'string', 'lesson_id,type,prompt,placeholder,options,correct_answer,is_general,difficulty', setting('exams.screen_settings.catalog_17', 'ترتيب الأعمدة المتوقَّع في أوّل صفّ من الملفّ.'), false],
            'question_bank.per_page' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_18', 'عدد صفوف الصفحة'), 'number', '20', '', false],
            'question_bank.difficulties' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_19', 'مستويات الصعوبة'), 'lines', setting('exams.screen_settings.catalog_20', '{"easy":"سهل","medium":"متوسّط","hard":"صعب"}'), setting('exams.screen_settings.catalog_21', 'سطر لكلّ مستوى بصيغة: المفتاح = اللافتة.'), false],
            'question_bank.types' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_22', 'أنواع الأسئلة'), 'lines', setting('exams.screen_settings.catalog_23', '{"choice":"اختيار متعدّد","text":"نصّيّ","otp":"رقميّ OTP"}'), setting('exams.screen_settings.catalog_24', 'سطر لكلّ نوع بصيغة: المفتاح = اللافتة.'), false],
            'question_bank.low_warning_text' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_25', 'نصّ تحذير نقص الأسئلة'), 'text', setting('exams.screen_settings.catalog_26', 'الأسئلة العامّة أقلّ من سقف الامتحان — زوّد البنك قبل ما تنشر امتحانًا.'), '', false],
            'question_bank.empty_text' => [self::SCREEN_BANK, 'exams', setting('exams.screen_settings.catalog_27', 'نصّ الحالة الفارغة'), 'text', setting('exams.screen_settings.catalog_28', 'لسّه مافيش أسئلة في البنك — ابدأ بسؤال واحد وهيكبر معاك.'), '', false],

            // ------------------------------------------------ الريفيرال والسفراء (24.2)
            'referral_admin.per_page' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_29', 'عدد صفوف الصفحة'), 'number', '20', '', false],
            'referral_admin.default_range_days' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_30', 'المدى الافتراضيّ (أيّام)'), 'number', '30', setting('growth.screen_settings.catalog_31', 'الشاشة تفتح على آخر 30 يومًا (2.15-أ-11).'), false],
            'referral_admin.require_admin_approval' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_32', 'الصرف بموافقة الأدمن'), 'bool', '1', setting('growth.screen_settings.catalog_33', 'شرط الدستور: استكمال بيانات المدعوّ + موافقة الأدمن.'), false],
            'referral_admin.require_complete_profile' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_34', 'اشتراط استكمال بيانات المدعوّ'), 'bool', '1', setting('growth.screen_settings.catalog_35', 'حساب غير مفعَّل = مكافأة لا تُصرَف.'), false],
            'referral_admin.max_invites_per_user' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_36', 'حدّ الدعوات للمستخدم الواحد'), 'number', '0', setting('growth.screen_settings.catalog_37', 'صفر = بلا حدّ.'), false],
            'referral_admin.flag_threshold' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_38', 'عتبة تنبيه التكرار المشبوه'), 'number', '5', setting('growth.screen_settings.catalog_39', 'كم دعوة في اليوم من داعٍ واحد تُعتبَر مؤشّرًا يستحقّ التدقيق.'), false],
            'referral_admin.paid_text' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_40', 'نصّ نجاح الصرف'), 'string', setting('growth.screen_settings.catalog_41', 'اتصرفت المكافأة ✓'), '', false],
            'referral_admin.hold_text' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_42', 'نصّ التعليق'), 'string', setting('growth.screen_settings.catalog_43', 'اتعلّقت المكافأة لحدّ ما تراجعها.'), '', false],
            'referral_admin.empty_text' => [self::SCREEN_REFERRAL, 'growth', setting('growth.screen_settings.catalog_44', 'نصّ الحالة الفارغة'), 'text', setting('growth.screen_settings.catalog_45', 'لسّه مافيش دعوات في المدى ده — جرّب مدى أوسع.'), '', false],
            // 🔒 الحقل الماليّ في المجموعة المحميّة (12.7) — لمالك المنصّة وحده
            'referral_admin.commission_percent' => [self::SCREEN_REFERRAL, 'finance', setting('finance.screen_settings.catalog_46', '🔒 عمولة الشحن مدى الحياة (%)'), 'number', '7', setting('finance.screen_settings.catalog_47', 'حقل ماليّ — لمالك المنصّة وحده.'), true],
            'referral_admin.commission_visible' => [self::SCREEN_REFERRAL, 'finance', setting('finance.screen_settings.catalog_48', '🔒 إظهار عمود العمولة'), 'bool', '1', setting('finance.screen_settings.catalog_49', 'حتّى لو أُظهِر فلن يراه إلّا مالك المنصّة.'), true],

            // ------------------------------------------------ التقارير المجدولة (24.3-خامسًا)
            'report_schedules.default_hour' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_50', 'ساعة الإرسال الافتراضيّة'), 'number', '7', setting('stats.screen_settings.catalog_51', '7 = السابعة صباحًا بتوقيت الجدولة.'), false],
            'report_schedules.default_timezone' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_52', 'المنطقة الزمنيّة الافتراضيّة'), 'string', 'Africa/Cairo', '', false],
            'report_schedules.default_period_days' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_53', 'مدى التقرير الافتراضيّ (أيّام)'), 'number', '30', '', false],
            'report_schedules.max_attachment_kb' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_54', 'أقصى حجم مرفق (كيلوبايت)'), 'number', '10240', setting('stats.screen_settings.catalog_55', 'الأكبر منه يُرسَل كرابط تنزيل مؤقّت بدل مرفق.'), false],
            'report_schedules.download_link_hours' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_56', 'صلاحيّة رابط التنزيل (ساعات)'), 'number', '72', setting('stats.screen_settings.catalog_57', 'بعدها يتشال الملفّ نفسه من الخادم لا الرابط وحده.'), false],
            'report_schedules.download_folder' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_58', 'مجلّد ملفّات التنزيل المؤقّت'), 'string', 'reports', setting('stats.screen_settings.catalog_59', 'داخل التخزين الخاصّ — مش المجلّد العامّ.'), false],
            'report_schedules.download_body_template' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_60', 'نصّ سطر رابط التنزيل'), 'text', setting('stats.screen_settings.catalog_61', 'الملفّ أكبر من حدّ المرفق (:size ميجابايت)، فرفعناه على رابط تنزيل مؤقّت:\\n:url\\nالرابط شغّال :hours ساعة، وبعدها يتشال. لو خلصت مدّته اضغط «شغّل الآن» من شاشة التقارير المجدولة.'), setting('stats.screen_settings.catalog_62', 'المتغيّرات: :url · :hours · :size'), false],
            'report_schedules.xlsx_row_limit' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_63', 'سقف صفوف ملفّ Excel'), 'number', '20000', setting('stats.screen_settings.catalog_64', 'الملفّ الذي لا يُفتَح لا ينفع أحدًا.'), false],
            'report_schedules.pdf_row_limit' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_65', 'سقف صفوف ملفّ PDF'), 'number', '500', setting('stats.screen_settings.catalog_66', 'الـPDF للقراءة لا للتحليل — الأكبر منه يُصدَّر CSV أو Excel.'), false],
            'report_schedules.pdf_headline' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_67', 'سطر ترويسة ملفّ PDF'), 'string', setting('stats.screen_settings.catalog_68', 'عن آخر :days يوم · :rows صفًّا · :date'), setting('stats.screen_settings.catalog_69', 'المتغيّرات: :days · :rows · :date'), false],
            'report_schedules.pdf_empty_line' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_70', 'سطر PDF الفاضي'), 'string', setting('stats.screen_settings.catalog_71', 'مافيش بيانات في المدى ده.'), '', false],
            'report_schedules.pdf_truncated_line' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_72', 'سطر تنبيه اقتطاع PDF'), 'string', setting('stats.screen_settings.catalog_73', 'معروض أوّل :shown صفًّا من :total — الملفّ الكامل بصيغة CSV.'), setting('stats.screen_settings.catalog_74', 'المتغيّرات: :shown · :total'), false],
            'report_schedules.retry_attempts' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_75', 'محاولات إعادة الإرسال عند الفشل'), 'number', '3', '', false],
            'report_schedules.notify_admin_on_failure' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_76', 'تنبيه الأدمن عند الفشل'), 'bool', '1', '', false],
            'report_schedules.export_row_limit' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_77', 'حدّ صفوف التصدير'), 'number', '50000', '', false],
            'report_schedules.log_keep_days' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_78', 'مدّة حفظ سجلّ الإرسال (أيّام)'), 'number', '180', '', false],
            'report_schedules.subject_template' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_79', 'عنوان رسالة التقرير'), 'string', setting('stats.screen_settings.catalog_80', 'تقرير :name — :date'), setting('stats.screen_settings.catalog_81', 'المتغيّرات: :name · :date · :tab'), false],
            'report_schedules.body_template' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_82', 'نصّ رسالة التقرير'), 'text', setting('stats.screen_settings.catalog_83', 'تقرير «:name» عن آخر :days يوم.\\nعدد الصفوف: :rows'), setting('stats.screen_settings.catalog_84', 'المتغيّرات: :name · :days · :rows · :date'), false],
            'report_schedules.empty_text' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_85', 'نصّ الحالة الفارغة'), 'text', setting('stats.screen_settings.catalog_86', 'مافيش تقارير مجدولة — ابعت تقريرك الأوّل تلقائيًّا.'), '', false],
            'report_schedules.frequencies' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_87', 'التكرارات المتاحة'), 'lines', setting('stats.screen_settings.catalog_88', '{"daily":"يوميّ","weekly":"أسبوعيّ","monthly":"شهريّ"}'), setting('stats.screen_settings.catalog_89', 'سطر لكلّ تكرار بصيغة: المفتاح = اللافتة.'), false],
            'report_schedules.formats' => [self::SCREEN_REPORTS, 'stats', setting('stats.screen_settings.catalog_90', 'الصيغ المتاحة'), 'lines', '{"csv":"CSV","xlsx":"Excel","pdf":"PDF"}', setting('stats.screen_settings.catalog_91', 'سطر لكلّ صيغة بصيغة: المفتاح = اللافتة.'), false],

            // ------------------------------------------------ مرآة الاجتماعات الإداريّة (24.2-أوّلًا)
            'admin_meetings.per_page' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_92', 'عدد صفوف الصفحة'), 'number', '20', '', false],
            'admin_meetings.default_range_days' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_93', 'المدى الافتراضيّ (أيّام)'), 'number', '30', '', false],
            'admin_meetings.default_window_hours' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_94', 'نافذة تسجيل الحضور الافتراضيّة (ساعات)'), 'number', '12', setting('meetings.screen_settings.catalog_95', 'تُفتَح لحظة «إنهاء الاجتماع».'), false],
            'admin_meetings.max_window_hours' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_96', 'سقف نافذة تسجيل الحضور (ساعات)'), 'number', '48', '', false],
            'admin_meetings.exceptional_reason_required' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_97', 'إلزام سبب الحضور الاستثنائيّ'), 'bool', '1', setting('meetings.screen_settings.catalog_98', 'المنح بلا سبب أثرٌ لا يُراجَع.'), false],
            'admin_meetings.freeze_windows_in_maintenance' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_99', 'تجميد النوافذ في وضع الصيانة'), 'bool', '1', '', false],
            'admin_meetings.stats_scan_limit' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_100', 'سقف الاجتماعات في حساب الكروت'), 'number', '200', setting('meetings.screen_settings.catalog_101', 'حساب «المدعوّين» يمرّ على شجرة كلّ اجتماع — والسقف يمنع بطء الشاشة.'), false],
            'admin_meetings.empty_text' => [self::SCREEN_MEETINGS, 'meetings', setting('meetings.screen_settings.catalog_102', 'نصّ الحالة الفارغة'), 'text', setting('meetings.screen_settings.catalog_103', 'مافيش اجتماعات في النطاق ده.'), '', false],
        ];
    }

    /**
     * صفوف شاشة واحدة بقيمها الحاليّة وحالة «معدَّل».
     * والحقول الماليّة **تُحذَف من العرض** لغير مالك المنصّة — تُخفى ولا تُعطَّل (2.15-أ-7).
     *
     * @return array<int,array{key:string,label:string,type:string,default:string,value:string,hint:string,modified:bool,owner_only:bool}>
     */
    public static function rows(string $screen, ?User $viewer = null): array
    {
        $stored = Setting::query()->pluck('value', 'key');
        $isOwner = $viewer?->isPlatformOwner() ?? false;
        $rows = [];

        foreach (self::catalog() as $key => [$catalogScreen, $group, $label, $type, $default, $hint, $ownerOnly]) {
            if ($catalogScreen !== $screen || ($ownerOnly && ! $isOwner)) {
                continue;
            }

            $value = (string) ($stored[$key] ?? $default);

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'default' => $type === 'lines' ? self::mapToLines($default) : $default,
                'value' => $type === 'lines' ? self::mapToLines($value) : $value,
                'hint' => $hint,
                'modified' => $value !== $default,
                'owner_only' => $ownerOnly,
            ];
        }

        return $rows;
    }

    /** قراءة خريطة من نوع «أسطر» جاهزةً للاستعمال في الفلاتر والقوائم */
    public static function map(string $key): array
    {
        $value = setting($key, null);

        if (is_array($value)) {
            return $value;
        }

        $default = self::catalog()[$key][4] ?? '{}';
        $decoded = json_decode(is_string($value) && $value !== '' ? $value : $default, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** كتابة دفعة من شاشة واحدة — ومفاتيح غيرها تُتجاهَل بصمت */
    public static function putMany(string $screen, array $values, ?User $actor = null): int
    {
        $catalog = self::catalog();
        $isOwner = $actor?->isPlatformOwner() ?? false;
        $count = 0;

        foreach ($values as $key => $value) {
            if (! isset($catalog[$key]) || $catalog[$key][0] !== $screen) {
                continue;
            }

            [$catalogScreen, $group, $label, $type, $default, $hint, $ownerOnly] = $catalog[$key];

            // 🔒 الحقل الماليّ لا يُكتَب إلّا من مالك المنصّة مهما كان شكل الطلب
            if ($ownerOnly && ! $isOwner) {
                continue;
            }

            $setting = Setting::firstOrNew(['key' => $key]);
            $old = $setting->value;

            $setting->fill([
                'group' => $group,
                'label_ar' => $label,
                'type' => $type === 'lines' ? 'json' : $type,
                'default_value' => $default,
                'hint' => $hint ?: null,
                'is_owner_only' => $ownerOnly,
                'is_sensitive' => $ownerOnly,
                'value' => self::normalize($type, $value),
            ])->save();

            AuditTrail::log($actor, 'settings.update', $setting, ['value' => $old], ['key' => $key, 'value' => $setting->value]);
            $count++;
        }

        Cache::forget('settings');

        return $count;
    }

    /** ↺ رجوع مفاتيح شاشة كاملة لافتراضيّها */
    public static function resetScreen(string $screen, ?User $actor = null): int
    {
        $defaults = [];

        foreach (self::catalog() as $key => $row) {
            if ($row[0] !== $screen) {
                continue;
            }

            $defaults[$key] = $row[3] === 'lines' ? self::mapToLines($row[4]) : $row[4];
        }

        return self::putMany($screen, $defaults, $actor);
    }

    /** JSON ⟵ أسطر «مفتاح = لافتة» يقرأها الأدمن بلا تدريب */
    public static function mapToLines(string $json): string
    {
        $map = json_decode($json, true);

        if (! is_array($map)) {
            return '';
        }

        $lines = [];

        foreach ($map as $key => $label) {
            $lines[] = $key.' = '.$label;
        }

        return implode("\n", $lines);
    }

    public static function linesToMap(string $text): string
    {
        $map = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $label] = explode('=', $line, 2);
            $key = trim($key);
            $label = trim($label);

            if ($key !== '' && $label !== '') {
                $map[$key] = $label;
            }
        }

        return json_encode($map, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private static function normalize(string $type, mixed $value): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'number' => (string) (int) $value,
            'lines' => self::linesToMap((string) $value),
            default => (string) $value,
        };
    }
}
