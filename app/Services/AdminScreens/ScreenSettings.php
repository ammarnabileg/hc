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
    /** مجموعات الكتالوج — كلّ شاشة تقرأ مجموعتها وحدها */
    public const GROUP_BANK = 'question_bank';

    public const GROUP_REFERRAL = 'referral_admin';

    public const GROUP_REPORTS = 'report_schedules';

    public const GROUP_MEETINGS = 'admin_meetings';

    /**
     * المفتاح ⟵ [المجموعة، اللافتة، النوع، الافتراضيّ، الشرح، لمالك المنصّة وحده؟].
     *
     * @return array<string,array{0:string,1:string,2:string,3:string,4:string,5:bool}>
     */
    public static function catalog(): array
    {
        return [
            // ------------------------------------------------ بنك الأسئلة (24.1-3)
            'question_bank.exam_question_cap' => [self::GROUP_BANK, 'سقف أسئلة الامتحان النهائيّ', 'number', '20', 'العدد الذي يُبنى منه الامتحان — والبنك ينبّه لو الأسئلة العامّة أقلّ منه.', false],
            'question_bank.general_minimum' => [self::GROUP_BANK, 'الحدّ الأدنى من الأسئلة العامّة', 'number', '20', 'أقلّ منه = تحذير أحمر خافت أعلى الجدول.', false],
            'question_bank.default_pass_score' => [self::GROUP_BANK, 'درجة النجاح الافتراضيّة (%)', 'number', '60', 'يرثها كلّ امتحان جديد.', false],
            'question_bank.shuffle_questions' => [self::GROUP_BANK, 'ترتيب عشوائيّ للأسئلة', 'bool', '1', 'يمنع حفظ ترتيب الإجابات بين الطلّاب.', false],
            'question_bank.shuffle_options' => [self::GROUP_BANK, 'ترتيب عشوائيّ للخيارات', 'bool', '1', '', false],
            'question_bank.server_side_grading' => [self::GROUP_BANK, 'التصحيح على الخادم', 'bool', '1', 'إيقافه يكشف الإجابات للمتصفّح — لا تُوقفه إلّا لسببٍ تعرفه.', false],
            'question_bank.low_general_warning' => [self::GROUP_BANK, 'تحذير نقص الأسئلة العامّة', 'bool', '1', '', false],
            'question_bank.import_enabled' => [self::GROUP_BANK, 'تفعيل استيراد CSV', 'bool', '1', 'إيقافه يخفي زرّ الاستيراد لمن يملك صلاحيّته.', false],
            'question_bank.import_max_kb' => [self::GROUP_BANK, 'أقصى حجم ملفّ الاستيراد (كيلوبايت)', 'number', '2048', '', false],
            'question_bank.import_columns' => [self::GROUP_BANK, 'أعمدة قالب CSV', 'string', 'lesson_id,type,prompt,placeholder,options,correct_answer,is_general,difficulty', 'ترتيب الأعمدة المتوقَّع في أوّل صفّ من الملفّ.', false],
            'question_bank.per_page' => [self::GROUP_BANK, 'عدد صفوف الصفحة', 'number', '20', '', false],
            'question_bank.difficulties' => [self::GROUP_BANK, 'مستويات الصعوبة', 'lines', '{"easy":"سهل","medium":"متوسّط","hard":"صعب"}', 'سطر لكلّ مستوى بصيغة: المفتاح = اللافتة.', false],
            'question_bank.types' => [self::GROUP_BANK, 'أنواع الأسئلة', 'lines', '{"choice":"اختيار متعدّد","text":"نصّيّ","otp":"رقميّ OTP"}', 'سطر لكلّ نوع بصيغة: المفتاح = اللافتة.', false],
            'question_bank.low_warning_text' => [self::GROUP_BANK, 'نصّ تحذير نقص الأسئلة', 'text', 'الأسئلة العامّة أقلّ من سقف الامتحان — زوّد البنك قبل ما تنشر امتحانًا.', '', false],
            'question_bank.empty_text' => [self::GROUP_BANK, 'نصّ الحالة الفارغة', 'text', 'لسّه مافيش أسئلة في البنك — ابدأ بسؤال واحد وهيكبر معاك.', '', false],

            // ------------------------------------------------ الريفيرال والسفراء (24.2)
            'referral_admin.per_page' => [self::GROUP_REFERRAL, 'عدد صفوف الصفحة', 'number', '20', '', false],
            'referral_admin.default_range_days' => [self::GROUP_REFERRAL, 'المدى الافتراضيّ (أيّام)', 'number', '30', 'الشاشة تفتح على آخر 30 يومًا (2.15-أ-11).', false],
            'referral_admin.require_admin_approval' => [self::GROUP_REFERRAL, 'الصرف بموافقة الأدمن', 'bool', '1', 'شرط الدستور: استكمال بيانات المدعوّ + موافقة الأدمن.', false],
            'referral_admin.require_complete_profile' => [self::GROUP_REFERRAL, 'اشتراط استكمال بيانات المدعوّ', 'bool', '1', 'حساب غير مفعَّل = مكافأة لا تُصرَف.', false],
            'referral_admin.max_invites_per_user' => [self::GROUP_REFERRAL, 'حدّ الدعوات للمستخدم الواحد', 'number', '0', 'صفر = بلا حدّ.', false],
            'referral_admin.flag_threshold' => [self::GROUP_REFERRAL, 'عتبة تنبيه التكرار المشبوه', 'number', '5', 'كم دعوة في اليوم من داعٍ واحد تُعتبَر مؤشّرًا يستحقّ التدقيق.', false],
            'referral_admin.paid_text' => [self::GROUP_REFERRAL, 'نصّ نجاح الصرف', 'string', 'اتصرفت المكافأة ✓', '', false],
            'referral_admin.hold_text' => [self::GROUP_REFERRAL, 'نصّ التعليق', 'string', 'اتعلّقت المكافأة لحدّ ما تراجعها.', '', false],
            'referral_admin.empty_text' => [self::GROUP_REFERRAL, 'نصّ الحالة الفارغة', 'text', 'لسّه مافيش دعوات في المدى ده — جرّب مدى أوسع.', '', false],
            // 🔒 الحقل الماليّ في المجموعة المحميّة (12.7) — لمالك المنصّة وحده
            'referral_admin.commission_percent' => [self::GROUP_REFERRAL, '🔒 عمولة الشحن مدى الحياة (%)', 'number', '7', 'حقل ماليّ — لمالك المنصّة وحده.', true],
            'referral_admin.commission_visible' => [self::GROUP_REFERRAL, '🔒 إظهار عمود العمولة', 'bool', '1', 'حتّى لو أُظهِر فلن يراه إلّا مالك المنصّة.', true],

            // ------------------------------------------------ التقارير المجدولة (24.3-خامسًا)
            'report_schedules.default_hour' => [self::GROUP_REPORTS, 'ساعة الإرسال الافتراضيّة', 'number', '7', '7 = السابعة صباحًا بتوقيت الجدولة.', false],
            'report_schedules.default_timezone' => [self::GROUP_REPORTS, 'المنطقة الزمنيّة الافتراضيّة', 'string', 'Africa/Cairo', '', false],
            'report_schedules.default_period_days' => [self::GROUP_REPORTS, 'مدى التقرير الافتراضيّ (أيّام)', 'number', '30', '', false],
            'report_schedules.max_attachment_kb' => [self::GROUP_REPORTS, 'أقصى حجم مرفق (كيلوبايت)', 'number', '10240', 'الأكبر منه يُرسَل كرابط تنزيل مؤقّت بدل مرفق.', false],
            'report_schedules.download_link_hours' => [self::GROUP_REPORTS, 'صلاحيّة رابط التنزيل (ساعات)', 'number', '72', '', false],
            'report_schedules.retry_attempts' => [self::GROUP_REPORTS, 'محاولات إعادة الإرسال عند الفشل', 'number', '3', '', false],
            'report_schedules.notify_admin_on_failure' => [self::GROUP_REPORTS, 'تنبيه الأدمن عند الفشل', 'bool', '1', '', false],
            'report_schedules.export_row_limit' => [self::GROUP_REPORTS, 'حدّ صفوف التصدير', 'number', '50000', '', false],
            'report_schedules.log_keep_days' => [self::GROUP_REPORTS, 'مدّة حفظ سجلّ الإرسال (أيّام)', 'number', '180', '', false],
            'report_schedules.subject_template' => [self::GROUP_REPORTS, 'عنوان رسالة التقرير', 'string', 'تقرير :name — :date', 'المتغيّرات: :name · :date · :tab', false],
            'report_schedules.body_template' => [self::GROUP_REPORTS, 'نصّ رسالة التقرير', 'text', "تقرير «:name» عن آخر :days يوم.\nعدد الصفوف: :rows", 'المتغيّرات: :name · :days · :rows · :date', false],
            'report_schedules.empty_text' => [self::GROUP_REPORTS, 'نصّ الحالة الفارغة', 'text', 'مافيش تقارير مجدولة — ابعت تقريرك الأوّل تلقائيًّا.', '', false],
            'report_schedules.frequencies' => [self::GROUP_REPORTS, 'التكرارات المتاحة', 'lines', '{"daily":"يوميّ","weekly":"أسبوعيّ","monthly":"شهريّ"}', 'سطر لكلّ تكرار بصيغة: المفتاح = اللافتة.', false],
            'report_schedules.formats' => [self::GROUP_REPORTS, 'الصيغ المتاحة', 'lines', '{"csv":"CSV","xlsx":"Excel","pdf":"PDF"}', 'سطر لكلّ صيغة بصيغة: المفتاح = اللافتة.', false],

            // ------------------------------------------------ مرآة الاجتماعات الإداريّة (24.2-أوّلًا)
            'admin_meetings.per_page' => [self::GROUP_MEETINGS, 'عدد صفوف الصفحة', 'number', '20', '', false],
            'admin_meetings.default_range_days' => [self::GROUP_MEETINGS, 'المدى الافتراضيّ (أيّام)', 'number', '30', '', false],
            'admin_meetings.default_window_hours' => [self::GROUP_MEETINGS, 'نافذة تسجيل الحضور الافتراضيّة (ساعات)', 'number', '12', 'تُفتَح لحظة «إنهاء الاجتماع».', false],
            'admin_meetings.max_window_hours' => [self::GROUP_MEETINGS, 'سقف نافذة تسجيل الحضور (ساعات)', 'number', '48', '', false],
            'admin_meetings.exceptional_reason_required' => [self::GROUP_MEETINGS, 'إلزام سبب الحضور الاستثنائيّ', 'bool', '1', 'المنح بلا سبب أثرٌ لا يُراجَع.', false],
            'admin_meetings.freeze_windows_in_maintenance' => [self::GROUP_MEETINGS, 'تجميد النوافذ في وضع الصيانة', 'bool', '1', '', false],
            'admin_meetings.empty_text' => [self::GROUP_MEETINGS, 'نصّ الحالة الفارغة', 'text', 'مافيش اجتماعات في النطاق ده.', '', false],
        ];
    }

    /**
     * صفوف مجموعة واحدة بقيمها الحاليّة وحالة «معدَّل».
     * والحقول الماليّة **تُحذَف من العرض** لغير مالك المنصّة — تُخفى ولا تُعطَّل (2.15-أ-7).
     *
     * @return array<int,array{key:string,label:string,type:string,default:string,value:string,hint:string,modified:bool,owner_only:bool}>
     */
    public static function rows(string $group, ?User $viewer = null): array
    {
        $stored = Setting::query()->pluck('value', 'key');
        $isOwner = $viewer?->isPlatformOwner() ?? false;
        $rows = [];

        foreach (self::catalog() as $key => [$catalogGroup, $label, $type, $default, $hint, $ownerOnly]) {
            if ($catalogGroup !== $group || ($ownerOnly && ! $isOwner)) {
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

        $default = self::catalog()[$key][3] ?? '{}';
        $decoded = json_decode(is_string($value) && $value !== '' ? $value : $default, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** كتابة دفعة من مجموعة واحدة — ومفاتيح غيرها تُتجاهَل بصمت */
    public static function putMany(string $group, array $values, ?User $actor = null): int
    {
        $catalog = self::catalog();
        $isOwner = $actor?->isPlatformOwner() ?? false;
        $count = 0;

        foreach ($values as $key => $value) {
            if (! isset($catalog[$key]) || $catalog[$key][0] !== $group) {
                continue;
            }

            [$catalogGroup, $label, $type, $default, $hint, $ownerOnly] = $catalog[$key];

            // 🔒 الحقل الماليّ لا يُكتَب إلّا من مالك المنصّة مهما كان شكل الطلب
            if ($ownerOnly && ! $isOwner) {
                continue;
            }

            $setting = Setting::firstOrNew(['key' => $key]);
            $old = $setting->value;

            $setting->fill([
                'group' => $catalogGroup,
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

    /** ↺ رجوع مجموعة كاملة لافتراضيّها */
    public static function resetGroup(string $group, ?User $actor = null): int
    {
        $defaults = [];

        foreach (self::catalog() as $key => $row) {
            if ($row[0] !== $group) {
                continue;
            }

            $defaults[$key] = $row[2] === 'lines' ? self::mapToLines($row[3]) : $row[3];
        }

        return self::putMany($group, $defaults, $actor);
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
