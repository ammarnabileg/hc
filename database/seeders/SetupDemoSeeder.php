<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * إعدادات معالج التنصيب (2.2) بالقاعدة الذهبيّة (2.13): لا رقم ولا نصّ محروق.
 * والمعالج يقرأها بقيم افتراضيّة آمنة، لأنّه يعمل قبل وجود قاعدة البيانات أصلًا —
 * فالسيدر هنا يفتح الباب لتعديلها من لوحة الإدارة بعد التنصيب.
 */
class SetupDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
    }

    /** تعريفات إعدادات المعالج — يستدعيها سيدر الإنتاج فتُزرَع على كلّ تنصيب (2.13) */
    public function settings(): void
    {
        $rows = [
            // ---------------- فحص المتطلّبات
            ['setup.requirements.php_version', 'إصدار PHP الأدنى', 'string', '8.3', 'أقلّ إصدار تشتغل عليه المنصّة.'],
            ['setup.requirements.extensions', 'الامتدادات الإلزاميّة', 'json', '["pdo","mbstring","gd","zip","intl","openssl"]', 'ناقص واحد منها = التنصيب موقوف.'],
            ['setup.requirements.optional_extensions', 'الامتدادات الاختياريّة', 'json', '["curl","fileinfo","exif"]', 'تحسّن الأداء ولا توقف التنصيب.'],
            ['setup.requirements.writable_paths', 'المجلّدات اللي لازم تكون قابلة للكتابة', 'json', '["storage","bootstrap\\/cache"]', 'نسبةً لجذر المشروع.'],

            // ---------------- التوكن (أمان لحظة الرفع)
            ['setup.token.length', 'طول توكن التنصيب', 'number', '32', 'يُولَّد في storage/setup-token.txt عند أوّل فتح.'],

            // ---------------- قاعدة البيانات
            ['setup.database.driver', 'محرّك قاعدة البيانات', 'string', 'mysql', 'المحرّك المستخدَم في التنصيب.'],
            ['setup.database.default_host', 'المضيف الافتراضيّ', 'string', '127.0.0.1', 'القيمة المقترحة في الفورم.'],
            ['setup.database.default_port', 'المنفذ الافتراضيّ', 'number', '3306', 'القيمة المقترحة في الفورم.'],
            ['setup.database.timeout_seconds', 'مهلة اختبار الاتّصال (ثوانٍ)', 'number', '5', '5 = خمس ثوانٍ قبل ما نقول إنّ الخادم مش بيردّ.'],
            ['setup.seed.class', 'سيدر البيانات الأساسيّة', 'string', 'Database\\Seeders\\DatabaseSeeder', 'يشتغل بعد المايجريشنز مباشرةً.'],

            // ---------------- بيانات المنصّة
            ['setup.platform.default_name', 'الاسم المقترح للمنصّة', 'string', 'المنصّة', 'يظهر في الفورم كقيمة أوّليّة.'],
            ['setup.platform.default_timezone', 'المنطقة الزمنيّة الافتراضيّة', 'string', 'Africa/Cairo', 'كلّ المواعيد تُحسَب بيها.'],
            ['setup.platform.default_locale', 'اللغة الافتراضيّة', 'string', 'ar', 'لغة الواجهة لكلّ مستخدم جديد.'],
            ['setup.platform.locales', 'اللغات المتاحة', 'json', '{"ar":"العربيّة","en":"English"}', 'تظهر في قائمة اللغة بالمعالج.'],
            ['setup.platform.preferred_timezones', 'المناطق الزمنيّة المقترحة', 'json', '["Africa\\/Cairo","Asia\\/Riyadh","Asia\\/Dubai","Africa\\/Khartoum","Asia\\/Amman","Europe\\/London","UTC"]', 'تظهر أوّل القائمة.'],
            ['setup.platform.logo_max_kb', 'أقصى حجم للشعار (كيلوبايت)', 'number', '2048', '2048 = 2 ميجابايت.'],

            // ---------------- حساب المالك والإنهاء
            ['setup.owner.role_key', 'دور مالك المنصّة', 'string', 'platform_owner', 'الدور المُسنَد للحساب الأوّل (12.2.3).'],
            ['setup.owner.min_password', 'أدنى طول لكلمة سرّ المالك', 'number', '8', '8 = ثمانية حروف.'],
            ['setup.finish.link_storage', 'ربط مجلّد التخزين بعد التنصيب', 'bool', '1', 'يخلّي الصور المرفوعة تظهر للزوّار بلا تيرمينال.'],
            ['setup.finish.rotate_app_key', 'توليد مفتاح تطبيق جديد عند الإنهاء', 'bool', '1', 'المفتاح المؤقّت وُلِّد قبل التنصيب فلا نُبقيه.'],

            // ---------------- نصوص المعالج (2.13: النصوص الظاهرة كلّها إعدادات)
            ['setup.texts.step_requirements', 'اسم خطوة المتطلّبات', 'string', 'فحص المتطلّبات', ''],
            ['setup.texts.step_database', 'اسم خطوة قاعدة البيانات', 'string', 'قاعدة البيانات', ''],
            ['setup.texts.step_migrate', 'اسم خطوة تجهيز الجداول', 'string', 'تجهيز الجداول', ''],
            ['setup.texts.step_platform', 'اسم خطوة بيانات المنصّة', 'string', 'بيانات المنصّة', ''],
            ['setup.texts.step_owner', 'اسم خطوة حساب المالك', 'string', 'حساب المالك', ''],
            ['setup.texts.step_finish', 'اسم خطوة الإنهاء', 'string', 'الإنهاء', ''],
            ['setup.texts.token_mismatch', 'رسالة التوكن الخاطئ', 'text', 'التوكن مش مطابق. افتح ملفّ التوكن من مدير الملفّات وانسخ السطر اللي جوّاه كما هو.', 'رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب).'],
        ];

        foreach ($rows as [$key, $label, $type, $value, $hint]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'setup',
                'label_ar' => $label,
                'type' => $type,
                'value' => $value,
                'default_value' => $value,
                'hint' => $hint,
                // إعدادات البنية التحتيّة لمالك المنصّة وحده (2.13-و)
                'is_owner_only' => true,
            ]);
        }

        // شعار المنصّة يُرفَع من المعالج (2.2-2) ويتغيّر بعدين من اللوحة
        Setting::updateOrCreate(['key' => 'platform.branding.logo_path'], [
            'group' => 'platform',
            'label_ar' => 'شعار المنصّة',
            'type' => 'media',
            'value' => '',
            'default_value' => '',
            'hint' => 'الشعار الظاهر في الهيدر وصفحات الدخول والشهادات.',
        ]);

        $this->command?->info('إعدادات التنصيب: '.(count($rows) + 1));
    }
}
