<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات صفحات النظام التجريبيّة (12.7-أ · هـ · و) ومعها **كلّ إعداداتها**.
 *
 * 🏆 القاعدة الذهبيّة (2.13): كلّ رقم وكلّ نصّ في هذا المجال إعدادٌ بقيمة افتراضيّة —
 *    فلا يوجد في كود المجال رقمٌ محروق واحد، والمالك يغيّر بلا مطوّر.
 */
class AdminOpsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->onboardingSlides();
        $this->versionHistory();

        Cache::forget('settings');
        $this->command?->info('بيانات admin-ops جاهزة.');
    }

    // ---------------------------------------------------------------- الإعدادات

    public function settings(): void
    {
        // [key, group, label, type, default]
        $rows = [
            // ---------------- محتوى الـOnboarding و«أوّل مرّة» (12.7-أ · 2.15-د)
            ['onboarding.welcome.enabled', 'onboarding', 'تفعيل سلسلة الترحيب الأولى', 'bool', '1'],
            ['onboarding.welcome.label', 'onboarding', 'اسم سلسلة الترحيب في اللوحة', 'string', 'سلسلة الترحيب الأولى'],
            ['onboarding.slides.max', 'onboarding', 'أقصى عدد شرائح لكلّ شاشة', 'number', '6'],
            ['onboarding.slides.image_max_kb', 'onboarding', 'أقصى حجم لصورة الشريحة (ك.ب)', 'number', '2048'],
            ['onboarding.slides.image_folder', 'onboarding', 'مجلّد صور الشرائح', 'string', 'onboarding'],
            ['onboarding.slides.empty_text', 'onboarding', 'نصّ الحالة الفارغة', 'text', 'لسّه مافيش شرائح — ابدأ بأوّل واحدة أو استخدم قالبًا جاهزًا.'],
            ['onboarding.first_time.enabled', 'onboarding', 'تفعيل «شاشة أوّل مرّة»', 'bool', '1'],
            ['onboarding.first_time.next_label', 'onboarding', 'زرّ التالي', 'string', 'التالي'],
            ['onboarding.first_time.back_label', 'onboarding', 'زرّ السابق', 'string', 'السابق'],
            ['onboarding.first_time.skip_label', 'onboarding', 'زرّ التخطّي', 'string', 'تخطّي'],
            ['onboarding.first_time.done_label', 'onboarding', 'زرّ النهاية', 'string', 'يلا نبدأ'],
            ['onboarding.first_time.replay_hint', 'onboarding', 'سطر إعادة الشرح', 'string', 'زرّ «؟» يعيد الشرح وقت ما تحبّ.'],
            ['onboarding.first_time.screens', 'onboarding', 'الشاشات المتاحة لـ«أوّل مرّة»', 'json', json_encode([
                'dashboard' => 'الرئيسيّة',
                'learning.courses' => 'تدريباتي',
                'wallet.index' => 'المحفظة',
                'volunteer.overview' => 'لوحة التطوّع',
            ], JSON_UNESCAPED_UNICODE)],
            ['onboarding.first_time.templates', 'onboarding', 'القوالب الجاهزة لكلّ شاشة (قابلة للتعديل)', 'json', json_encode([
                'dashboard' => [
                    ['title' => 'أهلًا بيك 👋', 'body' => 'دي رئيسيّتك — منها تشوف تدريباتك ومهامّك وكلّ جديد.'],
                    ['title' => 'ابدأ من هنا', 'body' => 'كارت «كمّل اللي وقفت عنده» بيرجّعك لآخر درس بضغطة.', 'action_label' => 'تدريباتي', 'action_url' => '/learning/courses'],
                ],
                'learning.courses' => [
                    ['title' => 'تدريباتك كلّها هنا', 'body' => 'كلّ تدريب بنسبة تقدّمه ومهلته — والترتيب حسب الأقرب للتسليم.'],
                    ['title' => 'المهلة صديقتك', 'body' => 'الديدلاين بيبان بلون ورمز: أخضر متّسع · أصفر قرّب · أحمر فات.'],
                ],
                'wallet.index' => [
                    ['title' => 'محفظتك', 'body' => 'الكوينز والـXP والتذاكر في مكان واحد، وكلّ معاملة بسجلّها.'],
                ],
                'volunteer.overview' => [
                    ['title' => 'لوحة التطوّع', 'body' => 'مهامّك ومهلك ودرجة التزامك — وكلّ حاجة محتاجة إجراء بتبان فوق.'],
                ],
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- التحديثات والترحيل (12.7-هـ)
            ['updates.current_version', 'updates', 'إصدار التطبيق الحاليّ', 'string', '1.0.0'],
            ['updates.confirm_phrase', 'updates', 'عبارة تأكيد التنفيذ', 'string', 'تنفيذ'],
            ['updates.rollback_confirm_phrase', 'updates', 'عبارة تأكيد الاسترجاع', 'string', 'استرجاع'],
            ['updates.dry_run_required', 'updates', 'Dry-run إلزاميّ قبل التحديث', 'bool', '1'],
            ['updates.dry_run_valid_minutes', 'updates', 'صلاحيّة تصريح الـDry-run (دقائق)', 'number', '30'],
            ['updates.backup_before_migrate', 'updates', 'نسخة احتياطيّة إلزاميّة قبل الترحيل', 'bool', '1'],
            ['updates.rollback_enabled', 'updates', 'تفعيل زرّ الاسترجاع', 'bool', '1'],
            ['updates.forward_only', 'updates', 'منع الرجوع لإصدار أقدم', 'bool', '1'],
            ['updates.batch_rows', 'updates', 'حجم دفعة الترحيل (صفوف)', 'number', '1000'],
            ['updates.migrations_path', 'updates', 'مسار إضافيّ لملفّات الهجرات', 'string', ''],
            ['updates.history_per_page', 'updates', 'صفوف سجلّ الإصدارات', 'number', '20'],
            ['updates.audit_per_page', 'updates', 'صفوف سجلّ تدقيق التحديثات', 'number', '10'],

            // ---------------- النسخ الاحتياطيّ وصحّة النظام (12.7-و)
            ['backups.enabled', 'backups', 'تفعيل النسخ الاحتياطيّ', 'bool', '1'],
            ['backups.path', 'backups', 'مجلّد النسخ داخل storage', 'string', 'backups'],
            ['backups.keep_count', 'backups', 'عدد النسخ المحفوظة', 'number', '7'],
            ['backups.keep_max', 'backups', 'أقصى عدد نسخ مسموح بحفظه', 'number', '90'],
            ['backups.daily_time', 'backups', 'وقت النسخة الدوريّة', 'string', '03:00'],
            ['backups.default_kind', 'backups', 'نوع النسخة الافتراضيّ', 'string', 'full'],
            ['backups.schedule.enabled', 'backups', 'تفعيل النسخ المجدول', 'bool', '1'],
            ['backups.schedule.frequency', 'backups', 'دوريّة النسخ المجدول', 'string', 'daily'],
            ['backups.schedule.frequencies', 'backups', 'الدوريّات المتاحة', 'json', json_encode([
                'daily' => 'يوميًّا', 'weekly' => 'أسبوعيًّا',
            ], JSON_UNESCAPED_UNICODE)],
            ['backups.schedule.last_run_at', 'backups', 'آخر تشغيل للنسخ المجدول', 'string', ''],
            ['backups.kinds', 'backups', 'أنواع النسخة', 'json', json_encode([
                'full' => 'كاملة', 'database' => 'قاعدة البيانات', 'files' => 'ملفّات التخزين',
            ], JSON_UNESCAPED_UNICODE)],
            ['backups.include_storage', 'backups', 'ضمّ ملفّات التخزين للنسخة الكاملة', 'bool', '1'],
            ['backups.files.folder', 'backups', 'مجلّد الملفّات المنسوخة داخل storage/app', 'string', 'public'],
            ['backups.files.max_mb', 'backups', 'سقف حجم الملفّات في النسخة (م.ب)', 'number', '200'],
            ['backups.rows_per_table_max', 'backups', 'أقصى صفوف لكلّ جدول في التفريغ', 'number', '20000'],
            ['backups.admin.per_page', 'backups', 'صفوف جدول النسخ', 'number', '15'],
            ['backups.audit_per_page', 'backups', 'صفوف سجلّ تدقيق النظام', 'number', '10'],
            ['backups.max_age_hours_alert', 'backups', 'عتبة تنبيه قِدَم آخر نسخة (ساعات)', 'number', '48'],
            ['backups.disk_alert_percent', 'backups', 'عتبة تنبيه امتلاء القرص (%)', 'number', '85'],
            ['backups.cron_alert_hours', 'backups', 'عتبة تنبيه توقّف الجدولة (ساعات)', 'number', '1'],
            ['system.health.disk_warn_percent', 'backups', 'تحذير مبكّر لامتلاء القرص (%)', 'number', '75'],
            ['system.health.db_slow_ms', 'backups', 'حدّ بطء قاعدة البيانات (م.ث)', 'number', '300'],
            ['system.health.queue_warn_jobs', 'backups', 'تحذير طول الطابور (مهامّ منتظرة)', 'number', '100'],
            ['system.health.required_extensions', 'backups', 'الامتدادات المطلوبة', 'json', json_encode(['gd', 'zip', 'intl', 'imagick'])],
            ['system.health.writable_paths', 'backups', 'المجلّدات التي يجب أن تكون قابلة للكتابة', 'json', json_encode([
                'storage/app', 'storage/logs', 'storage/framework', 'bootstrap/cache',
            ])],
            ['system.health.alerts_enabled', 'backups', 'التنبيهات الاستباقيّة', 'bool', '1'],
            ['system.health.alert_cooldown_minutes', 'backups', 'تبريد التنبيه المتكرّر (دقائق)', 'number', '180'],
            ['system.health.alert_max_recipients', 'backups', 'أقصى عدد أدمنز يصلهم التنبيه', 'number', '10'],
            ['system.health.alert_title', 'backups', 'عنوان إشعار التنبيه', 'string', 'تنبيه نظام'],
            ['system.health.alert_category', 'backups', 'فئة إشعار التنبيه', 'string', 'system'],
            ['system.health.last_alert_at', 'backups', 'آخر وقت تنبيه لكلّ مؤشّر', 'json', '{}'],
            ['system.health.last_check_at', 'backups', 'آخر فحص صحّة', 'string', ''],
            ['system.schedule.last_run_at', 'backups', 'آخر تشغيل مسجَّل للجدولة', 'string', ''],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            // موجود من مجال آخر؟ نسيب قيمته كما هي ولا ندهس تعديل الأدمن
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            Setting::create([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
            ]);
        }

        // مفتاح «شاشات أوّل مرّة المفعَّلة» موجود من السيدر الأمّ — نملؤه بشاشتين فقط،
        // لأنّ القاعدة: على أهمّ الشاشات فقط منعًا للزحام (2.15-د)
        Setting::query()->where('key', 'ux.first_time.enabled_screens')->update([
            'value' => json_encode(['welcome', 'dashboard'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- المحتوى

    private function onboardingSlides(): void
    {
        if (DB::table('onboarding_slides')->exists()) {
            return;
        }

        $rows = [
            ['welcome', 'أهلًا بيك معانا 👋', 'المكان ده اتعمل عشانك: تتعلّم، تتدرّب، وتاخد شهادة تفتخر بيها.', 'ابدأ الجولة', null],
            ['welcome', 'تدريب بخطوات واضحة', 'كلّ تدريب مقسّم دروسًا قصيرة، وتقدر توقف وترجع من نفس النقطة.', null, '/learning/courses'],
            ['welcome', 'مجهودك بيتحوّل نقاطًا', 'كلّ درس بيدّيك XP، والنقاط بتفتح لك تدريبات وحاجات في المتجر.', 'يلا نبدأ', '/dashboard'],
            ['dashboard', 'دي رئيسيّتك', 'من هنا تشوف تدريباتك ومهامّك وكلّ جديد — بلا لفّ ولا دوران.', null, null],
            ['dashboard', 'كمّل اللي وقفت عنده', 'الكارت الأوّل بيرجّعك لآخر درس فتحته بضغطة واحدة.', 'خُدني هناك', '/learning/courses'],
        ];

        foreach ($rows as $index => [$screen, $title, $body, $actionLabel, $actionUrl]) {
            DB::table('onboarding_slides')->insert([
                'screen' => $screen,
                'title_ar' => $title,
                'body_ar' => $body,
                'image_path' => null,
                'action_label' => $actionLabel,
                'action_url' => $actionUrl,
                'sort_order' => $index + 1,
                'is_active' => true,
                'from_template' => false,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function versionHistory(): void
    {
        if (DB::table('app_version_history')->exists()) {
            return;
        }

        DB::table('app_version_history')->insert([
            'version' => '1.0.0',
            'previous_version' => null,
            'event' => 'release',
            'notes' => 'أوّل إصدار مستقرّ — الأساس اللي بنينا عليه.',
            'migrations_count' => 0,
            'migrations' => null,
            'backup_file_id' => null,
            'performed_by' => null,
            'performed_at' => now()->subDays(7),
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);
    }
}
