<?php

namespace Database\Seeders;

use App\Models\AdAudience;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\ArticleWorkflow;
use App\Services\Ads\AdEvents;
use App\Services\Ads\AudienceResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات وإعدادات مجال النموّ (21.1 · 21.2 · 21.3).
 *
 * القاعدة الذهبيّة 2.13: **لا رقم ولا نصّ محروق** — كلّ ما تقرأه شاشات هذا المجال
 * له صفٌّ هنا بقيمة افتراضيّة قابلة للاسترجاع، وله شاشة إدارة في `admin/growth`.
 */
class GrowthDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->articles();
        $this->audiences();

        Cache::forget('settings');
        $this->command?->info('بيانات مجال النموّ جاهزة.');
    }

    // ---------------------------------------------------------------- الإعدادات

    public function settings(): void
    {
        // [key, group, label, type, default, owner_only]
        $rows = [
            // ---------------- 21.1-ب — بار «أكمل ملفك» ومكافأته 3 تذاكر
            ['growth.profile_completion.enabled', 'growth', 'تفعيل حلقة «أكمل ملفك»', 'bool', '1', false],
            ['growth.profile_completion.reward_currency', 'growth', 'عملة مكافأة إكمال الملفّ', 'string', 'tickets', false],
            ['growth.profile_completion.reward_reason', 'growth', 'وصف حركة المكافأة', 'string', 'مكافأة إكمال الملفّ الشخصيّ', false],
            ['growth.profile_completion.fields', 'growth', 'حقول «أكمل ملفك»', 'json', json_encode([
                'avatar_path' => 'صورة الملفّ',
                'phone' => 'رقم الموبايل',
                'country_id' => 'الدولة',
                'governorate_id' => 'المحافظة',
                'birthdate' => 'تاريخ الميلاد',
                'gender' => 'النوع',
            ], JSON_UNESCAPED_UNICODE), false],
            ['growth.profile_completion.title', 'growth', 'عنوان شاشة إكمال الملفّ', 'string', 'أكمل ملفّك', false],
            ['growth.profile_completion.subtitle', 'growth', 'سطر شاشة إكمال الملفّ', 'text', 'بياناتك الكاملة بتخلّي شهادتك وبطاقتك يطلعوا صحّ.', false],
            ['growth.profile_completion.bar_routes', 'growth', 'شاشات ظهور البار', 'json',
                json_encode(['dashboard', 'profile.me', 'settings.index', 'learning.courses'], JSON_UNESCAPED_UNICODE), false],
            ['growth.profile_completion.bar_title', 'growth', 'عنوان البار', 'string', 'كمّل ملفّك', false],
            ['growth.profile_completion.bar_hint', 'growth', 'سطر البار', 'string', 'كمّله لآخره وخُد {tickets} تذاكر.', false],
            ['growth.profile_completion.bar_cta', 'growth', 'زرّ البار', 'string', 'كمّل دلوقتي', false],
            ['growth.profile_completion.granted', 'growth', 'رسالة صرف المكافأة', 'string', 'تمام! ملفّك اكتمل و{tickets} تذاكر اتضافت لمحفظتك ✓', false],

            // ---------------- 21.1-أ — معاينة أوّل درس مجّانًا
            ['growth.preview.enabled', 'growth', 'تفعيل المعاينة المجّانيّة', 'bool', '1', false],
            ['growth.preview.max_lessons', 'growth', 'سقف دروس المعاينة', 'number', '3', false],
            ['growth.preview.title', 'growth', 'عنوان صفحة المعاينة', 'string', 'معاينة مجّانيّة', false],
            ['growth.preview.subtitle', 'growth', 'سطر صفحة المعاينة', 'text', 'جرّب قبل ما تسجّل — الدروس المفتوحة تحت متاحة بلا حساب.', false],
            ['growth.preview.note', 'growth', 'سطر عدد الدروس المفتوحة', 'text', 'مفتوح لك {count} درس مجّانًا كمعاينة. الباقي بيتفتح بعد ما تسجّل.', false],
            ['growth.preview.locked_label', 'growth', 'تسمية الدرس المقفول', 'string', 'بعد التسجيل', false],
            ['growth.preview.locked_message', 'growth', 'رسالة منع الدرس المقفول', 'text', 'الدرس ده مش ضمن المعاينة المجّانيّة — سجّل حسابك وافتح التدريب كامل.', false],
            ['growth.preview.cta', 'growth', 'زرّ فتح التدريب', 'string', 'افتح التدريب كامل', false],
            ['growth.preview.embed_template', 'growth', 'قالب تضمين الفيديو', 'string', 'https://www.youtube.com/embed/{id}', false],

            // ---------------- 21.1-ج — لوحة متصدّري الدعوات
            ['growth.invite_board.title', 'growth', 'عنوان لوحة الدعوات', 'string', 'متصدّرو الدعوات', false],
            ['growth.invite_board.size', 'growth', 'عدد صفوف اللوحة', 'number', '10', false],
            ['growth.invite_board.months', 'growth', 'عدد الشهور المتاحة', 'number', '6', false],
            ['growth.invite_board.my_rank', 'growth', 'سطر ترتيبي', 'string', 'ترتيبك الشهر ده: {rank}', false],
            ['growth.invite_board.empty', 'growth', 'الحالة الفارغة للوحة', 'text', 'مافيش دعوات مكتملة الشهر ده لسّه — ابدأ إنت.', false],

            // ---------------- 21.1-أ · 12.14 — قوالب صورة الـOG لكلّ نوع رابط
            ['growth.og.background', 'growth', 'خلفيّة بطاقة الرابط', 'string', '#0b1512', false],
            ['growth.og.text', 'growth', 'لون نصّ بطاقة الرابط', 'string', '#e8f5f2', false],
            ['growth.og.title_chars', 'growth', 'أحرف السطر في البطاقة', 'number', '26', false],
            ['growth.og.footer', 'growth', 'سطر أسفل البطاقة', 'string', 'ابدأ رحلتك معنا', false],
            ['growth.og.cache_seconds', 'growth', 'كاش صور الروابط (ثوانٍ)', 'number', '3600', false],
            ['growth.og.templates', 'growth', 'قالب صورة الـOG لكلّ نوع رابط', 'json', json_encode([
                'course' => ['label' => 'تدريب', 'accent' => '#00d4b8', 'glyph' => 'book'],
                'path' => ['label' => 'مسار تعلّم', 'accent' => '#7c9cff', 'glyph' => 'path'],
                'profile' => ['label' => 'بروفايل', 'accent' => '#f0b429', 'glyph' => 'person'],
                'leaderboard' => ['label' => 'لوحة الترتيب', 'accent' => '#ff8a5b', 'glyph' => 'trophy'],
                'article' => ['label' => 'مقال', 'accent' => '#9ad5a0', 'glyph' => 'quote'],
                'certificate' => ['label' => 'شهادة معتمدة', 'accent' => '#00d4b8', 'glyph' => 'seal'],
                'tip' => ['label' => 'نصيحة الأسبوع', 'accent' => '#f0b429', 'glyph' => 'quote'],
            ], JSON_UNESCAPED_UNICODE), false],

            // ---------------- 21.2-أ — مركز المقالات (الواجهة العامّة)
            ['growth.seo.index_articles', 'growth', 'فهرسة صفحات المقالات', 'bool', '1', false],
            ['growth.articles.per_page', 'growth', 'عدد المقالات في الصفحة', 'number', '12', false],
            ['growth.articles.related_count', 'growth', 'عدد مقالات «اقرأ كمان»', 'number', '3', false],
            ['growth.articles.show_author', 'growth', 'إظهار اسم الكاتب', 'bool', '1', false],
            ['growth.articles.index_title', 'growth', 'عنوان مركز المقالات', 'string', 'مقالات المنصّة', false],
            ['growth.articles.index_subtitle', 'growth', 'سطر مركز المقالات', 'text', 'محتوًى عربيّ مكتوب بأيدينا — تقرأه بلا حساب.', false],
            ['growth.articles.empty', 'growth', 'الحالة الفارغة للمقالات', 'text', 'لسّه مافيش مقالات منشورة هنا — قريب إن شاء الله.', false],
            ['growth.articles.share_label', 'growth', 'تسمية أزرار المشاركة', 'string', 'شارك المقال', false],
            ['growth.articles.related_label', 'growth', 'تسمية الربط بتدريب', 'string', 'اتعلّم الموضوع ده عمليًّا', false],
            ['growth.articles.allowed_tags', 'growth', 'وسوم HTML المسموحة في المقال', 'text',
                '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><img><figure><figcaption><code><pre><hr><table><thead><tbody><tr><th><td>', false],

            // ---------------- 21.2-ب — sitemap و robots
            ['growth.sitemap.enabled', 'growth', 'تفعيل sitemap.xml', 'bool', '1', false],
            ['growth.sitemap.cache_seconds', 'growth', 'كاش الخريطة (ثوانٍ)', 'number', '3600', false],
            ['growth.sitemap.max_certificates', 'growth', 'أقصى شهادات في الخريطة', 'number', '5000', false],
            ['growth.sitemap.certificate_statuses', 'growth', 'حالات الشهادة المفهرسة', 'json', '["valid"]', false],
            ['growth.robots.disallow', 'growth', 'مسارات ممنوعة على الزواحف', 'json',
                json_encode(['/admin', '/volunteer', '/settings', '/wallet', '/exams'], JSON_UNESCAPED_SLASHES), false],

            // ---------------- 21.2-ح — UTM موحّد على كلّ رابط
            ['growth.utm.enabled', 'growth', 'وسم كلّ الروابط بـUTM', 'bool', '1', false],
            ['growth.utm.defaults', 'growth', 'معايير UTM الافتراضيّة', 'json', '{"utm_source":"platform","utm_medium":"share"}', false],
            ['growth.utm.mediums', 'growth', 'قنوات UTM المعتمَدة', 'json',
                '["invite","share","article","image","weekly_card","volunteer_kit","certificate","preview"]', false],
            ['growth.utm.default_medium', 'growth', 'القناة الافتراضيّة', 'string', 'share', false],
            ['growth.utm.default_campaign', 'growth', 'الحملة الافتراضيّة', 'string', 'organic', false],

            // ---------------- 21.2-د/هـ — الكارت الأسبوعيّ وحزمة المتطوّعين
            ['growth.weekly_card.title', 'growth', 'عنوان الكارت الأسبوعيّ', 'string', 'نصيحة الأسبوع', false],
            ['growth.weekly_card.footer', 'growth', 'سطر أسفل الكارت', 'string', 'اتعلّم معنا', false],
            ['growth.weekly_card.period_days', 'growth', 'دوريّة الكارت (أيّام)', 'number', '7', false],
            ['growth.weekly_card.tips', 'growth', 'نصائح الكارت الأسبوعيّ', 'json', json_encode([
                'ذاكر ٢٥ دقيقة وارتاح ٥ — العقل بيثبّت المعلومة في الراحة مش في الزحمة.',
                'اكتب اللي فهمته بكلامك إنت. لو عرفت تشرحه، يبقى فهمته.',
                'الاستمرار أهمّ من الشدّة: نصّ ساعة كلّ يوم أنفع من يوم كامل في الأسبوع.',
                'راجع درس امبارح قبل ما تبدأ درس النهارده — دقيقتين بيوفّروا ساعة.',
                'اسأل بدري. السؤال المتأخّر بيتكلّف وقت، والسؤال البدري بيوفّره.',
            ], JSON_UNESCAPED_UNICODE), false],
            ['growth.volunteer_kit.title', 'growth', 'عنوان حزمة المحتوى', 'string', 'حزمة المحتوى', false],
            ['growth.volunteer_kit.subtitle', 'growth', 'سطر حزمة المحتوى', 'text', 'خُد الرابط والصور والنصوص الجاهزة وانشرها.', false],
            ['growth.volunteer_kit.audiences', 'growth', 'جمهور قوالب الحزمة', 'json', '["volunteers","everyone"]', false],
            ['growth.volunteer_kit.templates_limit', 'growth', 'أقصى قوالب في الحزمة', 'number', '8', false],
            ['growth.volunteer_kit.scripts', 'growth', 'النصوص الجاهزة للنشر', 'json', json_encode([
                ['title' => 'رسالة واتساب قصيرة', 'body' => "لو بتدوّر على تدريب عربيّ جادّ ومجّانيّ التفعيل، جرّب من هنا:\n{link}"],
                ['title' => 'منشور لينكدإن', 'body' => "بتعلّم على منصّة عربيّة بتشتغل بنظام: تدريب ⟵ امتحان ⟵ شهادة بكود تحقّق.\nلو مهتمّ، الرابط ده هيوصّلك:\n{link}"],
                ['title' => 'ستوري', 'body' => "بنبدأ دفعة جديدة — لو ناوي تتعلّم حاجة جديدة الشهر ده، ده مكانك:\n{link}"],
            ], JSON_UNESCAPED_UNICODE), false],

            // ---------------- 21.1-أ — زرّ [احصل على شهادتك] في صفحة التحقّق
            ['growth.certificate.cta_label', 'growth', 'زرّ «احصل على شهادتك»', 'string', 'احصل على شهادتك', false],
            ['growth.certificate.cta_hint', 'growth', 'سطر تحت زرّ الشهادة', 'text', 'اتعلّم، امتحن، وخُد شهادة بكود تحقّق زيّ دي.', false],

            // ---------------- 21.3-د — الموافقة والخصوصيّة
            ['ads.consent.remember_days', 'ads', 'مدّة حفظ الموافقة (أيّام)', 'number', '180', false],
            ['ads.consent.accept_label', 'ads', 'زرّ القبول', 'string', 'أوافق', false],
            ['ads.consent.reject_label', 'ads', 'زرّ الرفض', 'string', 'أرفض', false],
            ['ads.consent.custom_label', 'ads', 'زرّ التخصيص', 'string', 'تخصيص', false],
            ['ads.consent.save_label', 'ads', 'زرّ حفظ التخصيص', 'string', 'احفظ اختياري', false],
            ['ads.consent.purpose_ads', 'ads', 'غرض: الإعلان', 'string', 'قياس الإعلانات وإعادة الاستهداف', false],
            ['ads.consent.purpose_analytics', 'ads', 'غرض: القياس الداخليّ', 'string', 'قياس داخليّ لتحسين المنصّة', false],
            ['ads.consent.policy_link', 'ads', 'رابط سياسة الخصوصيّة', 'string', 'سياسة الخصوصيّة وحقّ السحب', false],

            // ---------------- 21.3-أ — البكسل وأحداث الخادم
            ['ads.capi.enabled', 'ads', 'تفعيل أحداث الخادم (CAPI)', 'bool', '1', false],
            ['ads.capi.endpoint', 'ads', 'عنوان Conversions API', 'string', 'https://graph.facebook.com', true],
            ['ads.capi.api_version', 'ads', 'إصدار الـAPI', 'string', 'v19.0', true],
            ['ads.capi.timeout_seconds', 'ads', 'مهلة طلب الخادم (ثوانٍ)', 'number', '5', false],
            ['ads.pixel.queue_max', 'ads', 'أقصى أحداث منتظرة في الجلسة', 'number', '10', false],
            ['ads.pixel.payload_keys', 'ads', 'مفاتيح الحمولة المسموحة', 'json',
                '["value","currency","content_ids","content_name","content_type"]', false],
            ['ads.events.currency', 'ads', 'عملة قيمة الأحداث', 'string', 'USD', false],
            ['ads.events.topup_source', 'ads', 'مصدر حركة الشحن', 'string', 'topup', false],
            ['ads.events.reconcile_limit', 'ads', 'أقصى تحويلات تُصالَح في الطلب', 'number', '5', false],
            ['ads.events.route_map', 'ads', 'خريطة المسار ⟵ الحدث', 'json', json_encode([
                'store.product' => ['event' => 'course_page_view'],
                'growth.preview.course' => ['event' => 'course_page_view'],
                'register' => ['event' => 'registration_started'],
                'learning.lesson' => ['event' => 'first_lesson_started', 'once' => true],
                'growth.preview.lesson' => ['event' => 'first_lesson_started', 'once' => true],
                'store.quote' => ['event' => 'checkout_opened'],
                'wallet.topup' => ['event' => 'checkout_opened'],
            ], JSON_UNESCAPED_UNICODE), false],

            // ---------------- 21.3-ب/ج — الشرائح والجمهور المشابه
            ['ads.audience.max_rows', 'ads', 'أقصى صفوف الشريحة', 'number', '50000', false],
            ['ads.audience.view_to_register_days', 'ads', 'مهلة «فتح ولم يسجّل» (أيّام)', 'number', '7', false],
            ['ads.best_user.return_after_days', 'ads', 'مدّة «عاد» بعد التفعيل (أيّام)', 'number', '7', false],
        ];

        // تفعيل/إيقاف كلّ حدث على حدة (21.3-و) — صفٌّ لكلّ حدثٍ من الثمانية
        foreach (AdEvents::catalog() as $key => $meta) {
            $rows[] = ['ads.events.'.$key, 'ads', 'حدث: '.$meta['label'], 'bool', '1', false];
        }

        foreach ($rows as [$key, $group, $label, $type, $default, $ownerOnly]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
                'is_sensitive' => $ownerOnly,
                'is_owner_only' => $ownerOnly,
            ]);
        }
    }

    // ---------------------------------------------------------------- المحتوى التجريبيّ

    /** مقالٌ منشور ومقالٌ مسودّة — فيُختبَر أنّ العامّ يرى المنشور وحده */
    private function articles(): void
    {
        $author = User::query()->where('status', 'active')->orderBy('id')->first();
        $publisher = User::query()->where('status', 'active')->orderBy('id')->skip(1)->first() ?? $author;

        if (! $author) {
            return;
        }

        $category = ArticleCategory::updateOrCreate(
            ['slug' => 'talam-thati'],
            ['name_ar' => 'التعلّم الذاتيّ', 'sort_order' => 1],
        );

        Article::updateOrCreate(['slug' => 'ezay-tebtedy-tetallem-barmaga'], [
            'article_category_id' => $category->id,
            'author_id' => $author->id,
            'published_by' => $publisher->id,
            'title' => 'إزّاي تبدأ تتعلّم البرمجة من غير ما تتوه',
            'excerpt' => 'خطّة بسيطة من أربع خطوات تبدأ بيها من الصفر، ومن غير ما تشتري كورس واحد.',
            'body' => '<p>أكتر سؤال بيوصلنا: «أبدأ منين؟». الإجابة مش كورس، الإجابة <strong>ترتيب</strong>.</p>'
                .'<h2>١) اختار هدف تشوفه</h2><p>هدفك الأوّل يكون حاجة تشتغل قدّامك، مش شهادة.</p>'
                .'<h2>٢) اتعلّم أساس واحد بس</h2><p>لغة واحدة، وخلاص. التنقّل بين اللغات في الشهر الأوّل بيضيّع الوقت.</p>'
                .'<h2>٣) اكتب كود كلّ يوم</h2><p>نصّ ساعة يوميًّا أنفع من يوم كامل في الأسبوع.</p>'
                .'<h2>٤) راجع بصوت عالي</h2><p>اشرح اللي فهمته لحدّ تاني — ولو لنفسك.</p>',
            'tags' => ['برمجة', 'مبتدئين'],
            'meta_title' => 'إزّاي تبدأ تتعلّم البرمجة — دليل عمليّ للمبتدئين',
            'meta_description' => 'خطّة من أربع خطوات للبداية الصحّ في تعلّم البرمجة بلا تشتّت.',
            'status' => ArticleWorkflow::PUBLISHED,
            'published_at' => now()->subDays(3),
        ]);

        Article::updateOrCreate(['slug' => 'mosawada-lel-tagreba'], [
            'article_category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'مسوّدة تحت الكتابة',
            'excerpt' => 'دي مسوّدة — والمفروض ما تظهرش لأيّ زائر.',
            'body' => '<p>محتوى لسّه ما اتراجعش.</p>',
            'status' => ArticleWorkflow::DRAFT,
        ]);
    }

    /** شريحة لكلّ شرطٍ معتمَد — فتُختبَر القائمة المقفولة كلّها */
    private function audiences(): void
    {
        foreach (AudienceResolver::rules() as $key => $label) {
            AdAudience::updateOrCreate(['name' => $label], [
                'kind' => $key === 'best_users' ? 'lookalike_source' : 'retargeting',
                'rule' => ['key' => $key],
                'ttl_days' => 30,
                'refresh_hours' => 24,
                'is_active' => true,
            ]);
        }
    }
}
