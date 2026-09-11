<?php

namespace Database\Seeders;

use App\Models\CelebrationEvent;
use App\Models\PositiveMessage;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات وبيانات: الصفحة الرئيسيّة العامّة (21.1 · 21.2) · الرسائل الإيجابيّة
 * (2.6-ب) · لقب السفير (7.6.1).
 *
 * القاعدة الذهبيّة 2.13: **لا رقم ولا نصّ محروق** — كلّ ما تراه الصفحة الرئيسيّة
 * مفتاحٌ في جدول الإعدادات بقيمة افتراضيّة قابلة للاسترجاع.
 * ولا يُسجَّل في DatabaseSeeder — يُجمَّع مع بقيّة سيدرات المجالات.
 */
class HomeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->celebrations();
        $this->messages();

        Cache::forget('settings');
    }

    public function settings(): void
    {
        $rows = [
            // ---------------- الصفحة الرئيسيّة العامّة: الفهرسة والميتا (21.2-ب)
            ['home.meta_title', 'home', 'عنوان الميتا للصفحة الرئيسيّة', 'string', 'المنصّة — اتعلّم واطلع بشهادة تقدر تثبتها'],
            ['home.meta_description', 'home', 'وصف الميتا للصفحة الرئيسيّة', 'text', 'منصّة عربيّة للتعلّم والتطوّع: تدريبات ومسارات وشهادات معتمدة، ومجتمع بيشتغل جنبك — والتسجيل والتفعيل مجّانيّان.'],
            ['home.og.image', 'home', 'صورة OG للصفحة الرئيسيّة', 'media', ''],
            ['home.brand.name', 'home', 'الاسم الظاهر في الشريط العلويّ', 'string', 'المنصّة'],
            ['home.date_format', 'home', 'صيغة التاريخ في الصفحة الرئيسيّة', 'string', 'j F Y'],

            // ---------------- Schema.org Organization (21.2-ب)
            ['home.org.name', 'home', 'اسم الجهة في Schema.org', 'string', 'المنصّة'],
            ['home.org.logo', 'home', 'شعار الجهة في Schema.org', 'media', ''],
            ['home.org.same_as', 'home', 'روابط الجهة الرسميّة (Schema.org sameAs)', 'json', '[]'],

            // ---------------- الشريط العلويّ والبطل
            ['home.nav.login', 'home', 'نصّ زرّ تسجيل الدخول', 'string', 'تسجيل الدخول'],
            ['home.nav.register', 'home', 'نصّ زرّ إنشاء الحساب', 'string', 'أنشئ حسابك'],
            ['home.hero.eyebrow', 'home', 'سطر فوق العنوان', 'string', 'منصّة تعلّم وتطوّع عربيّة'],
            ['home.hero.title', 'home', 'عنوان البطل', 'string', 'اتعلّم مهارة حقيقيّة، واطلع بشهادة تقدر تثبتها.'],
            ['home.hero.subtitle', 'home', 'نصّ البطل', 'text', 'تدريبات عربيّة مرتّبة في مسارات، ومجتمع بيشتغل جنبك، وشهادة لكلّ إنجاز — كلّه في مكان واحد.'],
            ['home.hero.primary_cta', 'home', 'الفعل الرئيسيّ في البطل', 'string', 'ابدأ مجّانًا'],
            ['home.hero.secondary_cta', 'home', 'الفعل الثانويّ في البطل', 'string', 'اتفرّج على التدريبات'],

            // ---------------- قيمة المنصّة
            ['home.value.title', 'home', 'عنوان قسم القيمة', 'string', 'ليه المنصّة دي؟'],
            ['home.value.items', 'home', 'بلوكات القيمة (عنوان + سطر)', 'json', json_encode([
                ['title' => 'تدريبات عربيّة مرتّبة', 'body' => 'مسارات واضحة تمشي فيها خطوة خطوة، مش فيديوهات متفرّقة.'],
                ['title' => 'شهادة تقدر تتحقّق منها', 'body' => 'لكلّ شهادة صفحة تحقّق عامّة يفتحها صاحب العمل بكود واحد.'],
                ['title' => 'مجتمع بيشتغل جنبك', 'body' => 'فعاليّات وتحدّيات وفريق تطوّع حقيقيّ بيتعلّم ويبني.'],
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- التسجيل والتفعيل مجّانيّان (2.5-د)
            ['home.free.title', 'home', 'عنوان بلوك المجّانيّة', 'string', 'التسجيل والتفعيل مجّانيّان'],
            ['home.free.body', 'home', 'نصّ بلوك المجّانيّة', 'text', 'تفتح حسابك وتفعّله من غير ما تدفع مليم. اللي بفلوس هو التدريبات المدفوعة نفسها — ومكتوب سعرها قدّامك قبل ما تختار.'],
            ['home.free.points', 'home', 'نقاط بلوك المجّانيّة', 'json', json_encode([
                'إنشاء الحساب مجّانيّ',
                'تفعيل الحساب مجّانيّ',
                'تدريبات مجّانيّة متاحة من أوّل يوم',
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- الأداة المجّانيّة كباب دخول: منشئ الـCV بلا تسجيل (21.2-ج)
            ['home.free.cv_enabled', 'home', 'إظهار بلوك منشئ الـCV المجّانيّ', 'bool', '1'],
            ['home.free.cv_title', 'home', 'عنوان بلوك منشئ الـCV', 'string', 'منشئ سيرة ذاتيّة مجّانيّ — من غير تسجيل'],
            ['home.free.cv_body', 'home', 'نصّ بلوك منشئ الـCV', 'text', 'قالب واحد مجّانيّ: تملا بياناتك وتشوف سيرتك قدّامك لحظة بلحظة بلا حساب — والتحميل بس هو اللي بيطلب إنشاء حساب، وشغلك بيستنّاك فيه.'],
            ['home.free.cv_cta', 'home', 'زرّ بلوك منشئ الـCV', 'string', 'ابدأ سيرتك دلوقتي'],

            // ---------------- التدريبات والمسارات (21.1-أ)
            ['home.courses.title', 'home', 'عنوان قسم التدريبات', 'string', 'أحدث التدريبات'],
            ['home.courses.limit', 'home', 'عدد التدريبات المعروضة', 'number', '4'],
            ['home.courses.cta', 'home', 'نصّ زرّ كارت التدريب', 'string', 'ابدأ التدريب'],
            ['home.courses.free_label', 'home', 'لافتة التدريب المجّانيّ', 'string', 'مجّانيّ'],
            ['home.courses.empty', 'home', 'الحالة الفارغة للتدريبات', 'text', 'التدريبات الأولى في الطريق — سجّل دلوقتي وتوصلك أوّل ما تنزل.'],
            ['home.courses.empty_cta', 'home', 'زرّ الحالة الفارغة', 'string', 'أنشئ حسابك'],
            ['home.paths.title', 'home', 'عنوان قسم المسارات', 'string', 'المسارات'],
            ['home.paths.limit', 'home', 'عدد المسارات المعروضة', 'number', '3'],

            // ---------------- المقالات (21.2-أ)
            ['home.articles.title', 'home', 'عنوان قسم المقالات', 'string', 'أحدث المقالات'],
            ['home.articles.limit', 'home', 'عدد المقالات المعروضة', 'number', '3'],
            ['home.articles.published_status', 'home', 'حالة المقال المنشور', 'string', 'published'],
            ['home.articles.show_author', 'home', 'إظهار اسم الكاتب', 'bool', '1'],

            // ---------------- الفعاليّات (13.3)
            ['home.events.title', 'home', 'عنوان قسم الفعاليّات', 'string', 'الفعاليّات القادمة'],
            ['home.events.limit', 'home', 'عدد الفعاليّات المعروضة', 'number', '3'],
            ['home.events.cta', 'home', 'نصّ زرّ كارت الفعاليّة', 'string', 'سجّل واحجز مكانك'],
            ['home.events.date_format', 'home', 'صيغة تاريخ الفعاليّة', 'string', 'l j F — H:i'],
            ['home.events.mode_online', 'home', 'وصف الفعاليّة الأونلاين', 'string', 'أونلاين'],
            ['home.events.mode_offline', 'home', 'وصف الفعاليّة الحضوريّة', 'string', 'حضوريّ'],
            ['home.events.mode_hybrid', 'home', 'وصف الفعاليّة المختلطة', 'string', 'مختلط'],

            // ---------------- السفراء على الرئيسيّة (7.6.1 · 21.1-ج)
            ['home.ambassadors.title', 'home', 'عنوان قسم السفراء', 'string', 'سفراء المنصّة'],
            ['home.ambassadors.subtitle', 'home', 'سطر قسم السفراء', 'text', 'ناس دعت أصحابها فكبر المكان بيهم — واللقب بيتحسب بالدعوات المفعّلة بس.'],
            ['home.ambassadors.limit', 'home', 'عدد السفراء على الرئيسيّة', 'number', '5'],
            ['home.ambassadors.more', 'home', 'نصّ رابط اللوحة الكاملة', 'string', 'اللوحة كاملة'],

            // ---------------- دعوة التسجيل والتذييل
            ['home.cta.title', 'home', 'عنوان دعوة التسجيل', 'string', 'ابدأ من غير ما تدفع حاجة'],
            ['home.cta.body', 'home', 'نصّ دعوة التسجيل', 'text', 'أنشئ حسابك، فعّله مجّانًا، وابدأ أوّل تدريب النهارده.'],
            ['home.cta.button', 'home', 'زرّ دعوة التسجيل', 'string', 'أنشئ حسابك دلوقتي'],
            ['home.cta.note', 'home', 'سطر تحت زرّ التسجيل', 'string', 'عندك حساب بالفعل؟'],
            ['home.footer.note', 'home', 'سطر التذييل', 'text', 'منصّة تعلّم وتطوّع عربيّة — بنتعلّم ونشتغل جنب بعض.'],
            ['home.footer.verify', 'home', 'نصّ رابط التحقّق من شهادة', 'string', 'التحقّق من شهادة'],
            ['home.footer.ambassadors', 'home', 'نصّ رابط لوحة السفراء', 'string', 'لوحة السفراء'],

            // ---------------- الرسائل الإيجابيّة (2.6-ب)
            ['engagement.positive.enabled', 'engagement', 'تفعيل الرسائل الإيجابيّة', 'bool', '1'],
            ['engagement.positive.icon_chance_percent', 'engagement', 'احتمال ظهور الأيقونة (%)', 'number', '3'],
            ['engagement.positive.ticket_chance_percent', 'engagement', 'احتمال زرّ التذكرة (%)', 'number', '20'],
            ['engagement.positive.ticket_amount', 'engagement', 'عدد تذاكر المفاجأة', 'number', '1'],
            ['engagement.positive.ticket_currency', 'engagement', 'عملة تذكرة المفاجأة', 'string', 'tickets'],
            ['engagement.positive.daily_ticket_cap', 'engagement', 'حدّ تذاكر المفاجأة يوميًّا', 'number', '1'],
            ['engagement.positive.no_repeat_last', 'engagement', 'كم رسالة لا تتكرّر قبل إعادتها', 'number', '5'],
            ['engagement.positive.per_page', 'engagement', 'عدد الرسائل في صفحة الإدارة', 'number', '20'],
            ['engagement.positive.surprise_context', 'engagement', 'سياق الأيقونة المفاجئة', 'string', 'surprise'],
            ['engagement.positive.envelope_title', 'engagement', 'عنوان ظرف الرسالة', 'string', 'وصلتك رسالة'],
            ['engagement.positive.open_label', 'engagement', 'نصّ زرّ الفتح', 'string', 'افتح الظرف'],
            ['engagement.positive.close_label', 'engagement', 'نصّ زرّ الإغلاق', 'string', 'تمام'],
            ['engagement.positive.ticket_label', 'engagement', 'نصّ زرّ التذكرة', 'string', 'استلام تذكرة'],
            ['engagement.positive.icon_label', 'engagement', 'وصف الأيقونة لقارئ الشاشة', 'string', 'رسالة إيجابيّة مستنّياك'],
            ['engagement.positive.ticket_reason', 'engagement', 'سبب قيد تذكرة المفاجأة', 'string', 'تذكرة رسالة إيجابيّة'],
            ['engagement.positive.ticket_granted_text', 'engagement', 'رسالة نجاح التذكرة', 'string', 'وصلتك :count تذكرة 🎟️'],
            ['engagement.positive.ticket_denied_text', 'engagement', 'رسالة انتهاء تذاكر اليوم', 'string', 'خدت تذكرة المفاجأة النهارده — نشوفك بكرة.'],
            ['engagement.positive.contexts', 'engagement', 'سياقات الرسائل الإيجابيّة', 'json', json_encode([
                'any' => 'أيّ لحظة',
                'surprise' => 'الأيقونة المفاجئة',
                'lesson_complete' => 'بعد إكمال درس',
                'course_complete' => 'بعد إتمام تدريب',
                'streak_broken' => 'بعد انكسار الستريك',
                'exam_failed' => 'بعد محاولة امتحان غير موفّقة',
                'empty_state' => 'في الشاشات الفاضية',
                'first_login' => 'أوّل دخول بعد التفعيل',
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- سهم العودة لأعلى (2.6-أ)
            ['ux.back_to_top.label', 'ux', 'وصف سهم العودة لأعلى', 'string', 'ارجع لأعلى الصفحة'],
            ['ux.back_to_top.after_px', 'ux', 'بعد كم بكسل يظهر السهم', 'number', '320'],

            // ---------------- لقب السفير (7.6.1 · 2.9-8)
            ['ambassadors.enabled', 'ambassadors', 'تفعيل ألقاب السفراء', 'bool', '1'],
            ['ambassadors.tiers', 'ambassadors', 'عتبات ألقاب السفراء', 'json', json_encode([
                ['key' => 'bronze', 'label' => 'سفير برونزيّ', 'threshold' => 5],
                ['key' => 'silver', 'label' => 'سفير فضّيّ', 'threshold' => 15],
                ['key' => 'gold', 'label' => 'سفير ذهبيّ', 'threshold' => 30],
                ['key' => 'diamond', 'label' => 'سفير ماسيّ', 'threshold' => 50],
            ], JSON_UNESCAPED_UNICODE)],
            ['ambassadors.leaderboard.public', 'ambassadors', 'لوحة المتصدّرين عامّة', 'bool', '1'],
            ['ambassadors.leaderboard.limit', 'ambassadors', 'عدد صفوف لوحة المتصدّرين', 'number', '20'],
            ['ambassadors.sync.batch', 'ambassadors', 'حدّ المزامنة في المرّة الواحدة', 'number', '200'],
            ['ambassadors.celebration.prefix', 'ambassadors', 'بادئة مفتاح احتفال اللقب', 'string', 'ambassador.'],
            ['ambassadors.notification.category', 'ambassadors', 'فئة إشعار اللقب', 'string', 'ambassador'],
            ['ambassadors.notification.title', 'ambassadors', 'عنوان إشعار اللقب', 'string', 'بقيت :label 👑'],
            ['ambassadors.notification.body', 'ambassadors', 'نصّ إشعار اللقب', 'text', ':count دعوة مفعَّلة وصلتك للقب :label — شكرًا إنّك بتكبّر المكان معانا.'],
            ['ambassadors.invites_label', 'ambassadors', 'لافتة عدّاد الدعوات', 'string', 'دعوة مفعّلة'],
            ['ambassadors.page.title', 'ambassadors', 'عنوان لوحة السفراء', 'string', 'سفراء المنصّة'],
            ['ambassadors.page.subtitle', 'ambassadors', 'سطر لوحة السفراء', 'text', 'اللقب بيتحسب بالدعوات المفعّلة بس — يعني ناس دخلت فعلًا وفعّلت حسابها.'],
            ['ambassadors.tiers_title', 'ambassadors', 'عنوان سلّم الألقاب', 'string', 'سلّم الألقاب'],
            ['ambassadors.no_title_yet', 'ambassadors', 'سطر مَن لم يتلقّب بعد', 'string', 'لسّه مابتلقّبتش — أوّل دعوة مفعّلة هي البداية.'],
            ['ambassadors.cta', 'ambassadors', 'زرّ رابط الدعوة', 'string', 'خد رابط دعوتك'],
            ['ambassadors.empty', 'ambassadors', 'الحالة الفارغة للوحة', 'text', 'لسّه محدّش وصل لأوّل لقب — تقدر تكون إنت الأوّل.'],
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

    /**
     * احتفال لكلّ لقب على حدة (2.14) — **مستوى متوسّط**: كونفيتي خفيف وصوت
     * قصير؛ فالذروة محجوزة للشهادة والتسكين ونظائرهما كي تبقى ذروةً.
     */
    private function celebrations(): void
    {
        $prefix = 'ambassador.';
        $tiers = [
            'bronze' => 'لقب سفير برونزيّ',
            'silver' => 'لقب سفير فضّيّ',
            'gold' => 'لقب سفير ذهبيّ',
            'diamond' => 'لقب سفير ماسيّ',
        ];

        foreach ($tiers as $key => $label) {
            CelebrationEvent::updateOrCreate(['key' => $prefix.$key], [
                'label_ar' => $label,
                'tier' => 2,
                'message_ar' => 'مبروك يا :name — بقيت :label 👑',
            ]);
        }
    }

    /** مكتبة رسائل ابتدائيّة بنبرة المنصّة: دافئة بلا مبالغة وبلا لوم (2.17-ج) */
    private function messages(): void
    {
        $rows = [
            ['surprise', 'إنت بتعمل شغل كويّس — كمّل بهدوء.', '🌱'],
            ['surprise', 'خطوة صغيرة النهارده أحسن من خطّة كبيرة بكرة.', '🚶'],
            ['surprise', 'مبسوطين إنّك هنا.', '💛'],
            ['surprise', 'مش لازم تخلّص كلّ حاجة النهارده — يكفي إنّك بدأت.', '☕'],
            ['any', 'كلّ حاجة اتعلّمتها فضلت معاك.', '📌'],
            ['any', 'الاستمرار أهمّ من السرعة.', '🧭'],
            ['lesson_complete', 'درس خلص ✓ — ماشي صحّ.', '✅'],
            ['lesson_complete', 'خدت خطوة زيادة النهارده.', '👏'],
            ['course_complete', 'تدريب كامل خلص — ده مجهود حقيقيّ.', '🎓'],
            ['streak_broken', 'يوم فات، والباب لسّه مفتوح — ابدأ من النهارده عادي.', '🌤️'],
            ['streak_broken', 'الانقطاع مش فشل، والرجوع أسهل ممّا تتخيّل.', '🔁'],
            ['exam_failed', 'المحاولة دي وضّحت لك ناقصك إيه — وده مكسب.', '🧪'],
            ['empty_state', 'لسّه بدري — أوّل خطوة مستنّياك.', '🌅'],
            ['first_login', 'أهلًا بيك — خد وقتك واتفرّج الأوّل.', '👋'],
        ];

        foreach ($rows as $index => [$context, $body, $emoji]) {
            PositiveMessage::updateOrCreate(
                ['context' => $context, 'body_ar' => $body],
                ['emoji' => $emoji, 'is_active' => true, 'sort_order' => $index],
            );
        }
    }
}
