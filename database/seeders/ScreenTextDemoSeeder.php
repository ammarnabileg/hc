<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * 🖥️ **نصوص شاشات القوالب** — كلّ جملةٍ يقرؤها المستخدم على قوالب بليد خارج
 * `admin/**` و`volunteer/**` و`partials/**` و`store/**` (التنصيب · الحساب ·
 * الإشعارات · البروفايل · الشهادات · الدعوات · الدليل · المكوّنات المشتركة …).
 *
 * وافتراضيُّ كلّ مفتاح **هو النصّ الذي كان محروقًا حرفًا بحرف** (2.13-ب)، فلا
 * يتغيّر عرضٌ قائم — والفرق الوحيد أنّ المالك صار يقدر على تحريره من لوحته.
 *
 * ووحدة المفتاح **جملةٌ كاملة كما يقرؤها المستخدم**: الجملة التي يقطعها
 * `{{ $name }}` تبقى مفتاحًا واحدًا بعنصرٍ نائب `:a1` يملؤه `strtr()`.
 *
 * ⛔ وما **لم** يُنقَل عمدًا (مسجَّلٌ بأرقامه في العتبة لا مخفيّ):
 * - **15:** مرادفات الأيقونات العربيّة في `components/icon.blade.php` —
 *   **مفاتيح مصفوفةٍ داخليّة** («مهمّة» ⟵ `task`) لا تُعرَض على شاشة،
 *   ونقلُها يكسر المطابقة نفسها.
 * - **19:** قيم `data-keywords` في `account/settings/**` — **كلمات بحثٍ
 *   داخليّة** يطابقها سكربت البحث ولا تُعرَض، و2.13-أ تخصّ «النصوص
 *   **الظاهرة** للمستخدم». وهو قرارٌ سابقٌ منصوصٌ في `account/_STATUS.md`
 *   ولم نخالفه: نقلُها لتُترجَم قرارُ مالكٍ لا اجتهادُ منفِّذ.
 *
 * ⚠️ ولا يُشغَّل مع بيانات العرض: `SettingDefinitionsSeeder` يستدعي
 * ميثود `settings()` وحدها في **مسار الإنتاج** (BUILD.md §3).
 */
class ScreenTextDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
    }

    /**
     * تعريفات نصوص القوالب — [المفتاح · المجموعة · اللافتة · الافتراضيّ].
     */
    public function settings(): void
    {
        $rows = [
            // ---- resources/views/account/settings/index.blade.php
            // ---- resources/views/account/settings/partials/security.blade.php
            // ---- resources/views/announcements/index.blade.php
            ['announcements.index.section_1', 'announcements', 'index: التعليمات', 'التعليمات'],
            ['announcements.index.php_1', 'announcements', 'index: عندك :a1 منشور لسّه ما اتقروش', 'عندك :a1 منشور لسّه ما اتقروش'],
            ['announcements.index.php_2', 'announcements', 'index: كلّ التعليمات مقروءة — تمام ✓', 'كلّ التعليمات مقروءة — تمام ✓'],
            ['announcements.index.title_1', 'announcements', 'index: التعليمات', 'التعليمات'],
            ['announcements.index.breadcrumbs_1', 'announcements', 'index: الرئيسيّة', 'الرئيسيّة'],
            ['announcements.index.breadcrumbs_2', 'announcements', 'index: التعليمات', 'التعليمات'],
            ['announcements.index.text_1', 'announcements', 'index: تعليم الكلّ كمقروء', 'تعليم الكلّ كمقروء'],
            ['announcements.index.text_2', 'announcements', 'index: غير المقروء', 'غير المقروء'],
            ['announcements.index.text_3', 'announcements', 'index: النوع', 'النوع'],
            ['announcements.index.text_4', 'announcements', 'index: الكلّ', 'الكلّ'],
            ['announcements.index.text_5', 'announcements', 'index: بحث', 'بحث'],
            ['announcements.index.placeholder_1', 'announcements', 'index: ابحث بالعنوان…', 'ابحث بالعنوان…'],
            ['announcements.index.action_1', 'announcements', 'index: الرجوع للرئيسيّة', 'الرجوع للرئيسيّة'],
            ['announcements.index.text_6', 'announcements', 'index: عرض المزيد (:a1)', 'عرض المزيد (:a1)'],
            ['announcements.index.expr_1', 'announcements', 'index: افتح الرابط', 'افتح الرابط'],
            ['announcements.index.title_2', 'announcements', 'index: تفاصيل المنشور', 'تفاصيل المنشور'],
            ['announcements.index.text_7', 'announcements', 'index: تعليم الكلّ كمقروء (:a1)', 'تعليم الكلّ كمقروء (:a1)'],
            ['announcements.index.js_1', 'announcements', 'index: افتح الرابط', 'افتح الرابط'],
            // ---- resources/views/announcements/partials/card.blade.php
            ['announcements.card.title_1', 'announcements', 'card: منشور مثبَّت', 'منشور مثبَّت'],
            ['announcements.card.aria_label_1', 'announcements', 'card: مثبَّت', 'مثبَّت'],
            ['announcements.card.text_1', 'announcements', 'card: جديد', 'جديد'],
            ['announcements.card.text_2', 'announcements', 'card: التفاصيل', 'التفاصيل'],
            ['announcements.card.expr_1', 'announcements', 'card: افتح', 'افتح'],
            ['announcements.card.aria_label_2', 'announcements', 'card: استطلاع', 'استطلاع'],
            ['announcements.card.text_3', 'announcements', 'card: اختيارك', 'اختيارك'],
            ['announcements.card.text_4', 'announcements', 'card: إجماليّ الأصوات', 'إجماليّ الأصوات'],
            ['announcements.card.aria_label_3', 'announcements', 'card: تفاعل', 'تفاعل'],
            ['announcements.card.label_1', 'announcements', 'card: أقررتَ بقراءته', 'أقررتَ بقراءته'],
            ['announcements.card.text_5', 'announcements', 'card: مرّة واحدة', 'مرّة واحدة'],
            ['announcements.card.text_6', 'announcements', 'card: تعليم كمقروء', 'تعليم كمقروء'],
            // ---- resources/views/auth/login.blade.php
            ['auth.login.section_1', 'accounts', 'login: تسجيل الدخول', 'تسجيل الدخول'],
            ['auth.login.text_1', 'accounts', 'login: أهلًا بعودتك', 'أهلًا بعودتك'],
            ['auth.login.text_2', 'accounts', 'login: ادخل بالكود أو البريد.', 'ادخل بالكود أو البريد.'],
            ['auth.login.label_1', 'accounts', 'login: الكود أو البريد', 'الكود أو البريد'],
            ['auth.login.label_2', 'accounts', 'login: كلمة السرّ', 'كلمة السرّ'],
            ['auth.login.text_3', 'accounts', 'login: فكّرني', 'فكّرني'],
            ['auth.login.text_4', 'accounts', 'login: دخول', 'دخول'],
            ['auth.login.text_5', 'accounts', 'login: نسيت كلمة السرّ؟', 'نسيت كلمة السرّ؟'],
            ['auth.login.text_6', 'accounts', 'login: استرجعها', 'استرجعها'],
            ['auth.login.text_7', 'accounts', 'login: لسّه مامعاكش حساب؟', 'لسّه مامعاكش حساب؟'],
            ['auth.login.text_8', 'accounts', 'login: سجّل مجّانًا', 'سجّل مجّانًا'],
            // ---- resources/views/auth/password/request.blade.php
            ['auth.password_request.section_1', 'security', 'request: نسيت كلمة السرّ', 'نسيت كلمة السرّ'],
            ['auth.password_request.label_1', 'security', 'request: البريد', 'البريد'],
            ['auth.password_request.text_1', 'security', 'request: فاكرها؟', 'فاكرها؟'],
            ['auth.password_request.text_2', 'security', 'request: ارجع للدخول', 'ارجع للدخول'],
            // ---- resources/views/auth/password/reset.blade.php
            ['auth.password_reset.section_1', 'security', 'reset: كلمة سرّ جديدة', 'كلمة سرّ جديدة'],
            ['auth.password_reset.foreach_1', 'security', 'reset: كلمة السرّ الجديدة', 'كلمة السرّ الجديدة'],
            ['auth.password_reset.foreach_2', 'security', 'reset: أكّدها تاني', 'أكّدها تاني'],
            // ---- resources/views/auth/password/sent.blade.php
            ['auth.password_sent.section_1', 'security', 'sent: بعتنالك', 'بعتنالك'],
            // ---- resources/views/auth/pending.blade.php
            ['auth.pending.section_1', 'accounts', 'pending: حسابك تحت المراجعة', 'حسابك تحت المراجعة'],
            ['auth.pending.text_1', 'accounts', 'pending: حسابك تحت المراجعة', 'حسابك تحت المراجعة'],
            ['auth.pending.text_2', 'accounts', 'pending: تسجيل الخروج', 'تسجيل الخروج'],
            // ---- resources/views/cards/partials/qr.blade.php
            ['volunteer_card.qr.aria_label_1', 'volunteer', 'qr: امسح للتحقّق من البطاقة', 'امسح للتحقّق من البطاقة'],
            ['volunteer_card.qr.text_1', 'volunteer', 'qr: امسح للتحقّق من البطاقة', 'امسح للتحقّق من البطاقة'],
            // ---- resources/views/cards/show.blade.php
            ['volunteer_card.show.section_1', 'volunteer', 'show: بطاقة المتطوّع — :a1', 'بطاقة المتطوّع — :a1'],
            ['volunteer_card.show.label_1', 'volunteer', 'show: منتهية', 'منتهية'],
            ['volunteer_card.show.label_2', 'volunteer', 'show: سارية', 'سارية'],
            ['volunteer_card.show.text_1', 'volunteer', 'show: نادي التميّز', 'نادي التميّز'],
            ['volunteer_card.show.text_2', 'volunteer', 'show: القسم', 'القسم'],
            ['volunteer_card.show.text_3', 'volunteer', 'show: المسار', 'المسار'],
            ['volunteer_card.show.text_4', 'volunteer', 'show: مدّة الخدمة', 'مدّة الخدمة'],
            ['volunteer_card.show.text_5', 'volunteer', 'show: الدولة/المحافظة', 'الدولة/المحافظة'],
            ['volunteer_card.show.text_6', 'volunteer', 'show: تاريخ الانضمام', 'تاريخ الانضمام'],
            ['volunteer_card.show.aria_label_1', 'volunteer', 'show: صفحة التحقّق', 'صفحة التحقّق'],
            ['volunteer_card.show.text_7', 'volunteer', 'show: مشاركة', 'مشاركة'],
            ['volunteer_card.show.js_1', 'volunteer', 'show: مشاركة', 'مشاركة'],
            ['volunteer_card.show.js_2', 'volunteer', 'show: اتنسخ ✓', 'اتنسخ ✓'],
            ['volunteer_card.show.js_3', 'volunteer', 'show: انسخ الرابط من المتصفّح', 'انسخ الرابط من المتصفّح'],
            // ---- resources/views/cards/verify.blade.php
            ['volunteer_card.verify.section_1', 'volunteer', 'verify: التحقّق من بطاقة المتطوّع', 'التحقّق من بطاقة المتطوّع'],
            ['volunteer_card.verify.label_1', 'volunteer', 'verify: سارية', 'سارية'],
            ['volunteer_card.verify.label_2', 'volunteer', 'verify: منتهية', 'منتهية'],
            ['volunteer_card.verify.text_1', 'volunteer', 'verify: كود العضو', 'كود العضو'],
            ['volunteer_card.verify.text_2', 'volunteer', 'verify: رقم البطاقة', 'رقم البطاقة'],
            ['volunteer_card.verify.text_3', 'volunteer', 'verify: تاريخ الإصدار', 'تاريخ الإصدار'],
            ['volunteer_card.verify.text_4', 'volunteer', 'verify: تاريخ الانتهاء', 'تاريخ الانتهاء'],
            ['volunteer_card.verify.text_5', 'volunteer', 'verify: فتح البطاقة', 'فتح البطاقة'],
            // ---- resources/views/certificates/partials/certificate-modal.blade.php
            ['certificates.modal.php_1', 'certificates', 'certificate-modal: [المسار]', '[المسار]'],
            ['certificates.modal.php_2', 'certificates', 'certificate-modal: [الاسم]', '[الاسم]'],
            ['certificates.modal.php_3', 'certificates', 'certificate-modal: [الكود]', '[الكود]'],
            // ---- resources/views/certificates/verify.blade.php
            ['certificates.verify_page.php_1', 'certificates', 'verify: [الاسم]', '[الاسم]'],
            ['certificates.verify_page.php_2', 'certificates', 'verify: [الشهادة]', '[الشهادة]'],
            ['certificates.verify_page.php_3', 'certificates', 'verify: [الكود]', '[الكود]'],
            ['certificates.verify_page.php_4', 'certificates', 'verify: [الاسم]', '[الاسم]'],
            ['certificates.verify_page.php_5', 'certificates', 'verify: [الشهادة]', '[الشهادة]'],
            ['certificates.verify_page.php_6', 'certificates', 'verify: [التاريخ]', '[التاريخ]'],
            // ---- resources/views/components/advanced-toggle.blade.php
            ['ux.advanced_toggle.title_expr_1', 'ux', 'advanced-toggle: اقفل الوضع المتقدّم', 'اقفل الوضع المتقدّم'],
            ['ux.advanced_toggle.title_expr_2', 'ux', 'advanced-toggle: افتح كلّ اللي اتخفى', 'افتح كلّ اللي اتخفى'],
            // ---- resources/views/components/command-palette.blade.php
            ['ux.command_palette.aria_label_1', 'ux', 'command-palette: البحث الموحّد', 'البحث الموحّد'],
            ['ux.command_palette.placeholder_1', 'ux', 'command-palette: اكتب اسم صفحة أو شخص أو مهمّة…', 'اكتب اسم صفحة أو شخص أو مهمّة…'],
            ['ux.command_palette.aria_label_2', 'ux', 'command-palette: البحث الموحّد', 'البحث الموحّد'],
            ['ux.command_palette.aria_label_3', 'ux', 'command-palette: إغلاق', 'إغلاق'],
            ['ux.command_palette.text_1', 'ux', 'command-palette: اكتب حرفين وهنوصّلك على طول.', 'اكتب حرفين وهنوصّلك على طول.'],
            // ---- resources/views/components/empty.blade.php
            ['ux.empty_state.props_1', 'ux', 'empty: مفيش حاجة هنا', 'مفيش حاجة هنا'],
            // ---- resources/views/components/export-image.blade.php
            ['images.export_panel.aria_label_1', 'images', 'export-image: استخراج كصورة', 'استخراج كصورة'],
            ['images.export_panel.title_1', 'images', 'export-image: استخراج كصورة', 'استخراج كصورة'],
            ['images.export_panel.text_1', 'images', 'export-image: استخراج كصورة', 'استخراج كصورة'],
            ['images.export_panel.title_2', 'images', 'export-image: استخراج كصورة', 'استخراج كصورة'],
            ['images.export_panel.text_2', 'images', 'export-image: اللي هيظهر', 'اللي هيظهر'],
            ['images.export_panel.text_3', 'images', 'export-image: أفضل 10', 'أفضل 10'],
            ['images.export_panel.text_4', 'images', 'export-image: أفضل 3', 'أفضل 3'],
            ['images.export_panel.text_5', 'images', 'export-image: صفّي أنا', 'صفّي أنا'],
            ['images.export_panel.text_6', 'images', 'export-image: المقاس', 'المقاس'],
            ['images.export_panel.text_7', 'images', 'export-image: القالب', 'القالب'],
            ['images.export_panel.text_8', 'images', 'export-image: بلا قالب — تصميم المنصّة', 'بلا قالب — تصميم المنصّة'],
            ['images.export_panel.text_9', 'images', 'export-image: طبقة الفريم', 'طبقة الفريم'],
            ['images.export_panel.text_10', 'images', 'export-image: فوق المحتوى ↑', 'فوق المحتوى ↑'],
            ['images.export_panel.text_11', 'images', 'export-image: تحت المحتوى ↓', 'تحت المحتوى ↓'],
            ['images.export_panel.text_12', 'images', 'export-image: إظهار الأفاتارات', 'إظهار الأفاتارات'],
            ['images.export_panel.text_13', 'images', 'export-image: الصورة بتحمل تاريخ اللقطة وشعار المنصّة — والمحافظة بتظهر دا', 'الصورة بتحمل تاريخ اللقطة وشعار المنصّة — والمحافظة بتظهر دايمًا.'],
            ['images.export_panel.text_14', 'images', 'export-image: نزّل الصورة', 'نزّل الصورة'],
            ['images.export_panel.text_15', 'images', 'export-image: افتحها للمعاينة', 'افتحها للمعاينة'],
            // ---- resources/views/components/filters.blade.php
            ['ux.filters.text_1', 'ux', 'filters: فلاتر متقدّمة', 'فلاتر متقدّمة'],
            // ---- resources/views/components/first-run.blade.php
            ['ux.first_run.aria_label_1', 'ux', 'first-run: أوّل مرّة هنا', 'أوّل مرّة هنا'],
            // ---- resources/views/components/form/stepper.blade.php
            ['ux.stepper.text_1', 'ux', 'stepper: السابق', 'السابق'],
            ['ux.stepper.text_2', 'ux', 'stepper: التالي', 'التالي'],
            // ---- resources/views/components/modal.blade.php
            ['ux.modal.aria_label_1', 'ux', 'modal: إغلاق', 'إغلاق'],
            // ---- resources/views/components/page-header.blade.php
            ['ux.page_header.aria_label_expr_1', 'ux', 'page-header: فكّ تثبيت الصفحة', 'فكّ تثبيت الصفحة'],
            ['ux.page_header.aria_label_expr_2', 'ux', 'page-header: ثبّت الصفحة أعلى السايد بار', 'ثبّت الصفحة أعلى السايد بار'],
            ['ux.page_header.title_expr_1', 'ux', 'page-header: مثبَّتة', 'مثبَّتة'],
            ['ux.page_header.title_expr_2', 'ux', 'page-header: ثبّت الصفحة', 'ثبّت الصفحة'],
            // ---- resources/views/components/saved-views.blade.php
            ['ux.saved_views.aria_label_1', 'ux', 'saved-views: امسح العرض', 'امسح العرض'],
            ['ux.saved_views.text_1', 'ux', 'saved-views: احفظ العرض', 'احفظ العرض'],
            ['ux.saved_views.text_2', 'ux', 'saved-views: اسم العرض', 'اسم العرض'],
            ['ux.saved_views.placeholder_1', 'ux', 'saved-views: مثلًا: محافظتي — آخر 7 أيّام', 'مثلًا: محافظتي — آخر 7 أيّام'],
            ['ux.saved_views.text_3', 'ux', 'saved-views: احفظ', 'احفظ'],
            // ---- resources/views/components/table.blade.php
            ['ux.table.text_1', 'ux', 'table: بنعرض أهمّ :a1 أعمدة — «وضع متقدّم» أعلى الصفحة بيفتح الباقي', 'بنعرض أهمّ :a1 أعمدة — «وضع متقدّم» أعلى الصفحة بيفتح الباقي.'],
            // ---- resources/views/components/undo-toast.blade.php
            ['ux.undo_toast.text_1', 'ux', 'undo-toast: زرّ التراجع في الـToast', 'تراجع'],
            // ---- resources/views/cv/public.blade.php
            ['cv.public_page.section_1', 'cv', 'public: :a1 — السيرة الذاتيّة', ':a1 — السيرة الذاتيّة'],
            ['cv.public_page.section_2', 'cv', 'public: السيرة الذاتيّة لـ:a1', 'السيرة الذاتيّة لـ:a1'],
            // ---- resources/views/exams/result.blade.php
            ['exams.result.expr_1', 'exams', 'result: [الاسم]', '[الاسم]'],
            ['exams.result.expr_2', 'exams', 'result: [الاسم]', '[الاسم]'],
            // ---- resources/views/growth/admin/index.blade.php
            ['growth.admin_index.section_1', 'growth', 'index: حلقات النموّ', 'حلقات النموّ'],
            ['growth.admin_index.title_1', 'growth', 'index: حلقات النموّ', 'حلقات النموّ'],
            ['growth.admin_index.subtitle_1', 'growth', 'index: كلّ رقم ونصّ في حلقات النموّ والاكتساب والتتبّع — من هنا لا ', 'كلّ رقم ونصّ في حلقات النموّ والاكتساب والتتبّع — من هنا لا من الكود.'],
            ['growth.admin_index.breadcrumbs_1', 'growth', 'index: لوحة الإدارة', 'لوحة الإدارة'],
            ['growth.admin_index.breadcrumbs_2', 'growth', 'index: حلقات النموّ', 'حلقات النموّ'],
            ['growth.admin_index.message_1', 'growth', 'index: الإعدادات دي لسّه ما اتزرعتش — شغّل سيدر مجال النموّ.', 'الإعدادات دي لسّه ما اتزرعتش — شغّل سيدر مجال النموّ.'],
            ['growth.admin_index.text_1', 'growth', 'index: حقول «أكمل ملفك»', 'حقول «أكمل ملفك»'],
            ['growth.admin_index.text_2', 'growth', 'index: عدّلها من', 'عدّلها من'],
            ['growth.admin_index.text_3', 'growth', 'index: قوالب صور الروابط', 'قوالب صور الروابط'],
            ['growth.admin_index.text_4', 'growth', 'index: نصائح الكارت الأسبوعيّ', 'نصائح الكارت الأسبوعيّ'],
            ['growth.admin_index.text_5', 'growth', 'index: الأحداث الثمانية', 'الأحداث الثمانية'],
            ['growth.admin_index.text_6', 'growth', 'index: رفض المستخدم بيوقف البكسل وأحداث الخادم له فعليًّا — مش شكلي', 'رفض المستخدم بيوقف البكسل وأحداث الخادم له فعليًّا — مش شكليًّا.'],
            ['growth.admin_index.text_7', 'growth', 'index: بيوقّف التتبّع كلّه بمفتاح واحد.', 'بيوقّف التتبّع كلّه بمفتاح واحد.'],
            // ---- resources/views/growth/articles/index.blade.php
            ['articles.index.text_1', 'articles', 'index: ابحث في المقالات', 'ابحث في المقالات'],
            ['articles.index.text_2', 'articles', 'index: التصنيف', 'التصنيف'],
            ['articles.index.text_3', 'articles', 'index: كلّ التصنيفات', 'كلّ التصنيفات'],
            ['articles.index.text_4', 'articles', 'index: بحث', 'بحث'],
            // ---- resources/views/growth/articles/show.blade.php
            ['articles.show.text_1', 'articles', 'show: نُشر', 'نُشر'],
            ['articles.show.text_2', 'articles', 'show: آخر تحديث', 'آخر تحديث'],
            ['articles.show.text_3', 'articles', 'show: افتح', 'افتح'],
            ['articles.show.aria_label_1', 'articles', 'show: مشاركة المقال', 'مشاركة المقال'],
            ['articles.show.text_4', 'articles', 'show: واتساب', 'واتساب'],
            ['articles.show.text_5', 'articles', 'show: لينكدإن', 'لينكدإن'],
            ['articles.show.text_6', 'articles', 'show: انسخ الرابط', 'انسخ الرابط'],
            ['articles.show.aria_label_2', 'articles', 'show: مقالات قريبة', 'مقالات قريبة'],
            ['articles.show.js_1', 'articles', 'show: اتنسخ ✓', 'اتنسخ ✓'],
            ['articles.show.js_2', 'articles', 'show: انسخه يدويًّا', 'انسخه يدويًّا'],
            // ---- resources/views/growth/components/completion-bar.blade.php
            ['growth.completion_bar.aria_label_1', 'growth', 'completion-bar: إكمال الملفّ الشخصيّ', 'إكمال الملفّ الشخصيّ'],
            ['growth.completion_bar.aria_label_2', 'growth', 'completion-bar: إخفاء التذكير', 'إخفاء التذكير'],
            // ---- resources/views/growth/content-kit.blade.php
            ['growth.content_kit.aria_label_1', 'growth', 'content-kit: الكارت الأسبوعيّ', 'الكارت الأسبوعيّ'],
            ['growth.content_kit.text_1', 'growth', 'content-kit: بيتغيّر كلّ :a1 يوم', 'بيتغيّر كلّ :a1 يوم'],
            ['growth.content_kit.text_2', 'growth', 'content-kit: نزّل الصورة', 'نزّل الصورة'],
            ['growth.content_kit.aria_label_2', 'growth', 'content-kit: رابط الدعوة', 'رابط الدعوة'],
            ['growth.content_kit.text_3', 'growth', 'content-kit: انسخ', 'انسخ'],
            ['growth.content_kit.aria_label_3', 'growth', 'content-kit: نصوص جاهزة', 'نصوص جاهزة'],
            ['growth.content_kit.text_4', 'growth', 'content-kit: انسخ النصّ', 'انسخ النصّ'],
            ['growth.content_kit.aria_label_4', 'growth', 'content-kit: قوالب الصور', 'قوالب الصور'],
            ['growth.content_kit.js_1', 'growth', 'content-kit: اتنسخ ✓', 'اتنسخ ✓'],
            ['growth.content_kit.js_2', 'growth', 'content-kit: انسخه يدويًّا', 'انسخه يدويًّا'],
            ['growth.content_kit.js_3', 'growth', 'content-kit: اتنسخ ✓', 'اتنسخ ✓'],
            ['growth.content_kit.js_4', 'growth', 'content-kit: انسخه يدويًّا', 'انسخه يدويًّا'],
            // ---- resources/views/growth/invite-board.blade.php
            ['growth.invite_board.text_1', 'growth', 'invite-board: استخرج كصورة', 'استخرج كصورة'],
            ['growth.invite_board.text_2', 'growth', 'invite-board: الشهر', 'الشهر'],
            ['growth.invite_board.action_1', 'growth', 'invite-board: ادعُ صديقك', 'ادعُ صديقك'],
            ['growth.invite_board.text_3', 'growth', 'invite-board: الداعي', 'الداعي'],
            ['growth.invite_board.text_4', 'growth', 'invite-board: دعوات مكتملة', 'دعوات مكتملة'],
            ['growth.invite_board.text_5', 'growth', 'invite-board: إجمالي الدعوات', 'إجمالي الدعوات'],
            ['growth.invite_board.text_6', 'growth', 'invite-board: مكتملة · :a1 إجمالي', 'مكتملة · :a1 إجمالي'],
            ['growth.invite_board.text_7', 'growth', 'invite-board: استخرج اللوحة كصورة', 'استخرج اللوحة كصورة'],
            // ---- resources/views/growth/preview/course.blade.php
            ['growth.preview_course.breadcrumbs_1', 'growth', 'course: التدريبات', 'التدريبات'],
            ['growth.preview_course.text_1', 'growth', 'course: سجّل حسابك', 'سجّل حسابك'],
            // ---- resources/views/growth/preview/lesson.blade.php
            ['growth.preview_lesson.text_1', 'growth', 'lesson: الدرس التالي:', 'الدرس التالي:'],
            ['growth.preview_lesson.text_2', 'growth', 'lesson: كلّ دروس المعاينة', 'كلّ دروس المعاينة'],
            ['growth.preview_lesson.expr_1', 'growth', 'lesson: سجّل حسابك', 'سجّل حسابك'],
            ['growth.preview_lesson.expr_2', 'growth', 'lesson: سجّل حسابك', 'سجّل حسابك'],
            // ---- resources/views/growth/profile-completion.blade.php
            ['growth.profile_completion.label_1', 'growth', 'profile-completion: ناقص', 'ناقص'],
            // ---- resources/views/home/ambassadors.blade.php
            ['ambassadors.page.text_1', 'ambassadors', 'ambassadors: دعوة مفعّلة', 'دعوة مفعّلة'],
            ['ambassadors.page.text_2', 'ambassadors', 'ambassadors: باقي :a1 دعوة مفعّلة على لقب «:a2».', 'باقي :a1 دعوة مفعّلة على لقب «:a2».'],
            // ---- resources/views/home/partials/celebration.blade.php
            ['home.celebration.aria_label_1', 'home', 'celebration: إغلاق', 'إغلاق'],
            // ---- resources/views/home/partials/footer.blade.php
            ['home.footer.aria_label_1', 'home', 'footer: روابط عامّة', 'روابط عامّة'],
            // ---- resources/views/home/partials/learning.blade.php
            ['home.learning.text_1', 'home', 'learning: أوّل :a1 درس معاينة مجّانيّة', 'أوّل :a1 درس معاينة مجّانيّة'],
            ['home.learning.expr_1', 'home', 'learning: متاح مجّانًا', 'متاح مجّانًا'],
            ['home.learning.expr_2', 'home', 'learning: تدريب مدفوع', 'تدريب مدفوع'],
            // ---- resources/views/home/partials/nav.blade.php
            ['home.nav.aria_label_1', 'home', 'nav: روابط الحساب', 'روابط الحساب'],
            // ---- resources/views/layouts/admin.blade.php
            ['ux.layout_admin.yield_1', 'ux', 'admin: لوحة الإدارة', 'لوحة الإدارة'],
            ['ux.layout_admin.label_1', 'ux', 'admin: مش هينفع نحفظ', 'مش هينفع نحفظ'],
            // ---- resources/views/layouts/volunteer.blade.php
            ['ux.layout_volunteer.yield_1', 'ux', 'volunteer: لوحة التطوّع', 'لوحة التطوّع'],
            // ---- resources/views/learning/lesson.blade.php
            ['learning.lesson_view.js_1', 'learning', 'lesson: اتحسبت المشاهدة ✓', 'اتحسبت المشاهدة ✓'],
            ['learning.lesson_view.js_2', 'learning', 'lesson: المشاهدة: ', 'المشاهدة: '],
            // ---- resources/views/notifications/index.blade.php
            ['notifications.index.section_1', 'notifications', 'index: الإشعارات', 'الإشعارات'],
            ['notifications.index.php_1', 'notifications', 'index: عندك :a1 إشعار غير مقروء', 'عندك :a1 إشعار غير مقروء'],
            ['notifications.index.php_2', 'notifications', 'index: مفيش غير مقروء — تمام ✓', 'مفيش غير مقروء — تمام ✓'],
            ['notifications.index.title_1', 'notifications', 'index: الإشعارات', 'الإشعارات'],
            ['notifications.index.breadcrumbs_1', 'notifications', 'index: الرئيسيّة', 'الرئيسيّة'],
            ['notifications.index.breadcrumbs_2', 'notifications', 'index: الإشعارات', 'الإشعارات'],
            ['notifications.index.text_1', 'notifications', 'index: تعليم الكلّ كمقروء', 'تعليم الكلّ كمقروء'],
            ['notifications.index.action_1', 'notifications', 'index: الرجوع للرئيسيّة', 'الرجوع للرئيسيّة'],
            ['notifications.index.text_2', 'notifications', 'index: عرض المزيد', 'عرض المزيد'],
            ['notifications.index.text_3', 'notifications', 'index: وسّع المدى (أقدم من :a1 يومًا)', 'وسّع المدى (أقدم من :a1 يومًا)'],
            ['notifications.index.title_2', 'notifications', 'index: تفاصيل الإشعار', 'تفاصيل الإشعار'],
            ['notifications.index.text_4', 'notifications', 'index: تعليم الكلّ كمقروء (:a1)', 'تعليم الكلّ كمقروء (:a1)'],
            ['notifications.index.js_1', 'notifications', 'index: افتح', 'افتح'],
            // ---- resources/views/notifications/partials/row.blade.php
            ['notifications.row.aria_label_1', 'notifications', 'row: غير مقروء', 'غير مقروء'],
            ['notifications.row.text_1', 'notifications', 'row: التفاصيل', 'التفاصيل'],
            ['notifications.row.text_2', 'notifications', 'row: تعليم كمقروء', 'تعليم كمقروء'],
            // ---- resources/views/onboarding/accepted.blade.php
            ['onboarding.accepted.text_1', 'onboarding', 'accepted: أهلًا بيك.', 'أهلًا بيك.'],
            // ---- resources/views/onboarding/instructions.blade.php
            ['onboarding.instructions.aria_label_1', 'onboarding', 'instructions: نسبة القراءة', 'نسبة القراءة'],
            // ---- resources/views/onboarding/placement.blade.php
            ['onboarding.placement_view.text_1', 'onboarding', 'placement: سؤال', 'سؤال'],
            ['onboarding.placement_view.text_2', 'onboarding', 'placement: تذكرة', 'تذكرة'],
            // ---- resources/views/placeholder.blade.php
            ['ux.placeholder.section_1', 'ux', 'placeholder: قيد الإنشاء', 'قيد الإنشاء'],
            ['ux.placeholder.title_1', 'ux', 'placeholder: قيد الإنشاء', 'قيد الإنشاء'],
            ['ux.placeholder.subtitle_1', 'ux', 'placeholder: الصفحة دي بتتبني دلوقتي.', 'الصفحة دي بتتبني دلوقتي.'],
            ['ux.placeholder.message_1', 'ux', 'placeholder: لسّه بنجهّز الصفحة دي.', 'لسّه بنجهّز الصفحة دي.'],
            // ---- resources/views/profile/partials/header.blade.php
            ['profile.header.text_1', 'accounts', 'header: مستوى الحساب', 'مستوى الحساب'],
            ['profile.header.text_2', 'accounts', 'header: نادي التميّز', 'نادي التميّز'],
            ['profile.header.text_3', 'accounts', 'header: مشاركة الحساب', 'مشاركة الحساب'],
            ['profile.header.text_4', 'accounts', 'header: البيانات الحسّاسة مخفيّة افتراضيًّا.', 'البيانات الحسّاسة مخفيّة افتراضيًّا.'],
            // ---- resources/views/profile/show.blade.php
            ['profile.show.section_1', 'accounts', 'show: :a1 — بروفايل', ':a1 — بروفايل'],
            ['profile.show.section_2', 'accounts', 'show: بروفايل :a1 على :a2', 'بروفايل :a1 على :a2'],
            ['profile.show.js_1', 'accounts', 'show: انسخ رابطك', 'انسخ رابطك'],
            ['profile.show.js_2', 'accounts', 'show: اتنسخ ✓', 'اتنسخ ✓'],
            ['profile.show.js_3', 'accounts', 'show: اتحفظ ✓', 'اتحفظ ✓'],
            ['profile.show.js_4', 'accounts', 'show: مقدرناش نحفظ — جرّب تاني.', 'مقدرناش نحفظ — جرّب تاني.'],
            ['profile.show.js_5', 'accounts', 'show: النبذة اتحدّثت', 'النبذة اتحدّثت'],
            ['profile.show.js_6', 'accounts', 'show: 🔒 مقفولة — الشرط فوق', '🔒 مقفولة — الشرط فوق'],
            ['profile.show.js_7', 'accounts', 'show: ★ مفتوحة', '★ مفتوحة'],
            // ---- resources/views/public/volunteering.blade.php
            ['volunteer_page.view.text_1', 'volunteer_page', 'volunteering: تقدّمك في المسار التأهيليّ', 'تقدّمك في المسار التأهيليّ'],
            ['volunteer_page.view.text_2', 'volunteer_page', 'volunteering: وصلك طلب تسكين', 'وصلك طلب تسكين'],
            ['volunteer_page.view.text_3', 'volunteer_page', 'volunteering: الردّ خلال :a1 ساعة.', 'الردّ خلال :a1 ساعة.'],
            ['volunteer_page.view.text_4', 'volunteer_page', 'volunteering: أوافق', 'أوافق'],
            ['volunteer_page.view.text_5', 'volunteer_page', 'volunteering: مش دلوقتي', 'مش دلوقتي'],
            ['volunteer_page.view.text_6', 'volunteer_page', 'volunteering: التجديد الجاي متاح يوم :a1.', 'التجديد الجاي متاح يوم :a1.'],
            ['volunteer_page.view.action_1', 'volunteer_page', 'volunteering: صفحة الدعم', 'صفحة الدعم'],
            ['volunteer_page.view.js_1', 'volunteer_page', 'volunteering: الموعد حالًا', 'الموعد حالًا'],
            ['volunteer_page.view.js_2', 'volunteer_page', 'volunteering:  يوم و', ' يوم و'],
            ['volunteer_page.view.js_3', 'volunteer_page', 'volunteering:  ساعة و', ' ساعة و'],
            ['volunteer_page.view.js_4', 'volunteer_page', 'volunteering:  دقيقة', ' دقيقة'],
            // ---- resources/views/referral/index.blade.php
            ['referral.index.section_1', 'growth', 'index: ادعُ أصدقاءك', 'ادعُ أصدقاءك'],
            ['referral.index.section_2', 'growth', 'index: ادعُ أصدقاءك واكسب عمولة على شحناتهم — ولصاحبك تذكرة ترحيب.', 'ادعُ أصدقاءك واكسب عمولة على شحناتهم — ولصاحبك تذكرة ترحيب.'],
            ['referral.index.title_1', 'growth', 'index: ادعُ أصدقاءك', 'ادعُ أصدقاءك'],
            ['referral.index.subtitle_1', 'growth', 'index: كلّ صاحب تجيبه ليه تذكرة ترحيب، وليك عمولة على شحناته.', 'كلّ صاحب تجيبه ليه تذكرة ترحيب، وليك عمولة على شحناته.'],
            ['referral.index.breadcrumbs_1', 'growth', 'index: الرئيسيّة', 'الرئيسيّة'],
            ['referral.index.breadcrumbs_2', 'growth', 'index: ادعُ أصدقاءك', 'ادعُ أصدقاءك'],
            ['referral.index.include_1', 'growth', 'index: نسخ رابط الدعوة', 'نسخ رابط الدعوة'],
            ['referral.index.text_1', 'growth', 'index: سطر الصفحة المدعوّ إليها (:page)', 'اتدعيت لـ«:page» — نكمّل من هناك؟'],
            ['referral.index.expr_1', 'growth', 'index: صفحة معيّنة', 'صفحة معيّنة'],
            ['referral.index.text_3', 'growth', 'index: افتح الصفحة', 'افتح الصفحة'],
            ['referral.index.text_4', 'growth', 'index: رابط دعوتك الخاصّ', 'رابط دعوتك الخاصّ'],
            ['referral.index.include_2', 'growth', 'index: نسخ', 'نسخ'],
            ['referral.index.text_5', 'growth', 'index: واتساب', 'واتساب'],
            ['referral.index.label_1', 'growth', 'index: المدعوّون', 'المدعوّون'],
            ['referral.index.label_2', 'growth', 'index: أتمّوا التفعيل', 'أتمّوا التفعيل'],
            ['referral.index.label_3', 'growth', 'index: في الانتظار', 'في الانتظار'],
            ['referral.index.label_4', 'growth', 'index: العمولة المكتسبة', 'العمولة المكتسبة'],
            ['referral.index.hint_1', 'growth', 'index: نسبتك :a1% مدى الحياة', 'نسبتك :a1% مدى الحياة'],
            ['referral.index.text_6', 'growth', 'index: ادعُ صديقك لمحتوى بعينه', 'ادعُ صديقك لمحتوى بعينه'],
            ['referral.index.text_7', 'growth', 'index: الرابط ده بيفتح الصفحة نفسها لصاحبك بعد ما يسجّل — ولصاحبك :', 'الرابط ده بيفتح الصفحة نفسها لصاحبك بعد ما يسجّل — ولصاحبك :a1 تذكرة ترحيب.'],
            ['referral.index.include_3', 'growth', 'index: نسخ الرابط', 'نسخ الرابط'],
            ['referral.index.message_1', 'growth', 'index: ابدأ بدعوة أوّل صديق — الرابط جاهز فوق.', 'ابدأ بدعوة أوّل صديق — الرابط جاهز فوق.'],
            ['referral.index.action_1', 'growth', 'index: افتح الفعاليّات', 'افتح الفعاليّات'],
            ['referral.index.text_8', 'growth', 'index: مَن انضمّ', 'مَن انضمّ'],
            ['referral.index.text_9', 'growth', 'index: التاريخ', 'التاريخ'],
            ['referral.index.text_10', 'growth', 'index: الحالة', 'الحالة'],
            ['referral.index.text_11', 'growth', 'index: العمولة', 'العمولة'],
            ['referral.index.expr_2', 'growth', 'index: حساب غير مكتمل', 'حساب غير مكتمل'],
            ['referral.index.expr_3', 'growth', 'index: حساب غير مكتمل', 'حساب غير مكتمل'],
            ['referral.index.include_4', 'growth', 'index: نسخ رابط الدعوة', 'نسخ رابط الدعوة'],
            // ---- resources/views/reward-questions/show.blade.php
            ['reward_questions.show.text_1', 'gamification_reward_questions', 'show: المكافأة: :a1 XP · :a2 تذكرة', 'المكافأة: :a1 XP · :a2 تذكرة'],
            ['reward_questions.show.text_2', 'gamification_reward_questions', 'show: إجابتك:', 'إجابتك:'],
            ['reward_questions.show.text_3', 'gamification_reward_questions', 'show: تذكرة', 'تذكرة'],
            ['reward_questions.show.text_4', 'gamification_reward_questions', 'show: إجابتك', 'إجابتك'],
            // ---- resources/views/security/verify-email.blade.php
            ['security.verify_email.section_1', 'security', 'verify-email: تأكيد بريدك', 'تأكيد بريدك'],
            // ---- resources/views/setup/database.blade.php
            ['setup.database_view.section_1', 'setup', 'database: تنصيب المنصّة — قاعدة البيانات', 'تنصيب المنصّة — قاعدة البيانات'],
            ['setup.database_view.title_1', 'setup', 'database: قاعدة البيانات', 'قاعدة البيانات'],
            ['setup.database_view.subtitle_1', 'setup', 'database: انسخ البيانات من لوحة الاستضافة، وجرّب الاتّصال الأوّل — وبع', 'انسخ البيانات من لوحة الاستضافة، وجرّب الاتّصال الأوّل — وبعدين نحفظ.'],
            ['setup.database_view.label_1', 'setup', 'database: المضيف', 'المضيف'],
            ['setup.database_view.hint_1', 'setup', 'database: غالبًا 127.0.0.1 على نفس الخادم.', 'غالبًا 127.0.0.1 على نفس الخادم.'],
            ['setup.database_view.label_2', 'setup', 'database: المنفذ', 'المنفذ'],
            ['setup.database_view.hint_2', 'setup', 'database: الافتراضيّ 3306.', 'الافتراضيّ 3306.'],
            ['setup.database_view.label_3', 'setup', 'database: اسم قاعدة البيانات', 'اسم قاعدة البيانات'],
            ['setup.database_view.label_4', 'setup', 'database: مستخدم قاعدة البيانات', 'مستخدم قاعدة البيانات'],
            ['setup.database_view.label_5', 'setup', 'database: كلمة سرّ قاعدة البيانات', 'كلمة سرّ قاعدة البيانات'],
            ['setup.database_view.hint_3', 'setup', 'database: سيبها فاضية لو المستخدم بلا كلمة سرّ.', 'سيبها فاضية لو المستخدم بلا كلمة سرّ.'],
            ['setup.database_view.text_1', 'setup', 'database: اختبار الاتّصال', 'اختبار الاتّصال'],
            ['setup.database_view.text_2', 'setup', 'database: احفظ وكمّل', 'احفظ وكمّل'],
            ['setup.database_view.label_6', 'setup', 'database: الاتّصال متجرَّب', 'الاتّصال متجرَّب'],
            ['setup.database_view.label_7', 'setup', 'database: لسّه ماتجرّبش', 'لسّه ماتجرّبش'],
            // ---- resources/views/setup/done.blade.php
            ['setup.done_view.section_1', 'setup', 'done: تمّ التنصيب بنجاح', 'تمّ التنصيب بنجاح'],
            ['setup.done_view.label_1', 'setup', 'done: التنصيب تمّ', 'التنصيب تمّ'],
            ['setup.done_view.text_1', 'setup', 'done: جاهزة', 'جاهزة'],
            ['setup.done_view.text_2', 'setup', 'done: كلّ حاجة اتظبطت، وصفحة التنصيب اتقفلت. ادخل بحسابك وابدأ.', 'كلّ حاجة اتظبطت، وصفحة التنصيب اتقفلت. ادخل بحسابك وابدأ.'],
            ['setup.done_view.text_3', 'setup', 'done: بريد الدخول', 'بريد الدخول'],
            ['setup.done_view.text_4', 'setup', 'done: كودك في المنصّة', 'كودك في المنصّة'],
            ['setup.done_view.text_5', 'setup', 'done: ادخل للمنصّة', 'ادخل للمنصّة'],
            // ---- resources/views/setup/finish.blade.php
            ['setup.finish_view.section_1', 'setup', 'finish: تنصيب المنصّة — الإنهاء', 'تنصيب المنصّة — الإنهاء'],
            ['setup.finish_view.title_1', 'setup', 'finish: فاضل ضغطة واحدة', 'فاضل ضغطة واحدة'],
            ['setup.finish_view.subtitle_1', 'setup', 'finish: هنولّد مفتاح أمان جديد ونقفل صفحة التنصيب نهائيًّا.', 'هنولّد مفتاح أمان جديد ونقفل صفحة التنصيب نهائيًّا.'],
            ['setup.finish_view.text_1', 'setup', 'finish: بعد الضغط هتتقفل كلّ صفحات التنصيب، ومحدّش هيقدر يفتحها تاني', 'بعد الضغط هتتقفل كلّ صفحات التنصيب، ومحدّش هيقدر يفتحها تاني — لا أنت ولا غيرك.'],
            ['setup.finish_view.text_2', 'setup', 'finish: أنهِ التنصيب', 'أنهِ التنصيب'],
            ['setup.finish_view.js_1', 'setup', 'finish: بنقفل التنصيب…', 'بنقفل التنصيب…'],
            // ---- resources/views/setup/migrate.blade.php
            ['setup.migrate_view.section_1', 'setup', 'migrate: تنصيب المنصّة — تجهيز الجداول', 'تنصيب المنصّة — تجهيز الجداول'],
            ['setup.migrate_view.title_1', 'setup', 'migrate: تجهيز الجداول والبيانات', 'تجهيز الجداول والبيانات'],
            ['setup.migrate_view.subtitle_1', 'setup', 'migrate: هنبني جداول المنصّة ونحمّل الأدوار والصلاحيّات والإعدادات — ', 'هنبني جداول المنصّة ونحمّل الأدوار والصلاحيّات والإعدادات — كلّه من هنا بلا تيرمينال.'],
            ['setup.migrate_view.label_1', 'setup', 'migrate: تمّ', 'تمّ'],
            ['setup.migrate_view.label_2', 'setup', 'migrate: وقف', 'وقف'],
            ['setup.migrate_view.message_1', 'setup', 'migrate: لسّه ماشغّلناش التجهيز — اضغط الزرّ وهنمشي خطوة خطوة قدّامك.', 'لسّه ماشغّلناش التجهيز — اضغط الزرّ وهنمشي خطوة خطوة قدّامك.'],
            ['setup.migrate_view.text_1', 'setup', 'migrate: كمّل لبيانات المنصّة', 'كمّل لبيانات المنصّة'],
            ['setup.migrate_view.text_2', 'setup', 'migrate: بنجهّز الجداول… ممكن ياخد لحدّ دقيقة. سيب الصفحة مفتوحة.', 'بنجهّز الجداول… ممكن ياخد لحدّ دقيقة. سيب الصفحة مفتوحة.'],
            ['setup.migrate_view.text_3', 'setup', 'migrate: ابدأ التجهيز', 'ابدأ التجهيز'],
            ['setup.migrate_view.js_1', 'setup', 'migrate: بنجهّز…', 'بنجهّز…'],
            // ---- resources/views/setup/owner.blade.php
            ['setup.owner_view.section_1', 'setup', 'owner: تنصيب المنصّة — حساب مالك المنصّة', 'تنصيب المنصّة — حساب مالك المنصّة'],
            ['setup.owner_view.title_1', 'setup', 'owner: حساب مالك المنصّة', 'حساب مالك المنصّة'],
            ['setup.owner_view.subtitle_1', 'setup', 'owner: ده حسابك أنت: بيتفعّل على طول وبياخد أعلى صلاحيّة في المنصّة', 'ده حسابك أنت: بيتفعّل على طول وبياخد أعلى صلاحيّة في المنصّة.'],
            ['setup.owner_view.label_1', 'setup', 'owner: الاسم', 'الاسم'],
            ['setup.owner_view.hint_1', 'setup', 'owner: الاسم اللي هيظهر لك ولزمايلك جوّه المنصّة.', 'الاسم اللي هيظهر لك ولزمايلك جوّه المنصّة.'],
            ['setup.owner_view.label_2', 'setup', 'owner: البريد', 'البريد'],
            ['setup.owner_view.hint_2', 'setup', 'owner: هتدخل بيه، وعليه هتوصلك تنبيهات المنصّة.', 'هتدخل بيه، وعليه هتوصلك تنبيهات المنصّة.'],
            ['setup.owner_view.label_3', 'setup', 'owner: رقم الموبايل', 'رقم الموبايل'],
            ['setup.owner_view.hint_3', 'setup', 'owner: بمفتاح الدولة، مثال: ‎+201000000000‎.', 'بمفتاح الدولة، مثال: ‎+201000000000‎.'],
            ['setup.owner_view.label_4', 'setup', 'owner: كلمة السرّ', 'كلمة السرّ'],
            ['setup.owner_view.hint_4', 'setup', 'owner: حروف على الأقلّ.', 'حروف على الأقلّ.'],
            ['setup.owner_view.label_5', 'setup', 'owner: تأكيد كلمة السرّ', 'تأكيد كلمة السرّ'],
            ['setup.owner_view.text_1', 'setup', 'owner: أنشئ الحساب وكمّل', 'أنشئ الحساب وكمّل'],
            // ---- resources/views/setup/partials/stepper.blade.php
            ['setup.stepper_view.aria_label_1', 'setup', 'stepper: خطوات التنصيب', 'خطوات التنصيب'],
            // ---- resources/views/setup/platform.blade.php
            ['setup.platform_view.section_1', 'setup', 'platform: تنصيب المنصّة — بيانات المنصّة', 'تنصيب المنصّة — بيانات المنصّة'],
            ['setup.platform_view.title_1', 'setup', 'platform: بيانات المنصّة', 'بيانات المنصّة'],
            ['setup.platform_view.subtitle_1', 'setup', 'platform: الاسم والشعار والرابط والوقت واللغة — وكلّها تتعدّل بعدين من', 'الاسم والشعار والرابط والوقت واللغة — وكلّها تتعدّل بعدين من لوحة الإدارة.'],
            ['setup.platform_view.label_1', 'setup', 'platform: اسم المنصّة', 'اسم المنصّة'],
            ['setup.platform_view.hint_1', 'setup', 'platform: الاسم اللي هيظهر في الهيدر وعنوان المتصفّح.', 'الاسم اللي هيظهر في الهيدر وعنوان المتصفّح.'],
            ['setup.platform_view.label_2', 'setup', 'platform: رابط المنصّة', 'رابط المنصّة'],
            ['setup.platform_view.hint_2', 'setup', 'platform: انسخه من شريط المتصفّح بلا / في آخره.', 'انسخه من شريط المتصفّح بلا / في آخره.'],
            ['setup.platform_view.text_1', 'setup', 'platform: الشعار (اختياريّ)', 'الشعار (اختياريّ)'],
            ['setup.platform_view.text_2', 'setup', 'platform: أو JPG أو WEBP أو SVG — وتقدر ترفعه بعدين من اللوحة.', 'أو JPG أو WEBP أو SVG — وتقدر ترفعه بعدين من اللوحة.'],
            ['setup.platform_view.text_3', 'setup', 'platform: المنطقة الزمنيّة', 'المنطقة الزمنيّة'],
            ['setup.platform_view.text_4', 'setup', 'platform: كلّ المواعيد والتقارير هتتحسب بيها.', 'كلّ المواعيد والتقارير هتتحسب بيها.'],
            ['setup.platform_view.text_5', 'setup', 'platform: لغة الواجهة', 'لغة الواجهة'],
            ['setup.platform_view.text_6', 'setup', 'platform: اللغة الافتراضيّة لكلّ مستخدم جديد.', 'اللغة الافتراضيّة لكلّ مستخدم جديد.'],
            ['setup.platform_view.text_7', 'setup', 'platform: احفظ وكمّل لحساب المالك', 'احفظ وكمّل لحساب المالك'],
            // ---- resources/views/setup/requirements.blade.php
            ['setup.requirements_view.section_1', 'setup', 'requirements: تنصيب المنصّة — فحص المتطلّبات', 'تنصيب المنصّة — فحص المتطلّبات'],
            ['setup.requirements_view.php_1', 'setup', 'requirements: إصدار PHP', 'إصدار PHP'],
            ['setup.requirements_view.php_2', 'setup', 'requirements: الامتدادات', 'الامتدادات'],
            ['setup.requirements_view.php_3', 'setup', 'requirements: صلاحيّات الكتابة', 'صلاحيّات الكتابة'],
            ['setup.requirements_view.title_1', 'setup', 'requirements: فحص المتطلّبات', 'فحص المتطلّبات'],
            ['setup.requirements_view.subtitle_1', 'setup', 'requirements: بنتأكّد إنّ الخادم جاهز، عشان التنصيب مايقفش في النصّ.', 'بنتأكّد إنّ الخادم جاهز، عشان التنصيب مايقفش في النصّ.'],
            ['setup.requirements_view.label_1', 'setup', 'requirements: تمام', 'تمام'],
            ['setup.requirements_view.label_2', 'setup', 'requirements: لازم', 'لازم'],
            ['setup.requirements_view.label_3', 'setup', 'requirements: اختياريّ', 'اختياريّ'],
            ['setup.requirements_view.text_1', 'setup', 'requirements: كمّل لقاعدة البيانات', 'كمّل لقاعدة البيانات'],
            ['setup.requirements_view.text_2', 'setup', 'requirements: أعد الفحص', 'أعد الفحص'],
            ['setup.requirements_view.text_3', 'setup', 'requirements: صحّح اللي عليه ❌ من لوحة الاستضافة، وبعدين اضغط «أعد الفحص» ', 'صحّح اللي عليه ❌ من لوحة الاستضافة، وبعدين اضغط «أعد الفحص» — وهنكمّل على طول.'],
            // ---- resources/views/setup/token.blade.php
            ['setup.token_view.section_1', 'setup', 'token: تنصيب المنصّة — توكن التنصيب', 'تنصيب المنصّة — توكن التنصيب'],
            ['setup.token_view.title_1', 'setup', 'token: أهلًا بيك — نبدأ التنصيب', 'أهلًا بيك — نبدأ التنصيب'],
            ['setup.token_view.subtitle_1', 'setup', 'token: خطوة أمان أولى: أثبت إنّ الخادم ده بتاعك قبل ما نفتح المعالج', 'خطوة أمان أولى: أثبت إنّ الخادم ده بتاعك قبل ما نفتح المعالج.'],
            ['setup.token_view.text_1', 'setup', 'token: مقدرناش ننشئ ملفّ التوكن', 'مقدرناش ننشئ ملفّ التوكن'],
            ['setup.token_view.text_2', 'setup', 'token: من مدير الملفّات في الاستضافة اضبط صلاحيّة مجلّد', 'من مدير الملفّات في الاستضافة اضبط صلاحيّة مجلّد'],
            ['setup.token_view.text_3', 'setup', 'token: على 775، وبعدين حدّث الصفحة.', 'على 775، وبعدين حدّث الصفحة.'],
            ['setup.token_view.text_4', 'setup', 'token: وضع التطوير مفتوح، فالتوكن قدّامك:', 'وضع التطوير مفتوح، فالتوكن قدّامك:'],
            ['setup.token_view.text_5', 'setup', 'token: افتح الملفّ', 'افتح الملفّ'],
            ['setup.token_view.text_6', 'setup', 'token: من مدير الملفّات في الاستضافة،', 'من مدير الملفّات في الاستضافة،'],
            ['setup.token_view.text_7', 'setup', 'token: وانسخ السطر اللي جوّاه هنا.', 'وانسخ السطر اللي جوّاه هنا.'],
            ['setup.token_view.label_1', 'setup', 'token: توكن التنصيب', 'توكن التنصيب'],
            ['setup.token_view.hint_1', 'setup', 'token: التوكن اتولّد لوحده عند أوّل فتح للصفحة.', 'التوكن اتولّد لوحده عند أوّل فتح للصفحة.'],
            ['setup.token_view.text_8', 'setup', 'token: ادخل المعالج', 'ادخل المعالج'],
            // ---- resources/views/support/complaints/index.blade.php
            ['complaints.index.placeholder_1', 'complaints', 'index: أو كلمة من العنوان', 'أو كلمة من العنوان'],
            ['complaints.index.text_1', 'complaints', 'index: مرفق', 'مرفق'],
            // ---- resources/views/support/help/index.blade.php
            ['help.index.section_1', 'help', 'index: دليل المستخدم', 'دليل المستخدم'],
            ['help.index.title_1', 'help', 'index: دليل المستخدم', 'دليل المستخدم'],
            ['help.index.subtitle_1', 'help', 'index: إجابة سريعة من غير ما تفتح تذكرة.', 'إجابة سريعة من غير ما تفتح تذكرة.'],
            ['help.index.breadcrumbs_1', 'help', 'index: الدعم', 'الدعم'],
            ['help.index.breadcrumbs_2', 'help', 'index: دليل المستخدم', 'دليل المستخدم'],
            ['help.index.text_1', 'help', 'index: تدوّر على إيه؟', 'تدوّر على إيه؟'],
            ['help.index.placeholder_1', 'help', 'index: اكتب كلمة… مثال: الشهادة، الشحن، الستريك', 'اكتب كلمة… مثال: الشهادة، الشحن، الستريك'],
            ['help.index.text_2', 'help', 'index: إبحث', 'إبحث'],
            ['help.index.text_3', 'help', 'index: كلّ التصنيفات', 'كلّ التصنيفات'],
            ['help.index.message_1', 'help', 'index: مفيش نتائج — جرّب كلمة تانية.', 'مفيش نتائج — جرّب كلمة تانية.'],
            ['help.index.action_1', 'help', 'index: افتح تذكرة', 'افتح تذكرة'],
            ['help.index.href_1', 'help', 'index: استفسار عن: :a1', 'استفسار عن: :a1'],
            ['help.index.text_4', 'help', 'index: لسّه ملقيتش إجابتك؟', 'لسّه ملقيتش إجابتك؟'],
            ['help.index.href_expr_1', 'help', 'index: استفسار عن: :a1', 'استفسار عن: :a1'],
            ['help.index.text_5', 'help', 'index: افتح تذكرة', 'افتح تذكرة'],
            // ---- resources/views/support/help/show.blade.php
            ['help.show.breadcrumbs_1', 'help', 'show: الدعم', 'الدعم'],
            ['help.show.breadcrumbs_2', 'help', 'show: دليل المستخدم', 'دليل المستخدم'],
            ['help.show.aria_label_1', 'help', 'show: تقييم المقال', 'تقييم المقال'],
            ['help.show.text_1', 'help', 'show: هل كان مفيدًا؟', 'هل كان مفيدًا؟'],
            ['help.show.text_2', 'help', 'show: أيوه', 'أيوه'],
            ['help.show.text_3', 'help', 'show: لأ', 'لأ'],
            ['help.show.href_expr_1', 'help', 'show: استفسار حول: :a1', 'استفسار حول: :a1'],
            ['help.show.text_4', 'help', 'show: لم أجد إجابتي', 'لم أجد إجابتي'],
            ['help.show.text_5', 'help', 'show: مقالات قريبة', 'مقالات قريبة'],
            ['help.show.href_expr_2', 'help', 'show: استفسار حول: :a1', 'استفسار حول: :a1'],
            ['help.show.text_6', 'help', 'show: لم أجد إجابتي', 'لم أجد إجابتي'],
        ];

        foreach ($rows as [$key, $group, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        Cache::forget('settings');
    }
}
