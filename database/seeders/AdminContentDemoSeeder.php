<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\CertificateType;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\HelpArticle;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\MediaItem;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Content\TemplateDesigner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * بيانات تجريبيّة لمجال «إدارة التدريب والشهادات والتوجيه» — ولا تُسجَّل في DatabaseSeeder.
 * ويحمل معه **إعدادات المجال** (2.13) فلا رقم ولا نصّ محروق في الكود.
 */
class AdminContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->paths();
        $this->media();
        $this->certificates();
        $this->guidance();
    }

    // ============================================================== الإعدادات (2.13)

    public function settings(): void
    {
        $rows = [
            // ---------------- المسارات (12.4-أ)
            ['paths.order.forced_default', 'paths', 'ترتيب المشاهدة الإجباريّ كافتراضيّ', 'bool', '0'],
            ['paths.exam.default_price_coins', 'paths', 'سعر امتحان شهادة المسار الافتراضيّ (كوينز)', 'number', '50'],
            ['paths.courses.picker_limit', 'paths', 'حدّ قائمة التدريبات المرشّحة للإضافة', 'number', '20'],
            ['paths.delete.confirm_text', 'paths', 'نصّ تأكيد حذف المسار', 'string', 'هنشيل المسار — وتدريباته هتفضل زيّ ما هي. نكمّل؟'],
            ['paths.delete.success_text', 'paths', 'نصّ نجاح حذف المسار', 'string', 'اتشال المسار — وتدريباته زيّ ما هي ✓'],
            ['paths.detach.confirm_text', 'paths', 'نصّ تأكيد الإزالة من المسار', 'string', 'هنشيله من المسار بس — التدريب هيفضل موجود. نكمّل؟'],
            ['paths.detach.success_text', 'paths', 'نصّ نجاح الإزالة من المسار', 'string', 'اتشال من المسار — والتدريب زيّ ما هو ✓'],
            ['paths.statuses', 'paths', 'حالات المسار', 'json', '{"draft":"مسودّة","scheduled":"مجدول","published":"منشور","archived":"مؤرشف"}'],

            // ---------------- التدريبات (12.4-ب)
            ['courses.statuses', 'courses', 'حالات التدريب', 'json', '{"draft":"مسودّة","scheduled":"مجدول","published":"منشور","archived":"مؤرشف"}'],
            ['courses.table.per_page', 'courses', 'عدد صفوف جدول التدريبات', 'number', '15'],
            ['courses.enrollees.per_page', 'courses', 'عدد صفوف قائمة المسجّلين', 'number', '20'],
            ['courses.autosave.label', 'courses', 'نصّ الحفظ التلقائيّ', 'string', 'اتحفظ ✓'],
            // الحفظ التلقائيّ على تدريبٍ حيّ يكتب في مسوّدة تحريرٍ جانبيّة (12.4-ب)
            ['courses.autosave.draft_label', 'courses', 'نصّ الحفظ التلقائيّ للمنشور', 'string', 'اتحفظ كمسودّة تحرير ✓'],
            ['courses.autosave.discarded_label', 'courses', 'نصّ تجاهل مسوّدة التحرير', 'string', 'اتشالت مسوّدة التحرير — النسخة المنشورة زيّ ما هي ✓'],
            ['courses.autosave.draft_notice', 'courses', 'شرح بانر مسوّدة التحرير المعلّقة', 'string', 'التعديلات المحفوظة تلقائيًّا معروضة في الفورم — اضغط «حفظ» تسري على المنشور، أو تجاهلها وترجع النسخة المنشورة.'],
            ['courses.save.continue_label', 'courses', 'نصّ «حفظ واستمرار» للمسودّة', 'string', 'اتحفظ كمسودّة ✓'],
            ['courses.save.continue_published_label', 'courses', 'نصّ «حفظ واستمرار» للمنشور', 'string', 'اتحفظ وهو منشور ✓ — كمّل تحرير'],
            ['courses.autosave.debounce_ms', 'courses', 'مهلة الحفظ التلقائيّ (مللي ثانية)', 'number', '2000'],
            ['courses.duplicate.suffix', 'courses', 'لاحقة النسخة المكرّرة', 'string', ' — نسخة'],
            ['courses.xp.max_per_lesson', 'courses', 'أقصى XP للدرس', 'number', '50'],
            ['courses.availability.max_windows', 'courses', 'أقصى فترات إتاحة', 'number', '3'],

            // ---------------- الامتحانات
            // ⭐ 4.2 و8: درجة النجاح **70%+ افتراضيًّا** (أو حسب المحدَّد في لوحة الإدارة)
            ['exams.pass_score.default', 'exams', 'درجة النجاح الافتراضيّة', 'number', '70'],
            ['exams.questions.default_count', 'exams', 'عدد أسئلة الامتحان الافتراضيّ', 'number', '20'],
            ['exams.duration.default_minutes', 'exams', 'مدّة الامتحان الافتراضيّة (دقائق)', 'number', '30'],

            // ---------------- الدروس والأسئلة (12.4-ج)
            ['lessons.duplicate.suffix', 'lessons', 'لاحقة الدرس المكرّر', 'string', ' — نسخة'],
            ['lessons.questions.default_xp', 'lessons', 'XP السؤال الافتراضيّ', 'number', '0'],
            ['lessons.questions.placeholder_otp', 'lessons', 'Placeholder السؤال الرقميّ', 'string', 'اكتب الرقم'],
            ['lessons.questions.placeholder_text', 'lessons', 'Placeholder السؤال النصّيّ', 'string', 'اكتب إجابتك هنا…'],
            ['lessons.questions.placeholder_choice', 'lessons', 'Placeholder سؤال الاختيار', 'string', 'اختر الإجابة الصحيحة'],
            ['lessons.questions.csv_columns', 'lessons', 'أعمدة قالب CSV', 'json', '["type","prompt","placeholder","options","correct_answer","is_general"]'],
            ['lessons.questions.csv_max_rows', 'lessons', 'أقصى صفوف CSV', 'number', '500'],
            ['lessons.questions.csv_max_kb', 'lessons', 'أقصى حجم ملفّ CSV (ك.ب)', 'number', '2048'],

            // ---------------- مكتبة الوسائط (12.4-د)
            ['media.dedup.enabled', 'media', 'منع التكرار بالهاش', 'bool', '1'],
            ['media.dedup.notice', 'media', 'نصّ اكتشاف الملفّ المكرَّر', 'string', 'الملفّ ده موجود عندنا — استخدمنا النسخة الحاليّة ✓'],
            ['media.storage.disk', 'media', 'قرص التخزين', 'string', 'public'],
            ['media.storage.directory', 'media', 'مجلّد التخزين', 'string', 'media'],
            ['media.upload.max_kb', 'media', 'أقصى حجم للملفّ (ك.ب)', 'number', '10240'],
            ['media.upload.allowed_extensions', 'media', 'الامتدادات المسموحة', 'json', '["jpg","jpeg","png","webp","gif","pdf","doc","docx","mp3","wav","mp4","csv","txt"]'],
            ['media.grid.per_page', 'media', 'عدد البطاقات في الصفحة', 'number', '24'],
            ['media.folders.defaults', 'media', 'المجلّدات الافتراضيّة', 'json', '["أغلفة","مرفقات","شهادات","شعارات"]'],
            ['media.tags.max_per_item', 'media', 'أقصى وسوم للملفّ', 'number', '8'],
            ['media.delete.in_use_warning', 'media', 'تحذير حذف ملفّ مستخدَم', 'string', 'الملفّ ده مستخدَم في أماكن تانية — أكّد الحذف لو متأكّد.'],

            // ---------------- الشهادات (12.5)
            ['certificates.tabs', 'certificates', 'تبويبات إدارة الشهادات', 'json', '{"accreditations":"الاعتمادات","types":"الأنواع والقوالب","issue":"إصدار شهادة","ledger":"سجلّ الصادر"}'],
            ['certificates.accreditation.platform_locked_text', 'certificates', 'نصّ منع حذف اعتماد المنصّة', 'string', 'اعتماد المنصّة ثابت ولا يتشال — تقدر تعطّله بس.'],
            ['certificates.accreditation.verify_note_ar', 'certificates', 'نصّ صفحة التحقّق', 'string', 'معتمدة من'],
            ['certificates.numbering.padding', 'certificates', 'طول تسلسل الترقيم', 'number', '6'],
            ['certificates.numbering.separator', 'certificates', 'فاصل الترقيم', 'string', '-'],
            ['certificates.numbering.default_prefix', 'certificates', 'بادئة الترقيم الافتراضيّة', 'string', 'HC'],
            ['certificates.designer.grid_step', 'certificates', 'خطوة شبكة المحاذاة (%)', 'number', '5'],
            ['certificates.designer.snap_enabled', 'certificates', 'تفعيل Snap افتراضيًّا', 'bool', '1'],
            ['certificates.designer.max_layers', 'certificates', 'أقصى عدد طبقات', 'number', '40'],
            ['certificates.designer.default_font', 'certificates', 'الخطّ الافتراضيّ', 'string', 'Cairo'],
            ['certificates.designer.default_font_size', 'certificates', 'حجم الخطّ الافتراضيّ (على عرض 1754)', 'number', '32'],
            ['certificates.designer.default_color', 'certificates', 'لون النصّ الافتراضيّ', 'string', '#e8f5f2'],
            ['certificates.designer.saved_text', 'certificates', 'نصّ حفظ التصميم', 'string', 'اتحفظ التصميم ✓'],
            ['certificates.designer.mobile_notice', 'certificates', 'تنبيه المصمّم على الموبايل', 'string', 'مصمّم القالب محتاج شاشة كبيرة — افتحه من اللابتوب عشان السحب يبقى مريح.'],
            ['certificates.issue.batch_limit', 'certificates', 'حدّ الأكواد في الدفعة', 'number', '200'],
            ['certificates.issue.code_separators', 'certificates', 'فواصل الأكواد المقبولة', 'string', " \n\r\t,;،"],
            ['certificates.issue.error_not_found', 'certificates', 'نصّ الكود غير الموجود', 'string', 'الكود غير موجود'],
            ['certificates.issue.error_duplicate', 'certificates', 'نصّ التكرار', 'string', 'صدرت له من قبل'],
            ['certificates.issue.notify_user', 'certificates', 'إشعار صاحب الشهادة عند الإصدار', 'bool', '1'],
            ['certificates.issue.notice_title', 'certificates', 'عنوان إشعار الإصدار', 'string', 'مبروك — صدرت شهادتك 🎓'],
            ['certificates.issue.confirm_text', 'certificates', 'نصّ تأكيد الإصدار', 'string', 'هنصدر الشهادات دي دلوقتي — نكمّل؟'],
            ['certificates.issue.codes_placeholder', 'certificates', 'Placeholder حقل الأكواد', 'string', 'الصق الأكواد مفصولة بمسافة أو فاصلة…'],
            ['certificates.issue.preview_code_placeholder', 'certificates', 'نصّ الكود في المعاينة', 'string', '— يُولَّد عند الإصدار —'],
            ['certificates.revoke.reasons', 'certificates', 'أسباب الإلغاء', 'json', '["تزوير مثبَت","بيانات خاطئة","طلب صاحبها"]'],
            ['certificates.revoke.notice_title', 'certificates', 'عنوان إشعار الإلغاء', 'string', 'تحديث على إحدى شهاداتك'],
            ['certificates.revoke.notice_body', 'certificates', 'نصّ إشعار الإلغاء', 'string', 'راجعنا شهادتك وأوقفنا العمل بها — تواصل معنا لو محتاج توضيحًا.'],
            ['certificates.reissue.reason', 'certificates', 'سبب إبطال القديمة عند إعادة الإصدار', 'string', 'أُعيد إصدارها مصحَّحةً'],
            ['certificates.reissue.confirm_text', 'certificates', 'نصّ تأكيد إعادة الإصدار', 'string', 'هنبطل القديمة ونصدر مصحّحة — نكمّل؟'],
            ['certificates.ledger.per_page', 'certificates', 'عدد صفوف السجلّ', 'number', '20'],
            ['certificates.sources', 'certificates', 'مصادر الإصدار', 'json', '{"manual":"يدويّ","auto":"تلقائيّ","import":"مستورد"}'],
            ['certificates.preview.canvas_width', 'certificates', 'عرض لوحة المعاينة (بكسل)', 'number', '700'],
            ['certificates.preview.sample_name', 'certificates', 'اسم المعاينة الوهميّ', 'string', 'محمّد أحمد عبد الله'],
            ['certificates.preview.sample_country', 'certificates', 'دولة المعاينة الوهميّة', 'string', 'مصر'],
            ['certificates.preview.sample_code', 'certificates', 'كود المعاينة الوهميّ', 'string', 'U-1234'],
            ['certificates.preview.sample_binding', 'certificates', 'قيمة الربط في المعاينة', 'string', 'قيمة من قاعدة البيانات'],
            ['certificates.bindings.tables', 'certificates', 'الجداول والأعمدة المسموح الربط بها', 'json', '{"users":{"label":"المستخدمون","columns":{"name":"الاسم","code":"الكود"}},"courses":{"label":"التدريبات","columns":{"name_ar":"اسم التدريب","cert_name_ar":"اسم الشهادة"}},"learning_paths":{"label":"المسارات","columns":{"name_ar":"اسم المسار"}},"events":{"label":"الفعاليّات","columns":{"title_ar":"اسم الفعاليّة"}}}'],

            // ---------------- التوجيه والدعم (12.6)
            ['announcements.admin.per_page', 'announcements', 'عدد صفوف جدول التعليمات', 'number', '15'],
            ['announcements.acknowledge.default_xp', 'announcements', 'XP الإقرار الافتراضيّ', 'number', '10'],
            ['announcements.acknowledge.default_tickets', 'announcements', 'تذاكر الإقرار الافتراضيّة', 'number', '0'],
            ['announcements.acknowledge.max_xp', 'announcements', 'أقصى XP للإقرار', 'number', '500'],
            ['announcements.acknowledge.max_tickets', 'announcements', 'أقصى تذاكر للإقرار', 'number', '20'],
            ['announcements.acknowledge.ledger_source', 'announcements', 'دلو دفتر الأستاذ لتذاكر الإقرار', 'string', 'announcement'],
            ['announcements.pinned.max', 'announcements', 'أقصى منشورات مثبَّتة', 'number', '3'],
            ['announcements.auto_archive.days', 'announcements', 'أيّام الأرشفة التلقائيّة', 'number', '30'],
            ['announcements.duplicate.suffix', 'announcements', 'لاحقة نسخة المنشور', 'string', ' — نسخة'],
            ['announcements.analytics.max_rows', 'announcements', 'أقصى صفوف التحليلات', 'number', '100'],
            ['announcements.audience.picker_limit', 'announcements', 'حدّ قائمة التدريبات في الاستهداف', 'number', '30'],
            ['announcements.push.max_recipients', 'announcements', 'أقصى مستقبلين للبثّ', 'number', '2000'],
            ['announcements.push.body_limit', 'announcements', 'أقصى أحرف نصّ الإشعار', 'number', '120'],
            ['announcements.reactions.default_on', 'announcements', 'التفاعل مسموح افتراضيًّا', 'bool', '0'],

            ['notifications.types', 'notifications', 'أنواع الإشعارات', 'json', '{"account":"قبول الحساب","certificate":"إصدار شهادة","exam":"نتيجة امتحان","announcement":"رسالة إداريّة","wallet":"طلب سحب أو شحن","order":"اكتمال طلب"}'],
            ['notifications.channels', 'notifications', 'قنوات الإشعار', 'json', '{"bell":"الجرس","toast":"Toast","email":"بريد"}'],
            ['notifications.grouping.window_minutes', 'notifications', 'نافذة تجميع الإشعارات المتشابهة (دقائق)', 'number', '15'],
            ['notifications.grouping.enabled', 'notifications', 'تفعيل تجميع الإشعارات المتشابهة', 'bool', '1'],
            ['notifications.grouping.report_windows', 'notifications', 'عدد النوافذ في تقرير التجميع', 'number', '96'],
            ['notifications.rate_limit.per_user_per_day', 'notifications', 'حدّ الهدوء: إشعارات/مستخدم/يوم', 'number', '3'],
            // فئات لا يؤجّلها حدّ الهدوء: تأجيلها ضررُه أكبر من الإغراق نفسه (12.6-ب)
            ['notifications.rate_limit.exempt_categories', 'notifications', 'فئات مستثناة من حدّ الهدوء', 'json', '["account","security","certificate"]'],
            ['notifications.digest.category', 'notifications', 'فئة إشعار التجميع اليوميّ', 'string', 'digest'],
            ['notifications.digest.title', 'notifications', 'عنوان إشعار التجميع (:count)', 'string', 'عندك :count تنبيهات جديدة'],
            ['notifications.manual.default_category', 'notifications', 'نوع الإشعار اليدويّ الافتراضيّ', 'string', 'announcement'],
            ['notifications.manual.max_recipients', 'notifications', 'أقصى مستقبلين للإشعار اليدويّ', 'number', '2000'],

            ['help.admin.per_page', 'help', 'عدد أدلّة الصفحة', 'number', '12'],
            ['help.categories', 'help', 'تصنيفات دليل المستخدم', 'json', '["البداية","التدريبات","الشهادات","المحفظة","الحساب"]'],

            ['complaints.admin.per_page', 'complaints', 'عدد شكاوى الصفحة', 'number', '15'],
            ['complaints.reasons', 'complaints', 'أسباب الشكوى', 'json', '["أحد المشرفين","الهيكل الإداريّ وأسلوب الإدارة","اللقاءات المباشرة","اللوائح والقوانين","المحتوى التدريبيّ","خدمة العملاء","المنصّة","أخرى"]'],
            ['complaints.close_reasons', 'complaints', 'أسباب الإغلاق', 'json', '["اتحلّت","مكرّرة","خارج نطاقنا"]'],
            ['complaints.sla.reply_hours', 'complaints', 'SLA الردّ (ساعات)', 'number', '48'],
            ['complaints.reply.max_chars', 'complaints', 'أقصى أحرف للردّ', 'number', '2000'],
            ['complaints.assignees.limit', 'complaints', 'حدّ قائمة المعيَّن لهم', 'number', '50'],
            ['complaints.notify.on_reply', 'complaints', 'إشعار المستخدم عند الردّ', 'bool', '1'],
            ['complaints.notify.on_close', 'complaints', 'إشعار المستخدم عند الإغلاق', 'bool', '1'],
            ['complaints.notify.reply_title', 'complaints', 'عنوان إشعار الردّ', 'string', 'وصلك ردّ على رسالتك'],
            ['complaints.notify.close_title', 'complaints', 'عنوان إشعار الإغلاق', 'string', 'قفلنا رسالتك — وشكرًا لوقتك'],
            ['complaints.notify.body_limit', 'complaints', 'أقصى أحرف نصّ الإشعار', 'number', '120'],

            // ---------------- التدقيق
            ['admin_content.audit.enabled', 'admin_content', 'تفعيل سجلّ التدقيق', 'bool', '1'],
            ['admin_content.audit.page_size', 'admin_content', 'عدد صفوف سجلّ التدقيق', 'number', '20'],
            ['admin_content.audit.max_value_length', 'admin_content', 'أقصى طول للقيمة المسجَّلة', 'number', '500'],

            // ---------------- واجهة
            ['ux.toast.seconds', 'ux', 'مدّة الـToast (ثوانٍ)', 'number', '5'],
            ['ux.filters.min_rows', 'ux', 'أقلّ عدد صفوف تظهر معه الفلاتر', 'number', '10'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $value]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $value,
                'default_value' => $value,
            ]);
        }

        Cache::forget('settings');
    }

    // ============================================================== التدريب

    private function paths(): void
    {
        $paths = [
            ['مسار التأسيس', 'Foundations', 'أوّل خطوة لكلّ متدرّب جديد — أساسيّات لازمة قبل أيّ تخصّص.', false, 50],
            ['مسار القيادة', 'Leadership', 'مهارات إدارة الفريق والاجتماعات واتّخاذ القرار.', true, 120],
        ];

        foreach ($paths as $index => [$ar, $en, $description, $forced, $price]) {
            $path = LearningPath::updateOrCreate(['slug' => Str::slug($en)], [
                'name_ar' => $ar,
                'name_en' => $en,
                'description_ar' => $description,
                'forced_order' => $forced,
                'sort_order' => $index + 1,
                'status' => 'published',
                'published_at' => now(),
                'exam_price_coins' => $price,
            ]);

            $this->coursesFor($path, $index);
        }

        // ⭐ تدريب مشترك بين المسارين — إثبات أنّ التدريب يجوز في أكثر من مسار (12.4-أ)
        $shared = Course::query()->where('slug', 'shared-communication')->first()
            ?? Course::create([
                'slug' => 'shared-communication',
                'name_ar' => 'التواصل الفعّال',
                'name_en' => 'Effective Communication',
                'cert_name_ar' => 'شهادة التواصل الفعّال',
                'description_ar' => 'تدريب مشترك بين أكتر من مسار — لأنّ التواصل مطلوب في كلّ حتّة.',
                'price_coins' => 80,
                'status' => 'published',
                'published_at' => now(),
            ]);

        foreach (LearningPath::query()->pluck('id') as $order => $pathId) {
            CourseLearningPath::firstOrCreate(
                ['course_id' => $shared->id, 'learning_path_id' => $pathId],
                ['sort_order' => $order + 1],
            );
        }
    }

    private function coursesFor(LearningPath $path, int $index): void
    {
        /*
         | ⭐ بيانات العرض لازم تحمل **أكثر من حالة** (12.4-هـ: مسودّة/مجدول/منشور
         | /مؤرشف): بلا مسودّةٍ واحدة على الأقلّ يصير فلتر الحالة بلا مادّة،
         | و«معاينة كطالب **قبل النشر**» بلا تدريبٍ غير منشور تُعايَن عليه.
         | فالأوّل «تحت التجهيز» — مسودّة بلا تاريخ نشر، والباقي منشور.
         */
        $definitions = [
            ['مقدّمة في العمل التطوّعيّ', 'شهادة مقدّمة العمل التطوّعيّ', 0, true, 'draft'],
            ['إدارة الوقت والمهامّ', 'شهادة إدارة الوقت', 60, false, 'published'],
        ];

        foreach ($definitions as $order => [$name, $certName, $price, $free, $status]) {
            $course = Course::updateOrCreate(['slug' => Str::slug($name.'-'.$path->id)], [
                'name_ar' => $name,
                'cert_name_ar' => $certName,
                'description_ar' => 'تدريب تجريبيّ داخل '.$path->name_ar.'.',
                'is_free' => $free,
                'price_coins' => $price,
                'xp_max' => 50,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ]);

            CourseLearningPath::firstOrCreate(
                ['course_id' => $course->id, 'learning_path_id' => $path->id],
                ['sort_order' => $order + 1],
            );

            $this->contentFor($course);
        }
    }

    private function contentFor(Course $course): void
    {
        if (Section::query()->where('course_id', $course->id)->exists()) {
            return;
        }

        foreach (['البداية', 'التطبيق'] as $order => $title) {
            $section = Section::create([
                'course_id' => $course->id,
                'title_ar' => $title,
                'sort_order' => $order + 1,
            ]);

            $lesson = Lesson::create([
                'section_id' => $section->id,
                'title_ar' => 'درس '.$title,
                'type' => $order === 0 ? 'video' : 'document',
                'video_provider' => $order === 0 ? 'youtube' : null,
                'video_id' => $order === 0 ? 'dQw4w9WgXcQ' : null,
                'content' => $order === 0 ? null : 'نصّ الدرس التجريبيّ — يشرح الفكرة في سطور قليلة.',
                'sort_order' => 1,
                'is_free_preview' => $order === 0,
            ]);

            LessonQuestion::create([
                'lesson_id' => $lesson->id,
                'type' => 'otp',
                'prompt' => 'كام مبدأ اتكلّمنا عنه في الدرس؟',
                'placeholder' => 'اكتب الرقم',
                'correct_answer' => '3',
                'is_general' => true,
                'sort_order' => 1,
            ]);
        }
    }

    private function media(): void
    {
        $items = [
            ['غلاف مسار التأسيس.png', 'media/demo-foundations.png', 'image/png', 'أغلفة'],
            ['دليل المتدرّب.pdf', 'media/demo-guide.pdf', 'application/pdf', 'مرفقات'],
        ];

        foreach ($items as [$name, $path, $mime, $folder]) {
            MediaItem::updateOrCreate(['path' => $path], [
                'disk' => 'public',
                'name' => $name,
                'mime' => $mime,
                'size' => 120_000,
                'hash' => hash('sha256', $path),
                'tags' => ['تجريبيّ'],
                'folder' => $folder,
            ]);
        }
    }

    // ============================================================== الشهادات

    private function certificates(): void
    {
        $designer = app(TemplateDesigner::class);

        // التصميم الافتراضيّ الجاهز لكلّ نوع قائم — فالشاشة لا تظهر فارغة أبدًا
        foreach (CertificateType::query()->get() as $type) {
            $designer->templatesFor($type);
        }
    }

    // ============================================================== التوجيه والدعم

    private function guidance(): void
    {
        $author = User::query()->orderBy('id')->first();

        Announcement::updateOrCreate(['title' => 'دفعة جديدة من التدريبات كلّ اثنين'], [
            'body' => "بنضيف تدريبات جديدة كلّ اثنين الساعة 10 صباحًا.\nافتح تدريباتي وشوف الجديد.",
            'type' => 'general',
            'audience' => ['type' => 'all'],
            'reactions_enabled' => true,
            'requires_acknowledge' => false,
            'status' => 'published',
            'created_by' => $author?->id,
        ]);

        Announcement::updateOrCreate(['title' => 'سياسة الشهادات المحدَّثة'], [
            'body' => 'قرأت السياسة الجديدة؟ أقرّ من الزرّ عشان نعرف إنّها وصلتك — والإقرار بيتحسب مرّة واحدة بس.',
            'type' => 'critical',
            'audience' => ['type' => 'role', 'keys' => ['trainee']],
            'requires_acknowledge' => true,
            'acknowledge_xp' => 10,
            'push_to_notifications' => false,
            'status' => 'published',
            'created_by' => $author?->id,
        ]);

        $articles = [
            ['إزاي أبدأ أوّل تدريب؟', 'البداية', 'افتح «تدريباتي» واختر المسار، وبعدين ابدأ أوّل درس.'],
            ['إزاي أنزّل شهادتي؟', 'الشهادات', 'من «مكتبتي» اضغط على الشهادة، وهتلاقي زرّ التنزيل ورابط التحقّق.'],
        ];

        foreach ($articles as [$title, $category, $body]) {
            HelpArticle::updateOrCreate(['slug' => Str::slug($title) ?: Str::random(8)], [
                'title' => $title,
                'category' => $category,
                'body' => $body,
                'tags' => ['شائع'],
                'status' => 'published',
                'helpful_yes' => 12,
                'helpful_no' => 2,
                'views' => 140,
            ]);
        }

        if ($author) {
            $complaint = Complaint::updateOrCreate(['number' => 'CMP-DEMO-1'], [
                'user_id' => $author->id,
                'type' => 'complaint',
                'category' => 'المحتوى التدريبيّ',
                'title' => 'درس الفيديو مش بيفتح عندي',
                'body' => 'جرّبت من الموبايل واللابتوب ونفس المشكلة — الفيديو بيفضل بيلفّ.',
                'status' => 'open',
                'wants_contact' => true,
                'contact_channel' => 'واتساب',
            ]);

            ComplaintMessage::firstOrCreate([
                'complaint_id' => $complaint->id,
                'user_id' => $author->id,
                'body' => 'ملاحظة داخليّة: نتأكّد من رابط اليوتيوب في الدرس.',
            ], ['is_internal' => true]);
        }
    }
}
