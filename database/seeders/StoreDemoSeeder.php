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

            // ---------------- ⭐ لاندنج بيدج الباقة (18 · القسم 22)
            ['store.bundle.hero_badge', 'وسم الباقة في الهيرو', 'string', 'باقة متكاملة'],
            ['store.bundle.cta_label', 'نصّ زرّ شراء الباقة', 'string', 'احصل على الباقة كاملة'],
            ['store.bundle.price_label', 'تسمية سعر الباقة', 'string', 'سعر الباقة'],
            ['store.bundle.savings_label', 'تسمية التوفير في ميزان القيمة', 'string', 'اللي بتوفّره'],
            ['store.bundle.items_count_text', 'سطر عدد عناصر الباقة', 'string', '{count} عناصر بتتفتح كلّها في مكتبتك فور الشراء'],
            ['store.bundle.access_text', 'سطر دوام الوصول', 'string', 'اللي تشتريه بيفضل معاك في مكتبتك — من غير تجديد ولا اشتراك.'],
            ['store.bundle.owned_text', 'نصّ الباقة المملوكة', 'string', 'الباقة دي معاك بالفعل — كلّ عناصرها مفتوحة في مكتبتك.'],
            // ⭐ سطرٌ يمنع الـDark Pattern بنصّه: القيمة **محسوبة** لا مكتوبة (2.9 · 18)
            ['store.bundle.honest_note', 'سطر شفافيّة القيمة الإجماليّة', 'string', 'القيمة الإجماليّة تحت محسوبة من أسعار العناصر نفسها دلوقتي — مش رقمًا مكتوبًا باليد.'],
            ['store.bundle.closing_title', 'عنوان بلوك الإغلاق', 'string', 'جاهز تبدأ؟'],
            ['store.bundle.faq_title', 'عنوان أسئلة ما قبل الشراء', 'string', 'أسئلة قبل ما تشتري'],
            ['store.bundle.faq', 'أسئلة ما قبل الشراء للباقة', 'json', json_encode([
                ['q' => 'العناصر دي بتتفتح إمتى؟', 'a' => 'كلّها بتتفتح في مكتبتك فور تأكيد الشراء — مافيش انتظار ولا تفعيل يدويّ.'],
                ['q' => 'لو اشتريت عنصرًا منها قبل كده؟', 'a' => 'اللي معاك بيفضل معاك، والباقة بتفتح باقي العناصر — وماتقدرش تشتري نفس الباقة مرّتين.'],
                ['q' => 'في استرجاع؟', 'a' => 'مافيش استرجاع نقديّ، ورصيدك بيفضل في محفظتك تشتري بيه اللي تحبّه من الموقع.'],
            ], JSON_UNESCAPED_UNICODE)],

            /*
            |----------------------------------------------------------------
            | ⭐ بلوكات اللاندنج الكاملة (18 · القسم 22 — بأمر المالك)
            |----------------------------------------------------------------
            | كلّ عنوانٍ وكلّ سطرٍ هنا **إعداد** لا نصّ محروق (2.13)، وكلّ بلوك
            | له **Toggle** يخفيه — و«المحظور يُخفى لا يُعطَّل» (2.15-أ-7).
            */

            // الهيرو وصفّ الحقائق — حقائق تُقرأ من القاعدة لا وعودًا (2.9)
            ['store.bundle.fact_items', 'حقيقة عدد العناصر', 'string', '{count} عناصر في الباقة'],
            ['store.bundle.fact_lessons', 'حقيقة عدد الدروس', 'string', '{count} درسًا مسجّلًا'],
            ['store.bundle.fact_lifetime', 'حقيقة دوام الوصول', 'string', 'وصول دائم — من غير اشتراك ولا تجديد'],

            // «مناسبة لـ / مش مناسبة لـ» — التأهيل قبل البيع، وواجبٌ لأنّ لا استرجاع (19.4)
            ['store.bundle.blocks.fit_enabled', 'إظهار بلوك «مناسبة لـ / مش مناسبة لـ»', 'bool', '1'],
            ['store.bundle.fit_title', 'عنوان «الباقة دي مناسبة لـ»', 'string', 'الباقة دي مناسبة لـ'],
            ['store.bundle.not_fit_title', 'عنوان «مش مناسبة لـ»', 'string', 'ومش مناسبة لـ'],
            ['store.bundle.not_fit_note', 'سطر أمانة التأهيل', 'string', 'بنقولها بصراحة قبل ما تدفع — لأنّ مافيش استرجاع نقديّ بعد الشراء.'],

            // «بعد الباقة هتقدر…» — نتائج وأفعال لا مزايا
            ['store.bundle.blocks.outcomes_enabled', 'إظهار بلوك النتائج', 'bool', '1'],
            ['store.bundle.outcomes_title', 'عنوان بلوك النتائج', 'string', 'بعد الباقة هتقدر…'],

            // «اللي جواها» — العناصر بقيمها والبونص بقالبه المنصوص (18)
            ['store.bundle.blocks.includes_enabled', 'إظهار بلوك محتوى الباقة', 'bool', '1'],
            ['store.bundle.includes_title', 'عنوان بلوك محتوى الباقة', 'string', 'اللي جوّه الباقة'],
            ['store.bundle.item_type_labels', 'تسميات أنواع عناصر الباقة', 'json', json_encode([
                'course' => 'تدريب', 'path' => 'مسار', 'product' => 'منتج',
            ], JSON_UNESCAPED_UNICODE)],

            // ⭐ الشهادة **بشرطها** (8) — ولا وعد بشهادةٍ بلا امتحانٍ يجتازه
            ['store.bundle.blocks.certificate_enabled', 'إظهار بلوك الشهادة', 'bool', '1'],
            ['store.bundle.certificate_title', 'عنوان بلوك الشهادة', 'string', 'شهادة معتمدة — بشرطها'],
            ['store.bundle.certificate_text', 'نصّ شرط الشهادة', 'text', 'التدريبات دي بتصدّر شهادة معتمدة، والشهادة **مش** بتيجي بمجرّد الشراء: بتتصدر بعد ما تجتاز الامتحان النهائيّ للتدريب بدرجة {score}% على الأقلّ.'],

            // ميزان القيمة (18)
            ['store.bundle.blocks.ledger_enabled', 'إظهار ميزان القيمة', 'bool', '1'],
            ['store.bundle.ledger_title', 'عنوان ميزان القيمة', 'string', 'ميزان القيمة'],

            /*
            | ⭐ الإتاحة الحقيقيّة — والعدّاد لا يُطبَع في الـHTML أصلًا ما لم يكن
            | للبندل تاريخ نهاية فعليّ، و«باقي N مقعدًا» رقمٌ **معدود** من الطلبات
            | المدفوعة. «لا عدّادات وهميّة ولا ندرة مزيّفة» (21.1-د · 2.9).
            */
            ['store.bundle.blocks.availability_enabled', 'إظهار بلوك الإتاحة الحقيقيّة', 'bool', '1'],
            ['store.bundle.countdown_title', 'عنوان نافذة الإتاحة', 'string', 'نافذة الإتاحة'],
            ['store.bundle.countdown_text', 'سطر نهاية الإتاحة', 'string', 'العرض ده بينتهي في {date} — والتاريخ ده مضبوط من لوحة الإدارة، مش عدّادًا بيبدأ من أوّل لكلّ زائر.'],
            ['store.bundle.countdown_units', 'وحدات العدّاد', 'json', json_encode([
                'days' => 'يوم', 'hours' => 'ساعة', 'minutes' => 'دقيقة', 'seconds' => 'ثانية',
            ], JSON_UNESCAPED_UNICODE)],
            ['store.bundle.seats_text', 'سطر المقاعد المتبقّية', 'string', 'باقي {left} مقعدًا من {limit} — الرقم ده متحسب من الطلبات المدفوعة فعلًا.'],
            ['store.bundle.sold_out_text', 'نصّ نفاد المقاعد', 'string', 'المقاعد خلصت — الباقة دي مابقتش متاحة للشراء دلوقتي.'],
            ['store.bundle.window_closed_text', 'نصّ انتهاء نافذة الإتاحة', 'string', 'نافذة الباقة دي قفلت — استنّى الفتحة الجاية.'],
            ['store.bundle.not_started_text', 'نصّ ما قبل بداية الإتاحة', 'string', 'الباقة دي لسّه ماتفتحتش للشراء.'],

            // الإغلاق — ماذا يحدث بعد الشراء + رابط سياسة عدم الاسترجاع **قبل** الزرّ (19.4)
            ['store.bundle.after_purchase_title', 'عنوان «بعد الشراء»', 'string', 'اللي بيحصل بعد الشراء'],
            ['store.bundle.after_purchase_text', 'نصّ «بعد الشراء»', 'text', 'أوّل ما تأكّد الشراء بتتخصم قيمة الباقة من محفظتك، وكلّ عناصرها بتتفتح على طول في «مكتبتي» — مافيش انتظار ولا تفعيل يدويّ.'],
            ['store.bundle.no_refund_notice', 'تنبيه عدم الاسترجاع قبل الزرّ', 'string', 'مافيش استرجاع نقديّ لأيّ مدفوعات — ورصيدك بيفضل في محفظتك تشتري بيه اللي تحبّه.'],

            // الشريط اللاصق على الموبايل — نداءٌ واحد لا ينافسه ثانٍ (2.15-أ-2)
            ['store.bundle.sticky_price_label', 'وسم السعر في الشريط اللاصق', 'string', 'سعر الباقة'],

            /*
            |----------------------------------------------------------------
            | بلوك إعدادات شاشة البندلز (24 — نصّ البلوك حرفيًّا)
            |----------------------------------------------------------------
            */
            ['store.bundles.enabled', 'تفعيل البندلز', 'bool', '1'],
            ['store.bundle.anchoring_enabled', 'Toggle Anchoring (شطب السعر الطبيعيّ)', 'bool', '1'],
            ['store.bundle.total_value_enabled', 'Toggle إظهار القيمة الإجماليّة', 'bool', '1'],
            ['store.bundle.bonus_text_en', 'قالب نصّ البونص (إنجليزيّ)', 'string', '🎁 Bonus: {item} worth {amount} — free with the bundle'],

            // ⭐ قاعدتان **مقفولتان** تُعرَضان ملاحظتين لا مفتاحين (18 · 24)
            ['store.bundle.rule_contextual_title', 'عنوان قاعدة السعر السياقيّ', 'string', 'قاعدة السعر السياقيّ 🔒'],
            ['store.bundle.rule_contextual_text', 'نصّ قاعدة السعر السياقيّ', 'text', 'السعر الطبيعيّ بيظهر في كلّ صفحات الموقع، والاستثناء الوحيد هو صفحة البندل نفسها. فالـOverride محصور في صفحة البندل وحدها — قاعدة مقفولة مش توجّل.'],
            ['store.bundle.rule_no_gift_title', 'عنوان قاعدة «لا نوع هديّة»', 'string', 'مافيش نوع «هديّة» 🔒'],
            ['store.bundle.rule_no_gift_text', 'نصّ قاعدة «لا نوع هديّة»', 'text', 'أيّ محتوى مضمَّن في البندل بيتفتح دايمًا بحكم الشراء — و«هديّة» مصطلح تسويقيّ بس، مش نوع عنصر في النظام.'],

            /*
            |----------------------------------------------------------------
            | شاشة البندلز في الإدارة (24) — الهيدر والفلاتر والفورم والحالات
            |----------------------------------------------------------------
            */
            ['store.admin.bundles.per_page', 'عدد صفوف جدول البندلز', 'number', '20'],
            ['store.admin.bundles.empty_text', 'نصّ الحالة الفارغة للبندلز', 'string', 'لا بندلز — اجمع عناصرك في عرض واحد'],
            ['store.admin.bundles.error_text', 'نصّ حالة الخطأ في شاشة البندلز', 'string', 'مانقدرناش نحمّل البندلز دلوقتي.'],
            ['store.admin.bundles.pricing_locked_text', 'نصّ قفل التسعير لغير المالك', 'string', 'حقول التسعير لمالك المنصّة وحده — مخفيّة هنا، وأيّ محاولة إرسالها بتترفض على الخادم.'],
            ['store.admin.bundles.duplicate_suffix', 'لاحقة اسم البندل المكرَّر', 'string', '— نسخة'],

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
                'xp_max' => 120,
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
