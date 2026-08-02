<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات المتجر التجريبيّة + إعداداته (2.13 · 7 من دليل البناء).
 * لا يُسجَّل في DatabaseSeeder — يُشغَّل وحده: `php artisan db:seed --class=StoreDemoSeeder`.
 */
class StoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->catalog();
    }

    /** كلّ رقم ونصّ في المتجر إعدادٌ في لوحة الإدارة — بلا أيّ قيمة محروقة (2.13) */
    public function settings(): void
    {
        $rows = [
            // ---------------- الشبكة والفلاتر (17 · 24.3)
            ['store.enabled', 'المتجر مفعَّل', 'bool', '1'],
            ['store.currency.label', 'اسم العملة المعروض', 'string', 'كوين'],
            ['store.currency.schema_code', 'رمز العملة في Schema.org', 'string', 'COINS'],
            ['store.grid.per_page', 'عدد العناصر في الصفحة', 'number', '24'],
            ['store.grid.default_sort', 'الفرز الافتراضيّ', 'string', 'newest'],
            ['store.filters.price_max_coins', 'سقف منزلق السعر (كوينز)', 'number', '100000'],
            ['store.free_label', 'نصّ العنصر المجّانيّ', 'string', 'مجّانيّ'],
            ['store.path.note_text', 'ملاحظة المسار غير المباع منفردًا', 'string', 'يُفتَح ضمن الباقات'],
            ['store.empty.text', 'نصّ الحالة الفارغة', 'string', 'مفيش نتائج للفلتر ده — جرّب توسّع شويّة.'],
            ['store.bundles.empty_text', 'نصّ الحالة الفارغة للباقات', 'string', 'مفيش باقات متاحة دلوقتي.'],

            // ---------------- التوفير والخصم — بقيمته الحقيقيّة (2.9 · 18)
            ['store.savings.text', 'نصّ «وفّرت كذا»', 'string', 'وفّرت {amount}'],

            // ---------------- المعاينة المجّانيّة (20.3 · 21.1)
            ['store.preview.pages_text', 'نصّ صفحات العيّنة', 'string', 'أوّل {pages} صفحات مجّانيّة كمعاينة قبل الشراء.'],
            ['store.preview.lessons_text', 'نصّ دروس المعاينة', 'string', 'أوّل {lessons} درس مجّانيّ كمعاينة — جرّب قبل ما تشتري.'],
            ['store.seo.index_products', 'فهرسة صفحات المنتجات', 'bool', '1'],

            // ---------------- الكوبونات (24.3)
            ['store.coupons.enabled', 'تفعيل أكواد الخصم', 'bool', '1'],
            ['store.coupon.invalid_text', 'نصّ الكود غير الصالح', 'string', 'الكود ده مش صالح للطلب ده — راجعه أو أكمل من غيره.'],
            ['store.coupon.expired_text', 'نصّ الكود المنتهي', 'string', 'الكود ده خلصت مدّته.'],
            ['store.coupon.exhausted_text', 'نصّ الكود المستنفد', 'string', 'الكود ده اتستخدم بالكامل.'],
            ['store.coupon.per_user_text', 'نصّ تجاوز حدّ المستخدم', 'string', 'استخدمت الكود ده قبل كده.'],

            // ---------------- Order-bump (17 — اثنان كحدٍّ أقصى)
            ['store.order_bump.enabled', 'تفعيل Order-bump', 'bool', '1'],
            ['store.order_bump.max', 'أقصى عدد عروض Bump في البوب-أب', 'number', '2'],
            ['store.order_bump.offers', 'عروض Order-bump', 'json', json_encode([
                [
                    'parent_type' => 'course',
                    'parent_slug' => 'excel-for-work',
                    'bump_type' => 'product',
                    'bump_slug' => 'excel-formulas-cheatsheet',
                    'price_coins' => 45,
                    'teaser' => 'ضيف ملخّص المعادلات معاك — يوفّر عليك وقت البحث.',
                ],
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- الشراء والرسائل (2.17-ب: ماذا حدث + ماذا تفعل)
            ['store.order.number_prefix', 'بادئة رقم الطلب', 'string', 'ORD-'],
            ['store.order.number_padding', 'عدد خانات تسلسل الطلب', 'number', '6'],
            ['store.transaction.reason_text', 'سبب معاملة الشراء', 'string', 'شراء: {item}'],
            ['store.checkout.success_text', 'نصّ نجاح الشراء', 'string', 'تمّ الشراء ✓ — طلبك رقم {number} وتلاقي شراءك في مكتبتك.'],
            ['store.insufficient_text', 'نصّ عدم كفاية الرصيد', 'string', 'رصيدك أقلّ من قيمة الطلب — اشحن محفظتك وكمّل من نفس المكان.'],
            ['store.owned_text', 'نصّ ما يملكه المستخدم', 'string', 'ده معاك بالفعل — تلاقيه في مكتبتك.'],
            ['store.unavailable_text', 'نصّ العنصر غير المتاح', 'string', 'العنصر ده مش متاح للشراء دلوقتي.'],
            ['store.disabled_text', 'نصّ إقفال المتجر', 'string', 'المتجر مقفول مؤقّتًا — جرّب بعد شويّة.'],
            ['store.topup.button_text', 'نصّ زرّ الشحن في الهيدر', 'string', 'شحن'],
            ['store.topup.sheet_button_text', 'نصّ زرّ الشحن داخل البوب-أب', 'string', 'اشحن المحفظة'],

            // ---------------- السلّة وصفحة مراجعة الطلب (17) — مسارٌ اختياريّ بجانب بوب-أب الشراء
            ['store.cart.enabled', 'تفعيل السلّة الاختياريّة', 'bool', '1'],
            ['store.cart.max_items', 'أقصى عدد عناصر في السلّة', 'number', '10'],
            ['store.cart.page_title', 'عنوان صفحة مراجعة الطلب', 'string', 'مراجعة الطلب'],
            ['store.cart.page_subtitle', 'سطر صفحة المراجعة', 'string', 'راجع طلبك وادفع — وكلّ الأرقام محسوبة عندنا.'],
            ['store.cart.order_title', 'اسم الطلب في المعاملة', 'string', 'طلب من السلّة'],
            ['store.cart.add_label', 'زرّ الإضافة للسلّة', 'string', 'ضيفه للسلّة'],
            ['store.cart.remove_label', 'زرّ الحذف من السلّة', 'string', 'شيله'],
            ['store.cart.submit_label', 'زرّ الدفع في المراجعة', 'string', 'ادفع وأكمل'],
            ['store.cart.added_text', 'نصّ الإضافة للسلّة', 'string', 'اتضاف للسلّة ✓'],
            ['store.cart.removed_text', 'نصّ الحذف من السلّة', 'string', 'اتشال من السلّة ✓'],
            ['store.cart.empty_text', 'نصّ السلّة الفارغة', 'string', 'سلّتك فاضية — ضيف حاجة الأوّل.'],
            ['store.cart.full_text', 'نصّ امتلاء السلّة', 'string', 'السلّة وصلت أقصى عدد — أكمل الطلب ده الأوّل.'],
            ['store.cart.bump_line_label', 'وسم سطر الـBump', 'string', 'إضافة للطلب'],
            ['store.cart.coupon_label', 'عنوان حقل الكوبون في المراجعة', 'string', 'كود خصم (اختياريّ)'],
            ['store.cart.subtotal_label', 'وسم المجموع', 'string', 'المجموع'],
            ['store.cart.discount_label', 'وسم الخصم', 'string', 'الخصم'],
            ['store.cart.total_label', 'وسم الإجماليّ', 'string', 'الإجماليّ'],
            ['store.cart.balance_before_label', 'وسم الرصيد قبل', 'string', 'رصيدك قبل'],
            ['store.cart.balance_after_label', 'وسم الرصيد بعد', 'string', 'رصيدك بعد'],

            // ---------------- ⭐ أقرب عرض يكفّيك داخل بوب-أب الشراء (19.5-ب-2)
            ['store.topup.suggest_offer', 'اقتراح أقرب عرض شحن', 'bool', '1'],
            ['store.topup.suggest_methods', 'طرق الشحن المقترَحة', 'json', '["manual","gateway"]'],
            ['store.topup.nearest_offer_text', 'نصّ أقرب عرض يكفّيك', 'text', 'ناقصك {needed} — أقرب عرض يكفّيك: ادفع {pay} وتاخد {credit} كوين (+{bonus}% إضافيّة).'],
            ['store.topup.largest_offer_text', 'نصّ أكبر عرض متاح', 'text', 'ناقصك {needed} — وأكبر عرض عندنا دلوقتي: ادفع {pay} وتاخد {credit} كوين، وتقدر تشحن أكتر من مرّة.'],

            // ---------------- سياسة عدم الاسترجاع (19.4)
            ['store.refund.policy_title', 'عنوان صفحة سياسة الاسترجاع', 'string', 'سياسة عدم الاسترجاع'],
            ['store.refund.ack_text', 'نصّ الإقرار قبل الدفع', 'string', 'قرأت سياسة عدم الاسترجاع وموافق عليها.'],
            ['store.refund.ack_required_text', 'نصّ رفض الشراء بلا إقرار', 'string', 'محتاجين إقرارك بسياسة عدم الاسترجاع الأوّل، وبعدها نكمّل الشراء.'],
            ['store.refund.link_text', 'نصّ رابط السياسة', 'string', 'اقرأ سياسة عدم الاسترجاع'],
            ['store.refund.alternative_text', 'نصّ البديل المعتمَد', 'string', 'البديل المعتمَد: رصيدك يفضل في محفظتك وتشتري بيه اللي تحبّه من الموقع.'],
        ];

        foreach ($rows as [$key, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'store',
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        // نصّ السياسة نفسه يبقى ملكًا للأدمن — نضعه فقط إن لم يكن موجودًا (19.4)
        Setting::firstOrCreate(
            ['key' => 'store.refund.policy_text'],
            [
                'group' => 'store',
                'label_ar' => 'نصّ سياسة الاسترجاع (HTML أو نصّ)',
                'type' => 'text',
                'default_value' => $this->policyHtml(),
                'value' => $this->policyHtml(),
            ],
        );

        Cache::forget('settings');
    }

    /** منتجات وباقة وكوبون بأسماء عربيّة واقعيّة */
    public function catalog(): void
    {
        $categories = collect([
            ['skills', 'مهارات عمليّة', 1],
            ['career', 'التوظيف والمسار المهنيّ', 2],
            ['self', 'تطوير الذات', 3],
        ])->mapWithKeys(fn ($row) => [$row[0] => ProductCategory::updateOrCreate(
            ['slug' => $row[0]],
            ['name_ar' => $row[1], 'sort_order' => $row[2], 'is_active' => true],
        )]);

        $products = [
            [
                'slug' => 'excel-formulas-cheatsheet',
                'category' => 'skills',
                'name_ar' => 'ملخّص معادلات إكسل للشغل اليوميّ',
                'description' => 'ملفّ PDF مختصر فيه أهمّ 60 معادلة تستعملها فعلًا في الشغل، بأمثلة جاهزة للنسخ.',
                'type' => 'digital',
                'teaser_pages' => 4,
                'price_coins' => 60,
                'offer_price_coins' => 45,
                'offer_ends_at' => now()->addDays(14),
            ],
            [
                'slug' => 'cv-template-modern',
                'category' => 'career',
                'name_ar' => 'قالب سيرة ذاتيّة عربيّ أنيق',
                'description' => 'قالب سيرة ذاتيّة بصفحة واحدة يقرأه نظام الفرز الآليّ بلا مشاكل، مع نسخة إنجليزيّة.',
                'type' => 'cv_template',
                'teaser_pages' => 1,
                'price_coins' => 80,
            ],
            [
                'slug' => 'interview-questions-guide',
                'category' => 'career',
                'name_ar' => 'دليل أسئلة المقابلات الشخصيّة',
                'description' => 'أشهر 45 سؤالًا في المقابلات، ومعها طريقة إجابة مبنيّة على مواقف حقيقيّة.',
                'type' => 'protected_pdf',
                'teaser_pages' => 6,
                'price_coins' => 120,
                'is_downloadable' => false,
            ],
            [
                'slug' => 'daily-planner-workbook',
                'category' => 'self',
                'name_ar' => 'كرّاسة تنظيم اليوم',
                'description' => 'كرّاسة عمليّة لتخطيط يومك وأسبوعك، بصفحات قابلة للطباعة.',
                'type' => 'digital',
                'teaser_pages' => 3,
                'price_coins' => 40,
            ],
        ];

        foreach ($products as $row) {
            $category = $categories[$row['category']];
            unset($row['category']);

            Product::updateOrCreate(['slug' => $row['slug']], [
                ...$row,
                'product_category_id' => $category->id,
                'status' => 'published',
                'is_indexable' => true,
                'is_downloadable' => $row['is_downloadable'] ?? true,
            ]);
        }

        $courses = [
            [
                'slug' => 'excel-for-work',
                'name_ar' => 'إكسل للشغل: من الصفر للاحتراف العمليّ',
                'description_ar' => 'تدريب عمليّ يمشي معك خطوة بخطوة في الجداول والمعادلات والتقارير — بأمثلة من شغل حقيقيّ.',
                'price_coins' => 450,
                'offer_price_coins' => 350,
                'offer_ends_at' => now()->addDays(10),
                'deadline_days' => 45,
                'free_preview_lessons' => 2,
            ],
            [
                'slug' => 'business-writing-ar',
                'name_ar' => 'الكتابة المهنيّة بالعربيّة',
                'description_ar' => 'تكتب إيميل ومذكّرة وتقريرًا يوصّل فكرتك من أوّل مرّة، بلغة مرتّبة ومحترمة.',
                'price_coins' => 300,
                'deadline_days' => 30,
                'free_preview_lessons' => 1,
            ],
        ];

        foreach ($courses as $row) {
            Course::updateOrCreate(['slug' => $row['slug']], [
                ...$row,
                'status' => 'published',
                'published_at' => now(),
                'is_indexable' => true,
                'is_free' => false,
                'xp_before_half' => 120,
                'xp_after_half' => 60,
            ]);
        }

        $path = LearningPath::updateOrCreate(['slug' => 'office-skills-path'], [
            'name_ar' => 'مسار مهارات المكتب',
            'description_ar' => 'مسار يجمع تدريبات الشغل المكتبيّ الأساسيّة بترتيب مريح.',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);

        // الباقة: سعر واحد مستقلّ، وقيمتها الإجماليّة حقيقيّة من أسعار عناصرها (18)
        $items = [
            Course::where('slug', 'excel-for-work')->first(),
            Course::where('slug', 'business-writing-ar')->first(),
            Product::where('slug', 'cv-template-modern')->first(),
            Product::where('slug', 'interview-questions-guide')->first(),
        ];

        $originalValue = collect($items)->filter()->sum(fn ($item) => (float) $item->price_coins);

        $bundle = Bundle::updateOrCreate(['slug' => 'job-ready-pack'], [
            'name_ar' => 'باقة الاستعداد للوظيفة',
            'description' => 'كلّ اللي تحتاجه قبل أوّل مقابلة: تدريبان أساسيّان وقالب سيرة ذاتيّة ودليل أسئلة المقابلات.',
            'price_coins' => 690,
            'original_value' => $originalValue,
            'status' => 'published',
        ]);

        BundleItem::where('bundle_id', $bundle->id)->delete();

        foreach (array_values(array_filter($items)) as $index => $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'itemable_type' => $item::class,
                'itemable_id' => $item->id,
                'sort_order' => $index + 1,
            ]);
        }

        Coupon::updateOrCreate(['code' => 'AHLAN15'], [
            'type' => 'percent',
            'value' => 15,
            'max_uses' => 200,
            'max_uses_per_user' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(3),
            'applies_to' => null,
            'is_active' => true,
        ]);

        $this->command?->info('المتجر التجريبيّ: '.Product::count().' منتج · '.Bundle::count().' باقة · مسار «'.$path->name_ar.'».');
    }

    private function policyHtml(): string
    {
        return '<p><strong>لا يوجد استرجاع نقديّ لأيّ مدفوعات</strong> — لا على شحن المحفظة، ولا على شراء تدريب أو مسار أو باقة أو منتج.</p>'
            .'<p>البديل المعتمَد: <strong>رصيدك يفضل في محفظتك</strong> وتشتري بيه اللي تحبّه من الموقع، فالقيمة ما بتضيعش.</p>'
            .'<p>لو حصل خطأ تقنيّ (خصم مكرّر أو عمليّة فاشلة) بنصحّح رصيدك بمعاملة موثّقة في سجلّ معاملاتك.</p>';
    }
}
