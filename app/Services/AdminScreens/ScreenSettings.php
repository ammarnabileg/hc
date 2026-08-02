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
            'question_bank.exam_question_cap' => [self::SCREEN_BANK, 'exams', 'سقف أسئلة الامتحان النهائيّ', 'number', '20', 'العدد الذي يُبنى منه الامتحان — والبنك ينبّه لو الأسئلة العامّة أقلّ منه.', false],
            'question_bank.general_minimum' => [self::SCREEN_BANK, 'exams', 'الحدّ الأدنى من الأسئلة العامّة', 'number', '20', 'أقلّ منه = تحذير أحمر خافت أعلى الجدول.', false],
            'question_bank.default_pass_score' => [self::SCREEN_BANK, 'exams', 'درجة النجاح الافتراضيّة (%)', 'number', '60', 'يرثها كلّ امتحان جديد.', false],
            'question_bank.shuffle_questions' => [self::SCREEN_BANK, 'exams', 'ترتيب عشوائيّ للأسئلة', 'bool', '1', 'يمنع حفظ ترتيب الإجابات بين الطلّاب.', false],
            'question_bank.shuffle_options' => [self::SCREEN_BANK, 'exams', 'ترتيب عشوائيّ للخيارات', 'bool', '1', '', false],
            'question_bank.server_side_grading' => [self::SCREEN_BANK, 'exams', 'التصحيح على الخادم', 'bool', '1', 'إيقافه يكشف الإجابات للمتصفّح — لا تُوقفه إلّا لسببٍ تعرفه.', false],
            'question_bank.low_general_warning' => [self::SCREEN_BANK, 'exams', 'تحذير نقص الأسئلة العامّة', 'bool', '1', '', false],
            'question_bank.import_enabled' => [self::SCREEN_BANK, 'exams', 'تفعيل استيراد CSV', 'bool', '1', 'إيقافه يخفي زرّ الاستيراد لمن يملك صلاحيّته.', false],
            'question_bank.import_max_kb' => [self::SCREEN_BANK, 'exams', 'أقصى حجم ملفّ الاستيراد (كيلوبايت)', 'number', '2048', '', false],
            'question_bank.import_columns' => [self::SCREEN_BANK, 'exams', 'أعمدة قالب CSV', 'string', 'lesson_id,type,prompt,placeholder,options,correct_answer,is_general,difficulty', 'ترتيب الأعمدة المتوقَّع في أوّل صفّ من الملفّ.', false],
            'question_bank.per_page' => [self::SCREEN_BANK, 'exams', 'عدد صفوف الصفحة', 'number', '20', '', false],
            'question_bank.difficulties' => [self::SCREEN_BANK, 'exams', 'مستويات الصعوبة', 'lines', '{"easy":"سهل","medium":"متوسّط","hard":"صعب"}', 'سطر لكلّ مستوى بصيغة: المفتاح = اللافتة.', false],
            'question_bank.types' => [self::SCREEN_BANK, 'exams', 'أنواع الأسئلة', 'lines', '{"choice":"اختيار متعدّد","text":"نصّيّ","otp":"رقميّ OTP"}', 'سطر لكلّ نوع بصيغة: المفتاح = اللافتة.', false],
            'question_bank.low_warning_text' => [self::SCREEN_BANK, 'exams', 'نصّ تحذير نقص الأسئلة', 'text', 'الأسئلة العامّة أقلّ من سقف الامتحان — زوّد البنك قبل ما تنشر امتحانًا.', '', false],
            'question_bank.empty_text' => [self::SCREEN_BANK, 'exams', 'نصّ الحالة الفارغة', 'text', 'لسّه مافيش أسئلة في البنك — ابدأ بسؤال واحد وهيكبر معاك.', '', false],

            // ------------------------------------------------ الريفيرال والسفراء (24.2)
            'referral_admin.per_page' => [self::SCREEN_REFERRAL, 'growth', 'عدد صفوف الصفحة', 'number', '20', '', false],
            'referral_admin.default_range_days' => [self::SCREEN_REFERRAL, 'growth', 'المدى الافتراضيّ (أيّام)', 'number', '30', 'الشاشة تفتح على آخر 30 يومًا (2.15-أ-11).', false],
            'referral_admin.require_admin_approval' => [self::SCREEN_REFERRAL, 'growth', 'الصرف بموافقة الأدمن', 'bool', '1', 'شرط الدستور: استكمال بيانات المدعوّ + موافقة الأدمن.', false],
            'referral_admin.require_complete_profile' => [self::SCREEN_REFERRAL, 'growth', 'اشتراط استكمال بيانات المدعوّ', 'bool', '1', 'حساب غير مفعَّل = مكافأة لا تُصرَف.', false],
            'referral_admin.max_invites_per_user' => [self::SCREEN_REFERRAL, 'growth', 'حدّ الدعوات للمستخدم الواحد', 'number', '0', 'صفر = بلا حدّ.', false],
            'referral_admin.flag_threshold' => [self::SCREEN_REFERRAL, 'growth', 'عتبة تنبيه التكرار المشبوه', 'number', '5', 'كم دعوة في اليوم من داعٍ واحد تُعتبَر مؤشّرًا يستحقّ التدقيق.', false],
            'referral_admin.paid_text' => [self::SCREEN_REFERRAL, 'growth', 'نصّ نجاح الصرف', 'string', 'اتصرفت المكافأة ✓', '', false],
            'referral_admin.hold_text' => [self::SCREEN_REFERRAL, 'growth', 'نصّ التعليق', 'string', 'اتعلّقت المكافأة لحدّ ما تراجعها.', '', false],
            'referral_admin.empty_text' => [self::SCREEN_REFERRAL, 'growth', 'نصّ الحالة الفارغة', 'text', 'لسّه مافيش دعوات في المدى ده — جرّب مدى أوسع.', '', false],
            // 🔒 الحقل الماليّ في المجموعة المحميّة (12.7) — لمالك المنصّة وحده
            'referral_admin.commission_percent' => [self::SCREEN_REFERRAL, 'finance', '🔒 عمولة الشحن مدى الحياة (%)', 'number', '7', 'حقل ماليّ — لمالك المنصّة وحده.', true],
            'referral_admin.commission_visible' => [self::SCREEN_REFERRAL, 'finance', '🔒 إظهار عمود العمولة', 'bool', '1', 'حتّى لو أُظهِر فلن يراه إلّا مالك المنصّة.', true],

            // ------------------------------------------------ التقارير المجدولة (24.3-خامسًا)
            'report_schedules.default_hour' => [self::SCREEN_REPORTS, 'stats', 'ساعة الإرسال الافتراضيّة', 'number', '7', '7 = السابعة صباحًا بتوقيت الجدولة.', false],
            'report_schedules.default_timezone' => [self::SCREEN_REPORTS, 'stats', 'المنطقة الزمنيّة الافتراضيّة', 'string', 'Africa/Cairo', '', false],
            'report_schedules.default_period_days' => [self::SCREEN_REPORTS, 'stats', 'مدى التقرير الافتراضيّ (أيّام)', 'number', '30', '', false],
            'report_schedules.max_attachment_kb' => [self::SCREEN_REPORTS, 'stats', 'أقصى حجم مرفق (كيلوبايت)', 'number', '10240', 'الأكبر منه يُرسَل كرابط تنزيل مؤقّت بدل مرفق.', false],
            'report_schedules.download_link_hours' => [self::SCREEN_REPORTS, 'stats', 'صلاحيّة رابط التنزيل (ساعات)', 'number', '72', '', false],
            'report_schedules.retry_attempts' => [self::SCREEN_REPORTS, 'stats', 'محاولات إعادة الإرسال عند الفشل', 'number', '3', '', false],
            'report_schedules.notify_admin_on_failure' => [self::SCREEN_REPORTS, 'stats', 'تنبيه الأدمن عند الفشل', 'bool', '1', '', false],
            'report_schedules.export_row_limit' => [self::SCREEN_REPORTS, 'stats', 'حدّ صفوف التصدير', 'number', '50000', '', false],
            'report_schedules.log_keep_days' => [self::SCREEN_REPORTS, 'stats', 'مدّة حفظ سجلّ الإرسال (أيّام)', 'number', '180', '', false],
            'report_schedules.subject_template' => [self::SCREEN_REPORTS, 'stats', 'عنوان رسالة التقرير', 'string', 'تقرير :name — :date', 'المتغيّرات: :name · :date · :tab', false],
            'report_schedules.body_template' => [self::SCREEN_REPORTS, 'stats', 'نصّ رسالة التقرير', 'text', "تقرير «:name» عن آخر :days يوم.\nعدد الصفوف: :rows", 'المتغيّرات: :name · :days · :rows · :date', false],
            'report_schedules.empty_text' => [self::SCREEN_REPORTS, 'stats', 'نصّ الحالة الفارغة', 'text', 'مافيش تقارير مجدولة — ابعت تقريرك الأوّل تلقائيًّا.', '', false],
            'report_schedules.frequencies' => [self::SCREEN_REPORTS, 'stats', 'التكرارات المتاحة', 'lines', '{"daily":"يوميّ","weekly":"أسبوعيّ","monthly":"شهريّ"}', 'سطر لكلّ تكرار بصيغة: المفتاح = اللافتة.', false],
            'report_schedules.formats' => [self::SCREEN_REPORTS, 'stats', 'الصيغ المتاحة', 'lines', '{"csv":"CSV","xlsx":"Excel","pdf":"PDF"}', 'سطر لكلّ صيغة بصيغة: المفتاح = اللافتة.', false],

            // ------------------------------------------------ مرآة الاجتماعات الإداريّة (24.2-أوّلًا)
            'admin_meetings.per_page' => [self::SCREEN_MEETINGS, 'meetings', 'عدد صفوف الصفحة', 'number', '20', '', false],
            'admin_meetings.default_range_days' => [self::SCREEN_MEETINGS, 'meetings', 'المدى الافتراضيّ (أيّام)', 'number', '30', '', false],
            'admin_meetings.default_window_hours' => [self::SCREEN_MEETINGS, 'meetings', 'نافذة تسجيل الحضور الافتراضيّة (ساعات)', 'number', '12', 'تُفتَح لحظة «إنهاء الاجتماع».', false],
            'admin_meetings.max_window_hours' => [self::SCREEN_MEETINGS, 'meetings', 'سقف نافذة تسجيل الحضور (ساعات)', 'number', '48', '', false],
            'admin_meetings.exceptional_reason_required' => [self::SCREEN_MEETINGS, 'meetings', 'إلزام سبب الحضور الاستثنائيّ', 'bool', '1', 'المنح بلا سبب أثرٌ لا يُراجَع.', false],
            'admin_meetings.freeze_windows_in_maintenance' => [self::SCREEN_MEETINGS, 'meetings', 'تجميد النوافذ في وضع الصيانة', 'bool', '1', '', false],
            'admin_meetings.empty_text' => [self::SCREEN_MEETINGS, 'meetings', 'نصّ الحالة الفارغة', 'text', 'مافيش اجتماعات في النطاق ده.', '', false],
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
