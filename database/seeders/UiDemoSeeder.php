<?php

namespace Database\Seeders;

use App\Models\NameParticle;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * إعدادات وبيانات مجال «الواجهة والبروفايل والاستوديو».
 *
 * القاعدة الذهبيّة 2.13: **لكلّ ميزة إعدادات كاملة في لوحة الإدارة، وممنوع أيّ
 * رقم أو نصّ محروق**. ولكلّ مفتاح هنا **شاشة فعليّة**: نضعه في مجموعة مربوطة
 * بتاب في `SettingsRegistry` — فلا يوجد إعداد بلا مكان يُعدَّل منه.
 *
 *  · `ux` ⟵ تاب «إعدادات المنصّة»    · `appearance` ⟵ تاب «الهويّة والمظهر»
 *  · `accounts` ⟵ تاب «إعدادات المنصّة» · `cv` ⟵ تاب «قوالب الـCV»
 */
class UiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->nameParticles();
        $this->firstRunSlides();
    }

    public function settings(): void
    {
        // [المفتاح, المجموعة, التسمية, النوع, الافتراضيّ]
        $rows = [
            // ---------------- مساحة العمل (2.15-د)
            ['ux.pins.max', 'ux', 'أقصى عدد صفحات مثبَّتة لكلّ مستخدم', 'number', '8'],
            ['ux.saved_views.max_per_screen', 'ux', 'أقصى عروض محفوظة لكلّ شاشة', 'number', '10'],
            ['ux.palette.limit_per_group', 'ux', 'نتائج البحث الموحّد لكلّ مجموعة (Ctrl+K)', 'number', '5'],
            ['ux.back_to_top.label', 'ux', 'نصّ سهم العودة لأعلى', 'string', 'ارجع لأعلى الصفحة'],

            /*
             | ⭐ «شاشة أوّل مرّة»: مصدر المحتوى **جدول `onboarding_slides` وحده** —
             | فهو الذي يملك شاشة الإدارة (CRUD وترتيب وتفعيل وقوالب ومعاينة).
             | المفتاحان القديمان (`ux.first_time.content` و`.default_template`) كانا
             | مصدرًا ثانيًا لا يكتب فيه أحد ولا يقرأ منه المستخدم، فأُزيلا من الكتالوج
             | ورُحِّل محتواهما في هجرة التوحيد. والباقي هنا: `enabled_screens` وحده
             | في `SettingSeeder` لأنّه مشترك بين اللوحة والمستخدم.
             */

            // ---------------- الاستخراج كصورة (12.14-هـ · 12.14-ح)
            ['images.export.default_rows', 'appearance', 'عدد صفوف «أفضل N» في الاستخراج كصورة', 'number', '10'],
            ['images.export.templates_limit', 'appearance', 'أقصى قوالب تظهر في زرّ الاستخراج', 'number', '20'],
            ['images.board.bg_top', 'appearance', 'خلفيّة اللوحة المستخرَجة (أعلى)', 'color', '#fcfbf8'],
            ['images.board.bg_bottom', 'appearance', 'خلفيّة اللوحة المستخرَجة (أسفل)', 'color', '#f3efe7'],
            ['images.board.text', 'appearance', 'لون نصّ اللوحة المستخرَجة', 'color', '#171715'],
            ['images.board.muted', 'appearance', 'لون النصّ الثانويّ في اللوحة', 'color', '#65645f'],
            ['images.board.brand', 'appearance', 'لون الهويّة في اللوحة المستخرَجة', 'color', '#d9231b'],
            ['images.board.row', 'appearance', 'خلفيّة صفّ اللوحة', 'color', '#ffffff'],
            ['images.board.row_me', 'appearance', 'خلفيّة صفّي أنا في اللوحة', 'color', '#fbece9'],

            /*
             | ---------------- توكنز نظام التصميم (2.10.1 — الهويّة 2.0، v5.7) — «الهويّة والمظهر»
             |
             | كلّ قيمةٍ هنا **منصوصةٌ حرفيًّا** في 2.10.1، وتُقرأ من `DesignTokens`
             | فتُكتَب متغيّراتٍ في `:root`/`:root[data-theme='dark']` تستعملها
             | ورقة الأنماط. فالمقاس والمدّة واللون **إعدادٌ لا رقمٌ محروق**
             | (2.13-ب) — يعدّلها المالك من شاشته.
             |
             | ⛔ ولا مفتاح يوقف الحركة: «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب)،
             | فالمُدد هنا تضبط **سرعتها** لا **وجودها**.
             |
             | ⛔ لا شريط تمرير مخصّص بعد الآن (2.10.1-23): أُصبح أصليًّا رفيعًا
             | بلا إعداد — فلا `design.scrollbar.*` هنا.
             | ⛔ ولا تدرّج للزرّ ولا للإبراز (2.10.1-1/7): لمسة حمراء صلبة —
             | فلا `design.button.gradient`/`design.accent.gradient` هنا؛ الزرّ
             | يقرأ `design.color.brand`/`.brand_hover` مباشرة.
             */
            // (10) السويتش: «المسار 42×24px … الإبهام دائرة 18px بيضاء تنزلق 3px→21px»
            ['design.switch.track_w', 'appearance', 'عرض مسار السويتش (بكسل)', 'number', '42'],
            ['design.switch.track_h', 'appearance', 'ارتفاع مسار السويتش (بكسل)', 'number', '24'],
            ['design.switch.thumb', 'appearance', 'قطر إبهام السويتش (بكسل)', 'number', '18'],
            ['design.switch.inset', 'appearance', 'حافّة إبهام السويتش (بكسل)', 'number', '3'],

            // (18) المؤشّر المخصّص: «نقطة 8px حمراء تكبر إلى 42px»
            ['design.cursor.dot', 'appearance', 'قطر نقطة المؤشّر المخصّص (بكسل)', 'number', '8'],
            ['design.cursor.hover', 'appearance', 'قطر المؤشّر فوق العناصر التفاعليّة (بكسل)', 'number', '42'],

            // (20) الحركات الثماني بمُددها المنصوصة — بلا تغيير عن ما قبل الهويّة 2.0
            ['design.anim.fadeup_ms', 'appearance', 'مدّة حركة fadeup (مللي ثانية)', 'number', '550'],
            ['design.anim.fadeup_shift', 'appearance', 'إزاحة ظهور fadeup (بكسل)', 'number', '16'],
            ['design.anim.float_s', 'appearance', 'مدّة حركة float (ثانية)', 'number', '5'],
            ['design.anim.float_shift', 'appearance', 'مدى طفوّ float (بكسل)', 'number', '4'],
            ['design.anim.text_shimmer_s', 'appearance', 'مدّة حركة text-shimmer (ثانية)', 'number', '5'],
            ['design.anim.shimmer_s', 'appearance', 'مدّة حركة shimmer (ثانية)', 'number', '3'],
            ['design.anim.pulse_dot_s', 'appearance', 'مدّة حركة pulse-dot (ثانية)', 'number', '2'],
            ['design.anim.spin_slow_s', 'appearance', 'مدّة حركة spin-slow (ثانية)', 'number', '11'],
            ['design.anim.ticker_s', 'appearance', 'مدّة حركة ticker (ثانية)', 'number', '22'],
            ['design.anim.blink_s', 'appearance', 'مدّة حركة blink (ثانية)', 'number', '2'],

            // 2.15-ج: «الحدّ الأدنى لمساحة اللمس 44×44 بكسل»
            ['design.touch.min', 'appearance', 'الحدّ الأدنى لمساحة اللمس (بكسل)', 'number', '44'],

            /*
             | ---------------- ألوان الهويّة الأساسيّة (2.10.1-3) — فاتح + داكن
             |
             | كلّ دورٍ هنا **مفتاحان**: `design.color.X` للفاتح (الافتراضيّ)
             | و`design.color.X.dark` للداكن (تجريبيّ) — بنفس اسم المتغيّر في
             | CSS، فيتغيّر الوضعان معًا من شاشة «الهويّة والمظهر» بلا نشرٍ جديد.
             */
            ['design.color.bg', 'appearance', 'خلفيّة الصفحة: فاتح', 'color', '#fcfbf8'],
            ['design.color.bg.dark', 'appearance', 'خلفيّة الصفحة: داكن', 'color', '#191917'],
            ['design.color.surface', 'appearance', 'خلفيّة الكروت: فاتح', 'color', '#ffffff'],
            ['design.color.surface.dark', 'appearance', 'خلفيّة الكروت: داكن', 'color', '#22221f'],
            ['design.color.soft', 'appearance', 'خلفيّة الغسيل (Wash): فاتح', 'color', '#f3efe7'],
            ['design.color.soft.dark', 'appearance', 'خلفيّة الغسيل (Wash): داكن', 'color', '#2c2b26'],
            ['design.color.line', 'appearance', 'لون الحدود: فاتح', 'color', '#dfddd5'],
            ['design.color.line.dark', 'appearance', 'لون الحدود: داكن', 'color', '#424139'],
            ['design.color.ink', 'appearance', 'لون النصّ الأساسيّ: فاتح', 'color', '#171715'],
            ['design.color.ink.dark', 'appearance', 'لون النصّ الأساسيّ: داكن', 'color', '#f7f4ec'],
            ['design.color.muted', 'appearance', 'لون النصّ الثانويّ: فاتح', 'color', '#65645f'],
            ['design.color.muted.dark', 'appearance', 'لون النصّ الثانويّ: داكن', 'color', '#b8b5ab'],
            ['design.color.brand', 'appearance', 'أحمر الهويّة (الأفعال): فاتح', 'color', '#d9231b'],
            ['design.color.brand.dark', 'appearance', 'أحمر الهويّة (الأفعال): داكن', 'color', '#f36b60'],
            ['design.color.brand_hover', 'appearance', 'أحمر الهويّة عند الـHover: فاتح', 'color', '#b61b15'],
            ['design.color.brand_hover.dark', 'appearance', 'أحمر الهويّة عند الـHover: داكن', 'color', '#ff8277'],
            ['design.color.brand_soft', 'appearance', 'خلفيّة أحمر الهويّة الخفيفة: فاتح', 'color', '#fbece9'],
            ['design.color.brand_soft.dark', 'appearance', 'خلفيّة أحمر الهويّة الخفيفة: داكن', 'color', '#352321'],

            // ---------------- الأفاتار: المقاسات الثلاثة (2.7)
            ['account.avatar.sizes', 'accounts', 'مقاسات الأفاتار المولَّدة عند الرفع', 'json', '[500,150,50]'],

            // ---------------- البروفايل (10)
            ['profile.bio.max_chars', 'accounts', 'أقصى طول للنبذة الشخصيّة', 'number', '280'],
            ['profile.bio.saved_label', 'accounts', 'نصّ تأكيد حفظ النبذة', 'string', 'اتحفظ ✓'],
            ['profile.share.text', 'accounts', 'نصّ مشاركة البروفايل', 'string', 'شوف بروفايلي على :platform'],

            // ---------------- السيرة الذاتيّة (9)
            ['cv.import.extensions', 'cv', 'امتدادات ملفّ الـCV المقبولة للاستيراد', 'json', '["txt","md","html","htm","docx","pdf"]'],
            ['cv.import.max_kb', 'cv', 'أقصى حجم ملفّ استيراد الـCV (كيلوبايت)', 'number', '4096'],
            ['cv.import.question', 'cv', 'سؤال ما قبل الاستيراد', 'string', 'نبدّل بياناتك بالملفّ ولا نضيف عليها؟'],
            ['cv.import.headings', 'cv', 'عناوين أقسام الـCV المتعرَّف عليها', 'json', '[]'],
            ['cv.import.profile_label', 'cv', 'عنوان قسم البيانات الأساسيّة في معاينة الاستيراد', 'string', 'البيانات الأساسيّة'],
            ['cv.summary.max_chars', 'cv', 'أقصى طول للملخّص المهنيّ', 'number', '600'],
            ['cv.public.slug_length', 'cv', 'طول رمز الرابط العامّ للسيرة', 'number', '12'],
            ['cv.ats.font_path', 'cv', 'مسار الخطّ المضمَّن في PDF الـATS', 'string', 'fonts/Alexandria-Regular.ttf'],
            ['cv.ats.margin_pt', 'cv', 'هامش صفحة الـATS (نقطة)', 'number', '56'],
            ['cv.ats.title_size_pt', 'cv', 'حجم الاسم في الـATS (نقطة)', 'number', '20'],
            ['cv.ats.heading_size_pt', 'cv', 'حجم عناوين الأقسام في الـATS', 'number', '13'],
            ['cv.ats.body_size_pt', 'cv', 'حجم النصّ في الـATS', 'number', '11'],
            ['cv.ats.leading', 'cv', 'تباعد الأسطر في الـATS', 'string', '1.55'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'label_ar' => $label,
                    'type' => $type,
                    'value' => $default,
                    'default_value' => $default,
                ],
            );
        }
    }

    /**
     * ⭐ شرائح «أوّل مرّة» للرئيسيّة — **محتوى عرض لا تعريف إعداد**.
     *
     * مصدر الشاشة صار جدول `onboarding_slides` وحده، والجدول في التنصيب الجديد
     * **فاضي عن قصد**: لا يظهر للمستخدم شيءٌ لم يكتبه أدمن (حالة فارغة صادقة).
     * فالبيئة التجريبيّة تحتاج شرائح حقيقيّة لتُرى الميزة — وهذه هي، بنفس الطريق
     * الذي يسلكه الأدمن من شاشته: صفوف في الجدول لا نصوصٌ في الكود.
     *
     * والحارس **لكلّ شاشة على حدة**: مَن كتب شرائحه بنفسه لا نضيف فوقها، ولا
     * نمنع بذور شاشةٍ أخرى لمجرّد أنّ الجدول ليس فاضيًا.
     */
    private function firstRunSlides(): void
    {
        $rows = [
            ['dashboard', 'دي رئيسيّتك', 'من هنا تشوف تدريباتك ومهامّك وكلّ جديد. بلا لفّ ولا دوران.', null, null],
            ['dashboard', 'كمّل اللي وقفت عنده', 'الكارت الأوّل بيرجّعك لآخر درس فتحته بضغطة واحدة.', 'خُدني هناك', '/learning/courses'],
        ];

        // الشاشات التي كُتِبت شرائحها بالفعل — تُحسَب مرّةً قبل الإدراج لا داخله
        $written = DB::table('onboarding_slides')->distinct()->pluck('screen')->all();

        foreach ($rows as $index => [$screen, $title, $body, $actionLabel, $actionUrl]) {
            if (in_array($screen, $written, true)) {
                continue;
            }

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

    /**
     * ⭐ أدوات الاسم (12.14-ج) — **قابلة للتعديل بالكامل من لوحة الإدارة**
     *   لأنّها تختلف بالثقافات؛ وهذه القائمة الابتدائيّة لا أكثر.
     */
    private function nameParticles(): void
    {
        $particles = [
            'ar' => ['عبد', 'عبدال', 'أبو', 'ابو', 'أبا', 'ابا', 'بن', 'ابن', 'آل', 'ال', 'الـ', 'أم', 'ام'],
            'en' => ['abd', 'abdel', 'abdul', 'abo', 'abu', 'bin', 'ibn', 'al', 'el'],
        ];

        foreach ($particles as $locale => $words) {
            foreach ($words as $word) {
                NameParticle::updateOrCreate(
                    ['particle' => $word],
                    ['locale' => $locale, 'is_active' => true],
                );
            }
        }
    }
}
