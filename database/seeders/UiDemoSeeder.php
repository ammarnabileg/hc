<?php

namespace Database\Seeders;

use App\Models\NameParticle;
use App\Models\Setting;
use Illuminate\Database\Seeder;

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

            // «شاشة أوّل مرّة»: الشاشات المفعَّلة + محتوى كلّ شاشة + قالب جاهز (2.15-هـ)
            ['ux.first_time.content', 'ux', 'محتوى «شاشة أوّل مرّة» لكلّ شاشة', 'json', '{}'],
            ['ux.first_time.default_template', 'ux', 'القالب الجاهز لـ«شاشة أوّل مرّة»', 'json',
                '[{"title":"أهلًا بيك هنا","body":"الصفحة دي بتجاوب على سؤال واحد — والباقي ورا خطوة واحدة."},'
                .'{"title":"الفلاتر والعروض","body":"اظبط الفلاتر مرّة واحفظها كـ«عرض محفوظ» — هتلاقيها فوق الجدول."},'
                .'{"title":"ثبّت الصفحة","body":"لو بتفتحها كتير، اضغط الدبّوس جنب العنوان وهتلاقيها أعلى السايد بار."}]'],

            // ---------------- الاستخراج كصورة (12.14-هـ · 12.14-ح)
            ['images.export.default_rows', 'appearance', 'عدد صفوف «أفضل N» في الاستخراج كصورة', 'number', '10'],
            ['images.export.templates_limit', 'appearance', 'أقصى قوالب تظهر في زرّ الاستخراج', 'number', '20'],
            ['images.board.bg_top', 'appearance', 'خلفيّة اللوحة المستخرَجة (أعلى)', 'color', '#04121d'],
            ['images.board.bg_bottom', 'appearance', 'خلفيّة اللوحة المستخرَجة (أسفل)', 'color', '#030d17'],
            ['images.board.text', 'appearance', 'لون نصّ اللوحة المستخرَجة', 'color', '#eaf2f8'],
            ['images.board.muted', 'appearance', 'لون النصّ الثانويّ في اللوحة', 'color', '#9fb3c8'],
            ['images.board.brand', 'appearance', 'لون الهويّة في اللوحة المستخرَجة', 'color', '#00d4b8'],
            ['images.board.row', 'appearance', 'خلفيّة صفّ اللوحة', 'color', '#08192a'],
            ['images.board.row_me', 'appearance', 'خلفيّة صفّي أنا في اللوحة', 'color', '#0b2c33'],

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
            ['cv.summary.max_chars', 'cv', 'أقصى طول للملخّص المهنيّ', 'number', '600'],
            ['cv.public.slug_length', 'cv', 'طول رمز الرابط العامّ للسيرة', 'number', '12'],
            ['cv.ats.font_path', 'cv', 'مسار الخطّ المضمَّن في PDF الـATS', 'string', 'fonts/Cairo-Regular.ttf'],
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
