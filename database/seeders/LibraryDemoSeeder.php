<?php

namespace Database\Seeders;

use App\Models\Attestation;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Currency;
use App\Models\CvTemplate;
use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ReadingProgress;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * بيانات مجال «مكتبتي والقارئ وخبراتي» (20 · 9 · 9.1 · 24.5)
 * + إعداداته الكاملة تطبيقًا للقاعدة الذهبيّة 2.13 (ولا رقمٌ ولا نصٌّ محروق).
 */
class LibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        // الإعدادات تُقرأ من كاش دائم — فنُبطله فورًا كي يرى ما بعده القيمَ الجديدة
        Cache::forget('settings');

        $this->cvTemplates();

        $products = $this->products();
        $user = User::query()->where('status', 'active')->orderBy('id')->first();

        if ($user) {
            $this->library($user, $products);
            $this->attestations($user);
        }

        Cache::forget('settings');
    }

    // ------------------------------------------------------------------ الإعدادات

    public function settings(): void
    {
        $rows = [
            // ---------------- مكتبتي (20)
            ['library.page.title', 'library', 'عنوان صفحة مكتبتي', 'string', 'مكتبتي'],
            ['library.page.subtitle', 'library', 'سطر تعريف المكتبة', 'string', 'عندك :count عنصر بوصولٍ دائم.'],
            ['library.breadcrumb.home', 'library', 'اسم الرئيسيّة في المسار', 'string', 'الرئيسيّة'],
            ['library.shelf.default_sort', 'library', 'الفرز الافتراضيّ', 'string', 'recent'],
            ['library.sort.recent_label', 'library', 'فرز: الأحدث', 'string', 'الأحدث'],
            ['library.sort.name_label', 'library', 'فرز: الاسم', 'string', 'الاسم'],
            ['library.sort.used_label', 'library', 'فرز: الأكثر استخدامًا', 'string', 'الأكثر استخدامًا'],
            ['library.tab.all_label', 'library', 'تاب: الكلّ', 'string', 'الكلّ'],
            ['library.tab.courses_label', 'library', 'تاب: تدريبات', 'string', 'تدريبات'],
            ['library.tab.paths_label', 'library', 'تاب: مسارات', 'string', 'مسارات'],
            ['library.tab.bundles_label', 'library', 'تاب: بندلز', 'string', 'بندلز'],
            ['library.tab.products_label', 'library', 'تاب: منتجات', 'string', 'منتجات'],
            ['library.tab.certificates_label', 'library', 'تاب: شهادات', 'string', 'شهادات'],
            ['library.type.course_label', 'library', 'نوع: تدريب', 'string', 'تدريب'],
            ['library.type.path_label', 'library', 'نوع: مسار', 'string', 'مسار'],
            ['library.type.bundle_label', 'library', 'نوع: بندل', 'string', 'بندل'],
            ['library.type.pdf_label', 'library', 'نوع: PDF', 'string', 'PDF'],
            ['library.type.video_label', 'library', 'نوع: فيديو', 'string', 'فيديو'],
            ['library.type.audio_label', 'library', 'نوع: صوت', 'string', 'صوت'],
            ['library.type.html_label', 'library', 'نوع: HTML', 'string', 'HTML'],
            ['library.type.certificate_label', 'library', 'نوع: شهادة', 'string', 'شهادة'],
            ['library.action.open_label', 'library', 'زرّ: فتح', 'string', 'فتح'],
            ['library.action.play_label', 'library', 'زرّ: تشغيل', 'string', 'تشغيل'],
            ['library.action.read_label', 'library', 'زرّ: قراءة', 'string', 'قراءة'],
            ['library.action.download_label', 'library', 'زرّ: تحميل', 'string', 'تحميل'],
            ['library.action.store_label', 'library', 'زرّ: المتجر', 'string', 'المتجر'],
            ['library.filter.search_label', 'library', 'فلتر: بحث', 'string', 'بحث'],
            ['library.filter.search_placeholder', 'library', 'تلميح البحث', 'string', 'اكتب اسم العنصر…'],
            ['library.filter.type_label', 'library', 'فلتر: النوع', 'string', 'النوع'],
            ['library.filter.type_any', 'library', 'فلتر: كلّ الأنواع', 'string', 'كلّ الأنواع'],
            ['library.filter.sort_label', 'library', 'فلتر: الفرز', 'string', 'فرز'],
            ['library.filter.currency_label', 'library', 'فلتر متقدّم: العملة', 'string', 'العملة المُشترى بها'],
            ['library.filter.apply_label', 'library', 'زرّ تطبيق الفلاتر', 'string', 'طبّق'],
            ['library.empty.message', 'library', 'الحالة الفارغة', 'string', 'مكتبتك لسّه فاضية'],
            ['library.empty.action', 'library', 'زرّ الحالة الفارغة', 'string', 'اكتشف المتجر'],
            ['library.empty.filtered_message', 'library', 'لا نتائج للفلتر', 'string', 'مفيش نتائج للفلتر ده'],
            ['library.empty.reset_label', 'library', 'زرّ مسح الفلاتر', 'string', 'امسح الفلاتر'],
            ['library.availability.now_label', 'library', 'حالة: متاح الآن', 'string', 'متاح الآن'],
            ['library.availability.starts_label', 'library', 'حالة: يبدأ يوم', 'string', 'يبدأ يوم :date'],
            ['library.availability.ended_label', 'library', 'حالة: انتهت الإتاحة', 'string', 'انتهت فترة الإتاحة'],
            ['library.availability.not_owned_label', 'library', 'حالة: غير متاح', 'string', 'غير متاح'],
            ['library.availability.date_format', 'library', 'صيغة تاريخ الإتاحة', 'string', 'j F'],
            ['library.certificate.valid_label', 'library', 'شهادة سارية', 'string', 'سارية'],
            ['library.certificate.invalid_label', 'library', 'شهادة غير سارية', 'string', 'غير سارية'],
            ['library.certificate.fallback_title', 'library', 'اسم الشهادة الافتراضيّ', 'string', 'شهادة'],
            ['library.card.more_label', 'library', 'زرّ تفاصيل الكارت', 'string', 'تفاصيل العنصر'],
            ['library.modal.title', 'library', 'عنوان بوب-أب العنصر', 'string', 'تفاصيل العنصر'],
            ['library.modal.invoice_title', 'library', 'عنوان الفاتورة', 'string', 'الفاتورة'],
            ['library.modal.invoice_number', 'library', 'رقم الطلب', 'string', 'رقم الطلب'],
            ['library.modal.invoice_date', 'library', 'تاريخ الطلب', 'string', 'التاريخ'],
            ['library.modal.invoice_total', 'library', 'مبلغ الطلب', 'string', 'المبلغ'],
            ['library.modal.no_invoice', 'library', 'بلا فاتورة', 'string', 'العنصر ده مش مربوط بطلب شراء.'],
            ['library.modal.error', 'library', 'رسالة خطأ البوب-أب', 'string', 'مش قادرين نجيب التفاصيل دلوقتي — جرّب تاني.'],
            ['library.modal.copied', 'library', 'تمّ نسخ الرابط', 'string', 'الرابط اتنسخ ✓'],
            ['library.modal.recommend_label', 'library', 'زرّ أوصِ بهذا', 'string', 'أوصِ بهذا'],
            ['library.invoice.date_format', 'library', 'صيغة تاريخ الفاتورة', 'string', 'j F Y'],
            ['library.recommend.commission_percent', 'library', 'نسبة عمولة الترشيح (%)', 'number', '7'],
            // مشاركة العنصر كصورة (20.4) — بعلامة مائيّة + رابط ريفيرال
            ['library.share_image.subtitle', 'library', 'العنوان الفرعيّ لصورة المشاركة', 'string', 'من مكتبتي على المنصّة'],
            ['library.share_image.referral_row_label', 'library', 'تسمية سطر رابط الدعوة في صورة المشاركة', 'string', 'رابط دعوتي'],
            ['library.recommend.utm_source', 'library', 'UTM source للترشيح', 'string', 'library'],
            ['library.recommend.utm_medium', 'library', 'UTM medium للترشيح', 'string', 'recommend'],
            ['library.recommend.utm_campaign', 'library', 'UTM campaign للترشيح', 'string', 'organic'],
            ['library.recommend.done_message', 'library', 'رسالة بعد توليد الرابط', 'string', 'الرابط جاهز — كلّ عمليّة شراء منه ليك فيها عمولة.'],
            ['library.forbidden_message', 'library', 'رسالة عنصر ليس في المكتبة', 'string', 'العنصر ده مش في مكتبتك.'],

            // ---------------- القارئ المحميّ (20.3)
            ['reader.page.width_px', 'reader', 'عرض صفحة القارئ (بكسل)', 'number', '1000'],
            ['reader.thumb.width_px', 'reader', 'عرض المصغّرة (بكسل)', 'number', '160'],
            ['reader.render.dpi', 'reader', 'دقّة تحويل الـPDF', 'number', '150'],
            ['reader.page.aspect_ratio', 'reader', 'نسبة ارتفاع الصفحة', 'string', '1.414'],
            ['reader.page_count.fallback', 'reader', 'عدد الصفحات عند تعذّر القراءة', 'number', '1'],
            ['reader.cache.directory', 'reader', 'مجلّد كاش الصفحات', 'string', 'library/reader-cache'],
            ['reader.watermark.enabled', 'reader', 'تفعيل العلامة المائيّة', 'bool', '1'],
            ['reader.watermark.template', 'reader', 'صيغة العلامة المائيّة', 'string', ':name · #:code'],
            ['reader.watermark.repeat_count', 'reader', 'عدد تكرارات العلامة', 'number', '12'],
            ['reader.watermark.opacity_percent', 'reader', 'شفافيّة العلامة (%)', 'number', '12'],
            ['reader.watermark.font_size_px', 'reader', 'حجم خطّ العلامة', 'number', '18'],
            ['reader.zoom.min_percent', 'reader', 'أدنى تكبير (%)', 'number', '60'],
            ['reader.zoom.max_percent', 'reader', 'أقصى تكبير (%)', 'number', '240'],
            ['reader.zoom.step_percent', 'reader', 'خطوة التكبير (%)', 'number', '20'],
            ['reader.zoom.in_label', 'reader', 'زرّ تكبير', 'string', 'تكبير'],
            ['reader.zoom.out_label', 'reader', 'زرّ تصغير', 'string', 'تصغير'],
            ['reader.toc.open_by_default', 'reader', 'فتح المصغّرات افتراضيًّا', 'bool', '0'],
            ['reader.toc.search_placeholder', 'reader', 'تلميح البحث في الفهرس', 'string', 'رقم الصفحة…'],
            ['reader.menu.toc_label', 'reader', 'قائمة: الفهرس', 'string', 'الفهرس والمصغّرات'],
            ['reader.menu.fullscreen_label', 'reader', 'قائمة: ملء الشاشة', 'string', 'ملء الشاشة'],
            ['reader.menu.mode_label', 'reader', 'قائمة: وضع القراءة', 'string', 'وضع القراءة داكن/فاتح'],
            ['reader.nav.next_label', 'reader', 'زرّ الصفحة التالية', 'string', 'التالية'],
            ['reader.nav.prev_label', 'reader', 'زرّ الصفحة السابقة', 'string', 'السابقة'],
            ['reader.continue_label', 'reader', 'زرّ تابع القراءة', 'string', 'تابع القراءة'],
            ['reader.page.alt', 'reader', 'وصف صورة الصفحة', 'string', 'صفحة من الملفّ'],
            ['reader.protection_note', 'reader', 'سطر الحماية أسفل القارئ', 'text', 'الملفّ ده بيتقرا جوّه الموقع بس — بلا تحميل وبلا رابط مباشر، ونسختك عليها اسمك وكودك.'],
            ['reader.fallback.message', 'reader', 'رسالة تعذّر محرّك العرض', 'text', 'محرّك عرض الـPDF مش مركّب على السيرفر دلوقتي — بنعرض لك صفحاتٍ بديلة مؤقّتًا لحدّ ما يتفعّل، وملفّك محفوظ زيّ ما هو.'],
            ['reader.forbidden_message', 'reader', 'رسالة عدم الملكيّة', 'string', 'الملفّ ده مش في مكتبتك — افتحه من المتجر الأوّل.'],
            ['reader.unavailable_message', 'reader', 'رسالة خارج فترة الإتاحة', 'string', 'العنصر ده لسّه خارج فترة إتاحته.'],
            ['reader.teaser.badge_label', 'reader', 'شارة صفحات العيّنة', 'string', 'صفحات عيّنة'],
            ['reader.teaser.watermark_text', 'reader', 'علامة العيّنة', 'string', 'عيّنة'],
            ['reader.teaser.end_message', 'reader', 'رسالة نهاية العيّنة', 'string', 'خلصت صفحات العيّنة — كمّل القراءة بعد الشراء.'],
            ['reader.teaser.buy_label', 'reader', 'زرّ الشراء بعد العيّنة', 'string', 'شراء'],
            ['reader.teaser.blocked_message', 'reader', 'رسالة تجاوز العيّنة', 'string', 'دي آخر صفحة في العيّنة — اشترِ المنتج لتكمل.'],
            ['reader.teaser.default_pages', 'reader', 'عدد صفحات العيّنة الافتراضيّ', 'number', '3'],

            // ---------------- الفهرس (TOC) في القارئ (20.3)
            ['reader.toc.title', 'reader', 'عنوان الفهرس', 'string', 'فهرس الملفّ'],
            ['reader.toc.empty_text', 'reader', 'نصّ الفهرس الفارغ', 'string', 'الملفّ ده من غير فهرس — اتنقّل بالمصغّرات.'],
            ['reader.toc.max_entries', 'reader', 'أقصى عدد مدخلات الفهرس', 'number', '200'],
            ['reader.thumbs.title', 'reader', 'عنوان المصغّرات', 'string', 'المصغّرات'],

            // ---------------- تحليلات المكتبة المجمّعة (20.5) — بلا سجلّ فتح فرديّ
            ['library.analytics.title', 'library', 'عنوان تحليلات المكتبة', 'string', 'تحليلات المكتبة (مجمّعة)'],
            ['library.analytics.note', 'library', 'ملاحظة التحليلات', 'text', 'أرقام مجمّعة توجّه الإنتاج — بدون أيّ سجلّ فتح فرديّ لأيّ ملفّ.'],
            ['library.analytics.readers_label', 'library', 'وسم عدد القرّاء', 'string', 'قرّاء'],
            ['library.analytics.completion_label', 'library', 'وسم نسبة الإكمال', 'string', 'متوسّط الإكمال'],
            ['library.analytics.top_label', 'library', 'وسم الأكثر قراءةً', 'string', 'الأكثر قراءةً'],
            ['library.analytics.empty_text', 'library', 'نصّ غياب بيانات القراءة', 'string', 'لسّه مفيش قراءات مسجّلة — الأرقام هتظهر أوّل ما الناس تقرأ.'],
            ['library.analytics.top_limit', 'library', 'عدد عناصر الأكثر قراءةً', 'number', '5'],
            ['library.analytics.min_readers', 'library', 'أدنى عدد قرّاء لعرض المنتج', 'number', '1'],

            // ---------------- السيرة الذاتيّة (9)
            ['cv.page.title', 'cv', 'عنوان صفحة السيرة', 'string', 'سيرتي الذاتيّة'],
            ['cv.page.subtitle', 'cv', 'سطر تعريف السيرة', 'string', 'املأ الخطوات، والمعاينة بتتحدّث معاك لحظة بلحظة.'],
            ['cv.breadcrumb.experiences', 'cv', 'اسم «خبراتي» في المسار', 'string', 'خبراتي'],
            ['cv.download_label', 'cv', 'زرّ تحميل PDF', 'string', 'تحميل PDF'],
            ['cv.autosave.debounce_ms', 'cv', 'مهلة الحفظ التلقائيّ (ملّي ثانية)', 'number', '900'],
            ['cv.autosave.saved_label', 'cv', 'علامة الحفظ', 'string', 'اتحفظ ✓'],
            ['cv.autosave.error_label', 'cv', 'رسالة فشل الحفظ', 'string', 'ما اتحفظش — راجع النت وجرّب تاني.'],
            ['cv.completion.label', 'cv', 'عنوان مؤشّر الاكتمال', 'string', 'اكتمال السيرة'],
            ['cv.completion.weights', 'cv', 'أوزان الاكتمال', 'json', '{"profile":30,"experience":25,"education":20,"skills":15,"languages":10}'],
            ['cv.completion.missing_prefix', 'cv', 'بادئة الناقص', 'string', 'ناقصك:'],
            ['cv.completion.done', 'cv', 'رسالة الاكتمال', 'string', 'سيرتك مكتملة — جاهزة للتحميل.'],
            ['cv.step.profile_label', 'cv', 'خطوة: البيانات', 'string', 'البيانات'],
            ['cv.step.experience_label', 'cv', 'خطوة: الخبرات', 'string', 'الخبرات'],
            ['cv.step.education_label', 'cv', 'خطوة: التعليم', 'string', 'التعليم'],
            ['cv.step.skills_label', 'cv', 'خطوة: المهارات واللغات', 'string', 'المهارات واللغات'],
            ['cv.step.certificates_label', 'cv', 'خطوة: الشهادات', 'string', 'الشهادات'],
            ['cv.step.next_label', 'cv', 'زرّ التالي', 'string', 'التالي'],
            ['cv.step.prev_label', 'cv', 'زرّ السابق', 'string', 'السابق'],
            ['cv.tab.edit_label', 'cv', 'تاب التعديل (موبايل)', 'string', 'التعديل'],
            ['cv.tab.preview_label', 'cv', 'تاب المعاينة (موبايل)', 'string', 'المعاينة'],
            ['cv.preview.title', 'cv', 'عنوان المعاينة', 'string', 'المعاينة الحيّة'],
            ['cv.preview.size_note', 'cv', 'ملاحظة مقاس المعاينة', 'string', 'بالمقاس الحقيقيّ A4'],
            ['cv.field.max_chars', 'cv', 'أقصى طول حقل', 'number', '1000'],
            ['cv.skills.max_chars', 'cv', 'أقصى طول المهارات', 'number', '600'],
            ['cv.section.max_rows', 'cv', 'أقصى بنود القسم المتكرّر', 'number', '20'],
            ['cv.field.job_title_label', 'cv', 'حقل: المسمّى الوظيفيّ', 'string', 'المسمّى الوظيفيّ الحاليّ'],
            ['cv.field.company_label', 'cv', 'حقل: الشركة', 'string', 'الشركة / الجهة'],
            ['cv.field.years_label', 'cv', 'حقل: سنوات الخبرة', 'string', 'سنوات الخبرة'],
            ['cv.field.stage_label', 'cv', 'حقل: المرحلة المهنيّة', 'string', 'المرحلة المهنيّة'],
            ['cv.field.major_label', 'cv', 'حقل: التخصّص', 'string', 'التخصّص الأكاديميّ'],
            ['cv.field.native_language_label', 'cv', 'حقل: اللغة الأمّ', 'string', 'اللغة الأمّ'],
            ['cv.field.summary_label', 'cv', 'حقل: الملخّص المهنيّ', 'string', 'ملخّص مهنيّ (اختياريّ)'],
            ['cv.field.summary_hint', 'cv', 'تلميح الملخّص', 'string', 'سطران عن مسيرتك وطموحك.'],
            ['cv.field.email_label', 'cv', 'حقل: البريد', 'string', 'البريد'],
            ['cv.field.phone_label', 'cv', 'حقل: الهاتف', 'string', 'الهاتف'],
            ['cv.field.city_label', 'cv', 'حقل: المدينة', 'string', 'المدينة'],
            ['cv.field.from_label', 'cv', 'حقل: من', 'string', 'من'],
            ['cv.field.to_label', 'cv', 'حقل: إلى', 'string', 'إلى'],
            ['cv.field.description_label', 'cv', 'حقل: الوصف', 'string', 'الوصف'],
            ['cv.field.degree_label', 'cv', 'حقل: الدرجة العلميّة', 'string', 'الدرجة العلميّة'],
            ['cv.field.institution_label', 'cv', 'حقل: المؤسّسة التعليميّة', 'string', 'المؤسّسة التعليميّة'],
            ['cv.field.gpa_label', 'cv', 'حقل: المعدّل', 'string', 'المعدّل (اختياريّ)'],
            ['cv.field.skills_label', 'cv', 'حقل: المهارات', 'string', 'أضف مهاراتك (افصل بفاصلة)'],
            ['cv.field.skills_placeholder', 'cv', 'تلميح المهارات', 'string', 'Excel, تحليل بيانات, مبيعات B2B'],
            ['cv.field.languages_label', 'cv', 'حقل: اللغات', 'string', 'اللغات'],
            ['cv.field.language_label', 'cv', 'حقل: اللغة', 'string', 'اللغة'],
            ['cv.until_now_label', 'cv', 'حتى الآن', 'string', 'حتى الآن'],
            ['cv.row.remove_label', 'cv', 'زرّ حذف البند', 'string', 'حذف'],
            ['cv.experience.add_label', 'cv', 'زرّ إضافة خبرة', 'string', 'إضافة خبرة عمل'],
            ['cv.experience.empty_hint', 'cv', 'تلميح الخبرات الفارغة', 'string', 'ابدأ بآخر وظيفة اشتغلتها.'],
            ['cv.education.add_label', 'cv', 'زرّ إضافة تعليم', 'string', 'إضافة تعليم جديد'],
            ['cv.education.empty_hint', 'cv', 'تلميح التعليم الفارغ', 'string', 'أضف أعلى مؤهّل وصلت له.'],
            ['cv.languages.add_label', 'cv', 'زرّ إضافة لغة', 'string', 'إضافة لغة أخرى'],
            ['cv.options.years', 'cv', 'خيارات سنوات الخبرة', 'json', '["أقلّ من سنة","1–3 سنوات","3–5 سنوات","5–10 سنوات","أكثر من 10 سنوات"]'],
            ['cv.options.stages', 'cv', 'خيارات المرحلة المهنيّة', 'json', '["طالب","مبتدئ","متوسّط","خبير","قياديّ"]'],
            ['cv.options.native_languages', 'cv', 'خيارات اللغة الأمّ', 'json', '["العربيّة","الإنجليزيّة","الفرنسيّة"]'],
            ['cv.options.language_levels', 'cv', 'مستويات اللغة', 'json', '["مبتدئ","متوسّط","جيّد جدًّا","إتقان تامّ","لغة أمّ"]'],
            ['cv.certificates.intro', 'cv', 'شرح خطوة الشهادات', 'string', 'شهاداتك بتتسحب تلقائيًّا من المنصّة — شيل اللي مش عايزه يظهر.'],
            ['cv.certificates.hide_label', 'cv', 'إخفاء شهادة', 'string', 'إخفاء'],
            ['cv.certificates.empty', 'cv', 'لا شهادات بعد', 'string', 'لسّه مافيش شهادات — أوّل تدريب هيجيبلك واحدة.'],
            ['cv.pull.profile_label', 'cv', 'سحب بيانات البروفايل', 'string', 'اسحب بيانات بروفايلي (الاسم · الدولة · التواصل)'],
            ['cv.pull.certificates_label', 'cv', 'سحب الشهادات', 'string', 'اسحب شهاداتي من المنصّة'],
            ['cv.section.summary_label', 'cv', 'قسم: نبذة مهنيّة', 'string', 'نبذة مهنيّة'],
            ['cv.section.experience_label', 'cv', 'قسم: الخبرة العمليّة', 'string', 'الخبرة العمليّة'],
            ['cv.section.education_label', 'cv', 'قسم: رحلة التعلّم', 'string', 'رحلة التعلّم'],
            ['cv.section.skills_label', 'cv', 'قسم: المهارات', 'string', 'المهارات'],
            ['cv.section.languages_label', 'cv', 'قسم: اللغات', 'string', 'اللغات'],
            ['cv.section.certificates_label', 'cv', 'قسم: الشهادات', 'string', 'الشهادات'],
            ['cv.section.contact_label', 'cv', 'قسم: التواصل', 'string', 'التواصل'],
            ['cv.sheet.untitled', 'cv', 'اسم بديل في الورقة', 'string', 'سيرتي الذاتيّة'],
            ['cv.templates.title', 'cv', 'عنوان معرض القوالب', 'string', 'القالب'],
            ['cv.templates.balance_label', 'cv', 'رصيد التذاكر', 'string', 'رصيدك: :n تذكرة'],
            ['cv.template.default_price_tickets', 'cv', 'سعر القالب الافتراضيّ (تذاكر)', 'number', '2'],
            ['cv.template.free_badge', 'cv', 'شارة القالب المجّانيّ', 'string', 'مجّانيّ'],
            ['cv.template.owned_badge', 'cv', 'شارة القالب المملوك', 'string', 'مملوك'],
            ['cv.template.price_label', 'cv', 'سعر القالب', 'string', ':n تذكرة'],
            // ⛔ حُذفت هنا (ولمقابلها هجرة حذفٍ من settings): buy_title · price_title ·
            // balance_before · balance_after · confirm_label — استُبدلت بـ
            // selected_message/selected_paid_message في `CvController::selectTemplate`
            // ولم يقرأها أحد قطّ (`settings:coverage --dead`).
            ['cv.template.selected_message', 'cv', 'رسالة تغيير القالب', 'string', 'اتغيّر القالب — شوف المعاينة.'],
            // زرّ «تحميل أيّ قالب» في شاشة إدارة قوالب الـCV (12.7-ب · cv_templates.export)
            ['cv.template.admin.download_label', 'cv', 'زرّ تحميل ملفّ القالب', 'string', 'تحميل'],
            // ⭐ المحرّر المرئيّ (Drag-drop) — الطبقة الزخرفيّة (المرحلة 1/2 · 12.7-ب)
            ['cv.template.admin.decor_saved_message', 'cv', 'رسالة حفظ الطبقة الزخرفيّة', 'string', 'اتحفظت الطبقة الزخرفيّة ✓'],
            ['cv.template.decor.max_layers', 'cv', 'أقصى عدد طبقات زخرفيّة لكلّ قالب', 'number', '20'],
            // ⭐ المحرّر المرئيّ (Drag-drop) — شاشة التحرير نفسها (المرحلة 2/2 · 12.7-ب)
            ['cv.template.admin.decor_editor_label', 'cv', 'زرّ فتح المحرّر المرئيّ', 'string', 'المحرّر المرئيّ (Drag-drop)'],
            ['cv.template.admin.decor_page_title', 'cv', 'بادئة عنوان شاشة المحرّر المرئيّ', 'string', 'المحرّر المرئيّ: '],
            ['cv.template.admin.decor_page_subtitle', 'cv', 'عنوان فرعيّ لشاشة المحرّر المرئيّ', 'text', 'اسحب عناصر الديكور فوق معاينة حقيقيّة لمحتوى القالب — نصٌّ أو صورة، بلا ربط ببيانات السيرة.'],
            ['cv.template.admin.decor_layers_panel_label', 'cv', 'عنوان لوحة الطبقات', 'string', 'الطبقات'],
            ['cv.template.admin.decor_add_text_label', 'cv', 'زرّ إضافة طبقة نصّ', 'string', '+ نصّ'],
            ['cv.template.admin.decor_add_image_label', 'cv', 'زرّ إضافة طبقة صورة', 'string', '+ صورة'],
            ['cv.template.admin.decor_snap_label', 'cv', 'خانة شبكة المحاذاة', 'string', 'شبكة محاذاة (Snap)'],
            ['cv.template.admin.decor_canvas_label', 'cv', 'عنوان الكانفس', 'string', 'الكانفس'],
            ['cv.template.admin.decor_hint', 'cv', 'تلميح الكانفس', 'text', 'اسحب أيّ عنصر لتغيير موضعه — المحتوى خلفه معاينة حقيقيّة لقالب السيرة.'],
            ['cv.template.admin.decor_save_label', 'cv', 'زرّ حفظ الطبقة الزخرفيّة', 'string', 'حفظ الطبقة الزخرفيّة'],
            ['cv.template.admin.decor_empty_hint', 'cv', 'تلميح لا عناصر بعد', 'string', 'لا عناصر زخرفيّة بعد — أضف نصًّا أو صورة.'],
            ['cv.template.admin.decor_preview_title', 'cv', 'عنوان إطار المعاينة الحيّة', 'string', 'معاينة المحتوى الحقيقيّ'],
            ['cv.template.admin.decor_properties_label', 'cv', 'عنوان لوحة خصائص العنصر', 'string', 'خصائص العنصر'],
            ['cv.template.decor.grid_step', 'cv', 'خطوة شبكة المحاذاة بالمحرّر المرئيّ (نسبة مئويّة)', 'number', '5'],
            ['cv.template.decor.snap_enabled', 'cv', 'شبكة المحاذاة مفعَّلة افتراضيًّا بالمحرّر المرئيّ', 'bool', '1'],
            // نصوص جافاسكربت الكانفس (2.13-أ: لا حرف عربيّ داخل <script>)
            ['cv.template.admin.decor_js_text_layer', 'cv', 'اسم طبقة نصّ في القائمة', 'string', 'نصّ'],
            ['cv.template.admin.decor_js_image_layer', 'cv', 'اسم طبقة صورة في القائمة', 'string', 'صورة'],
            ['cv.template.admin.decor_js_behind_badge', 'cv', 'شارة «خلف المحتوى» في القائمة', 'string', 'خلف المحتوى'],
            ['cv.template.admin.decor_js_toggle_visibility', 'cv', 'تلميح زرّ إظهار/إخفاء (محليّ للتحرير فقط)', 'string', 'إظهار/إخفاء (في هذه الشاشة فقط)'],
            ['cv.template.admin.decor_js_move_up', 'cv', 'تلميح زرّ نقل لأعلى', 'string', 'لأعلى'],
            ['cv.template.admin.decor_js_move_down', 'cv', 'تلميح زرّ نقل لأسفل', 'string', 'لأسفل'],
            ['cv.template.admin.decor_js_delete_layer', 'cv', 'تلميح زرّ حذف الطبقة', 'string', 'حذف'],
            ['cv.template.admin.decor_js_text_label', 'cv', 'تسمية حقل النصّ', 'string', 'النصّ'],
            ['cv.template.admin.decor_js_size_label', 'cv', 'تسمية حقل حجم الخطّ', 'string', 'حجم الخطّ'],
            ['cv.template.admin.decor_js_color_label', 'cv', 'تسمية حقل اللون', 'string', 'اللون'],
            ['cv.template.admin.decor_js_align_label', 'cv', 'تسمية حقل المحاذاة', 'string', 'المحاذاة'],
            ['cv.template.admin.decor_js_align_right', 'cv', 'خيار محاذاة يمين', 'string', 'يمين'],
            ['cv.template.admin.decor_js_align_center', 'cv', 'خيار محاذاة وسط', 'string', 'وسط'],
            ['cv.template.admin.decor_js_align_left', 'cv', 'خيار محاذاة يسار', 'string', 'يسار'],
            ['cv.template.admin.decor_js_rotate_label', 'cv', 'تسمية حقل الدوران', 'string', 'الدوران'],
            ['cv.template.admin.decor_js_behind_label', 'cv', 'تسمية مفتاح خلف المحتوى', 'string', 'ضعها خلف المحتوى'],
            ['cv.template.admin.decor_js_image_label', 'cv', 'تسمية حقل اختيار الصورة', 'string', 'الصورة'],
            ['cv.template.admin.decor_js_width_label', 'cv', 'تسمية حقل عرض الصورة', 'string', 'العرض %'],
            ['cv.template.admin.decor_js_height_label', 'cv', 'تسمية حقل طول الصورة', 'string', 'الطول %'],
            ['cv.template.admin.decor_js_opacity_label', 'cv', 'تسمية حقل الشفافيّة', 'string', 'الشفافيّة %'],
            ['cv.template.admin.decor_js_pick_image', 'cv', 'زرّ اختيار صورة من المكتبة', 'string', 'اختر صورة'],
            ['cv.template.admin.decor_js_no_image', 'cv', 'حالة بلا صورة مختارة', 'string', 'بلا صورة'],
            ['cv.template.admin.decor_js_empty_text_placeholder', 'cv', 'نصّ بديل لطبقة نصّ فارغة في الكانفس', 'string', '(نصّ فارغ)'],
            ['cv.template.purchased_message', 'cv', 'رسالة نجاح الشراء', 'string', 'القالب بقى ملكك — استمتع.'],
            ['cv.template.insufficient_message', 'cv', 'رسالة نقص الرصيد', 'string', 'رصيد التذاكر لا يكفي — اكسب تذاكر أو اختر قالبًا آخر.'],
            ['cv.template.currency_missing_message', 'cv', 'رسالة محفظة غير مهيّأة', 'string', 'محفظة التذاكر غير مهيّأة — جرّب بعد قليل.'],
            ['cv.template.transaction_reason', 'cv', 'سبب معاملة شراء القالب', 'string', 'شراء قالب سيرة ذاتيّة: :name'],
            ['cv.template.error_label', 'cv', 'رسالة خطأ القوالب', 'string', 'مش قادرين ننفّذ دلوقتي — جرّب تاني.'],
            ['cv.template.default_name', 'cv', 'اسم القالب الافتراضيّ', 'string', 'كلاسيك'],
            ['cv.missing.profile_label', 'cv', 'ناقص: البيانات', 'string', 'بياناتك المهنيّة'],
            ['cv.missing.experience_label', 'cv', 'ناقص: الخبرات', 'string', 'خبرة عمل واحدة على الأقلّ'],
            ['cv.missing.education_label', 'cv', 'ناقص: التعليم', 'string', 'مؤهّلك التعليميّ'],
            ['cv.missing.skills_label', 'cv', 'ناقص: المهارات', 'string', 'مهاراتك'],
            ['cv.missing.languages_label', 'cv', 'ناقص: اللغات', 'string', 'لغة واحدة على الأقلّ'],
            ['cv.guest.note', 'cv', 'ملاحظة القالب المجّانيّ بلا تسجيل', 'text', 'إنت بتجرّب القالب المجّانيّ بلا تسجيل — التحميل بيطلب إنشاء حساب، وشغلك محفوظ لحدّ ما تسجّل.'],
            ['cv.guest.download_label', 'cv', 'زرّ التحميل للزائر', 'string', 'أنشئ حساب وحمّل PDF'],
            ['cv.guest.register_prompt', 'cv', 'رسالة بوّابة التحميل للزائر', 'text', 'سيرتك جاهزة ومحفوظة ✓ — أنشئ حسابك دلوقتي وحمّلها PDF.'],

            // ---------------- الإفادة (9.1)
            ['attestations.page.title', 'attestations', 'عنوان صفحة الإفادة', 'string', 'الإفادة'],
            ['attestations.page.subtitle', 'attestations', 'سطر تعريف الإفادة', 'string', 'إثباتٌ موثّق من المنصّة — مجّانًا بلا تذاكر.'],
            ['attestations.request.action_label', 'attestations', 'زرّ اطلب إفادة', 'string', 'اطلب إفادة'],
            ['attestations.request.submit_label', 'attestations', 'زرّ إرسال الطلب', 'string', 'إرسال الطلب'],
            ['attestations.request.max_open', 'attestations', 'أقصى طلبات مفتوحة', 'number', '3'],
            ['attestations.request.limit_message', 'attestations', 'رسالة تجاوز السقف', 'string', 'عندك طلبات مفتوحة كتير — استنّى ردّها الأوّل.'],
            ['attestations.request.sent_message', 'attestations', 'رسالة نجاح الطلب', 'string', 'طلبك وصل — هنبلّغك أوّل ما يتردّ عليه.'],
            ['attestations.field.from_label', 'attestations', 'حقل: الجهة أو الشخص', 'string', 'الجهة أو الشخص'],
            ['attestations.field.reason_label', 'attestations', 'حقل: سبب الطلب', 'string', 'سبب الطلب'],
            ['attestations.field.note_label', 'attestations', 'حقل: ملاحظة', 'string', 'ملاحظة (اختياريّ)'],
            ['attestations.status.requested_label', 'attestations', 'حالة: قيد الانتظار', 'string', 'قيد الانتظار'],
            ['attestations.status.approved_label', 'attestations', 'حالة: صدرت', 'string', 'صدرت'],
            ['attestations.status.rejected_label', 'attestations', 'حالة: مرفوضة', 'string', 'مرفوضة'],
            ['attestations.status.other_label', 'attestations', 'حالة: مؤرشفة', 'string', 'مؤرشفة'],
            ['attestations.list.title', 'attestations', 'عنوان قائمة الإفادات', 'string', 'إفاداتي'],
            ['attestations.list.rejection_reason_prefix', 'attestations', 'لافتة سبب الرفض', 'string', 'سبب الرفض:'],
            ['attestations.empty.message', 'attestations', 'الحالة الفارغة', 'string', 'مفيش إفادات لسّه'],
            ['attestations.record.title', 'attestations', 'عنوان الإفادة المولَّدة', 'string', 'إفادتك من المنصّة'],
            ['attestations.record.subtitle', 'attestations', 'شرح الإفادة المولَّدة', 'string', 'بتتولّد لوحدها من تدريباتك وشهاداتك وشاراتك ونقاطك.'],
            ['attestations.kpi.courses', 'attestations', 'مؤشّر: تدريبات مكتملة', 'string', 'تدريبات مكتملة'],
            ['attestations.kpi.certificates', 'attestations', 'مؤشّر: شهادات سارية', 'string', 'شهادات سارية'],
            ['attestations.kpi.badges', 'attestations', 'مؤشّر: شارات', 'string', 'شارات'],
            ['attestations.kpi.xp', 'attestations', 'مؤشّر: نقاط الخبرة', 'string', 'نقاط الخبرة'],
            ['attestations.export_label', 'attestations', 'زرّ الاستخراج', 'string', 'استخراج'],
            ['attestations.share_label', 'attestations', 'زرّ نسخ الرابط العامّ', 'string', 'نسخ الرابط العامّ'],
            ['attestations.copied_message', 'attestations', 'تمّ نسخ الرابط', 'string', 'الرابط اتنسخ ✓'],
            ['attestations.placements.title', 'attestations', 'عنوان مكان الظهور', 'string', 'بتظهر فين؟'],
            ['attestations.placements', 'attestations', 'أماكن ظهور الإفادة', 'json', '["في تاب «خبراتي» داخل بروفايلك العامّ","في السيرة الذاتيّة تحت قسم الشهادات","في الرابط العامّ الذي تشاركه مع جهة العمل"]'],
            ['attestations.sheet.title', 'attestations', 'عنوان ورقة الإفادة', 'string', 'إفادة من المنصّة'],
            ['attestations.sheet.courses_title', 'attestations', 'قسم التدريبات في الورقة', 'string', 'التدريبات المكتملة'],
            ['attestations.sheet.certificates_title', 'attestations', 'قسم الشهادات في الورقة', 'string', 'الشهادات وروابط التحقّق'],
            ['attestations.sheet.badges_title', 'attestations', 'قسم الشارات في الورقة', 'string', 'الشارات'],
            ['attestations.sheet.recommendations_title', 'attestations', 'قسم الإفادات الموثّقة', 'string', 'إفادات موثّقة'],
            ['attestations.sheet.footer', 'attestations', 'تذييل ورقة الإفادة', 'text', 'كلّ ما في هذه الإفادة مولَّد من سجلّ المنصّة، ويمكن التحقّق منه بالأكواد أعلاه.'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    // ------------------------------------------------------------------ القوالب

    /** خمسة قوالب — واحدٌ مجّانيّ باب دخول (21.2-ج) والباقي بالتذاكر */
    private function cvTemplates(): void
    {
        // NULL = اتبع السعر العامّ من «أوجه الصرف» (`cv.export`)، والرقم استثناء صريح (2.13)
        $price = (float) setting('cv.template.default_price_tickets', 2);

        $rows = [
            ['كلاسيك', 'classic', true, 0, 1],
            ['مودرن', 'modern', false, null, 2],
            ['تنفيذيّ', 'modern', false, null, 3],
            ['أكاديميّ', 'classic', false, null, 4],
            ['مبدع', 'modern', false, $price + 1, 5],
        ];

        foreach ($rows as [$name, $view, $isFree, $tickets, $order]) {
            CvTemplate::updateOrCreate(['name' => $name], [
                'view_path' => $view,
                'is_free' => $isFree,
                'price_tickets' => $tickets,
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
    }

    // ------------------------------------------------------------------ المنتجات

    /** @return array<string, Product> */
    private function products(): array
    {
        $category = ProductCategory::firstOrCreate(
            ['slug' => 'digital-books'],
            ['name_ar' => 'كتب رقميّة', 'sort_order' => 1, 'is_active' => true],
        );

        $protectedPath = 'library/demo/dalil-al-mutadarrib.pdf';

        if (! Storage::disk('local')->exists($protectedPath)) {
            Storage::disk('local')->put($protectedPath, $this->blankPdf(8));
        }

        $protected = Product::updateOrCreate(['slug' => 'dalil-al-mutadarrib'], [
            'product_category_id' => $category->id,
            'name_ar' => 'دليل المتدرّب — الطبعة الثانية',
            'description' => 'دليلٌ عمليّ يمشي معك من أوّل درس إلى الشهادة.',
            'type' => 'protected_pdf',
            'file_path' => $protectedPath,
            'is_downloadable' => false,
            'teaser_pages' => (int) setting('reader.teaser.default_pages', 3),
            'price_coins' => 120,
            'status' => 'published',
        ]);

        $audio = Product::updateOrCreate(['slug' => 'jalsat-tarkiz'], [
            'product_category_id' => $category->id,
            'name_ar' => 'جلسات تركيز صوتيّة',
            'description' => 'خمس جلسات قصيرة تساعدك تبدأ وتكمّل.',
            'type' => 'digital',
            'file_path' => 'library/demo/jalsat-tarkiz.mp3',
            'is_downloadable' => true,
            'price_coins' => 60,
            'status' => 'published',
        ]);

        $video = Product::updateOrCreate(['slug' => 'warsha-alsira'], [
            'product_category_id' => $category->id,
            'name_ar' => 'ورشة كتابة السيرة الذاتيّة',
            'description' => 'ورشة مسجّلة خطوة بخطوة مع نماذج جاهزة.',
            'type' => 'digital',
            'file_path' => 'library/demo/warsha-alsira.mp4',
            'is_downloadable' => true,
            'price_coins' => 90,
            'status' => 'published',
        ]);

        return ['protected' => $protected, 'audio' => $audio, 'video' => $video];
    }

    // ------------------------------------------------------------------ المكتبة

    /** @param  array<string, Product>  $products */
    private function library(User $user, array $products): void
    {
        $currency = Currency::where('code', 'coins')->first();

        $order = null;

        if ($currency) {
            $order = Order::updateOrCreate(['number' => 'ORD-LIB-0001'], [
                'user_id' => $user->id,
                'subtotal' => 120,
                'discount' => 0,
                'total' => 120,
                'currency_id' => $currency->id,
                'status' => 'paid',
                'refund_policy_acknowledged' => true,
                'paid_at' => now()->subDays(9),
            ]);

            OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'purchasable_type' => $products['protected']->getMorphClass(), 'purchasable_id' => $products['protected']->id],
                ['title' => $products['protected']->name_ar, 'price' => 120, 'quantity' => 1],
            );

            // محفظة تذاكر ليجرّب شراء قالب CV
            WalletBalance::updateOrCreate(
                ['user_id' => $user->id, 'currency_id' => Currency::where('code', 'tickets')->value('id') ?? $currency->id],
                ['balance' => 6, 'lifetime_earned' => 6],
            );
        }

        $entitlements = [
            [$products['protected'], $order?->id, null],
            [$products['audio'], null, null],
            // عنصرٌ لسّه خارج فترة إتاحته — يظهر بحالته ولا يُخفى (20.1)
            [$products['video'], null, now()->addDays(4)],
        ];

        foreach ($entitlements as [$item, $orderId, $from]) {
            LibraryEntitlement::updateOrCreate(
                ['user_id' => $user->id, 'itemable_type' => $item->getMorphClass(), 'itemable_id' => $item->id],
                ['order_id' => $orderId, 'source' => 'purchase', 'available_from' => $from],
            );
        }

        foreach ([Course::query()->first(), Bundle::query()->first()] as $extra) {
            if ($extra) {
                LibraryEntitlement::updateOrCreate(
                    ['user_id' => $user->id, 'itemable_type' => $extra->getMorphClass(), 'itemable_id' => $extra->id],
                    ['source' => 'purchase'],
                );
            }
        }

        ReadingProgress::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $products['protected']->id],
            ['last_page' => 3],
        );
    }

    private function attestations(User $user): void
    {
        $rows = [
            ['أ. منى عبد الرحمن — مديرة التدريب', 'التزم بالمواعيد وسلّم كلّ المهامّ في وقتها، وكان عونًا لزملائه في الفريق.', 'approved', true],
            ['شركة نماء للاستشارات', 'طلب إفادة للتقديم على وظيفة محلّل بيانات.', 'requested', false],
        ];

        foreach ($rows as [$from, $body, $status, $isPublic]) {
            Attestation::updateOrCreate(
                ['user_id' => $user->id, 'from_name' => $from],
                ['body' => $body, 'status' => $status, 'is_public' => $isPublic],
            );
        }
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * ملفّ PDF فارغ بعدد صفحات محدّد — يُبنى بأيدينا بلا أيّ مكتبة خارجيّة،
     * لأنّ المطلوب هنا ملفٌّ تجريبيّ صحيح البنية لا محتوًى حقيقيّ.
     */
    private function blankPdf(int $pages): string
    {
        $objects = [];
        $kids = [];

        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (3 + $i).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';

        for ($i = 0; $i < $pages; $i++) {
            $objects[3 + $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> >>';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 ".$count."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }
}
