<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **لاندنج بيدج البندل** (18 — البند الذي كان مؤجَّلًا في القسم 22، وأمر المالك ببنائه).
 *
 * الصفحة كانت تُرسَم من ثلاثة حقول (`name_ar` · `description` · `cover_path`)،
 * فكلّ ما تطلبه 24 في فورم البندل — **[الهويّة] [العناصر] [التسعير 🔒] [العرض]
 * [الإتاحة]** — لم يكن له مكانٌ يُحفَظ فيه. وهذه الهجرة تفتح ذلك المكان، **ولا
 * عمود هنا بلا حقلٍ يملؤه في الفورم وبلا موضعٍ يقرؤه في الصفحة** — فالعمود
 * اليتيم عطبٌ متكرّر في هذا المستودع.
 *
 * وثلاثة أعمدةٍ **لم** تُضَف عمدًا، ولكلٍّ سببه المنصوص:
 *
 *  - **«القيمة الإجماليّة»** — 24 يصفها «(محسوبة تلقائيًّا، **للقراءة**)»، و18
 *    «القيمة الإجماليّة **محسوبةً تلقائيًّا**». فمصدرها `PricingService::bundleItemsValue()`
 *    وحده، و`bundles.original_value` القائم يبقى **مرآةً تُكتَب من الخادم** لا
 *    حقلًا في فورمٍ — وأيّ قيمةٍ تصل في الطلب تُهمَل.
 *  - **«نسبة الخصم المعروضة»** — تُشتقّ من الرقمين السابقين، وكتابتها بيدٍ تخالف
 *    «لا أرقام وهميّة» (2.9 · 21.1-د).
 *  - **«عدد المشترين»** — يُعَدّ من `order_items` الفعليّة، فرقمٌ مخزَّن قد يفترق
 *    عن الحقيقة، و«باقي N مقعدًا» المبنيّ عليه يصير ندرةً كاذبة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            // ---------------------------------------------------- [الهويّة] (24)
            // «الاسم والوصف (ع/إ)» — والجدول في 24 يعرض عمود «الاسم (ع/إ)»
            $table->string('name_en')->nullable()->after('name_ar');
            $table->text('description_en')->nullable()->after('description');

            /*
             | العنوان يبيع **النتيجة** لا الاسم: اسم البندل هويّةٌ إداريّة، و`headline`
             | وعدٌ للزائر. وكلاهما اختياريّ — وإن تُرِكا فالصفحة ترجع للاسم والوصف
             | بلا فراغ (2.15-أ: البساطة أوّلًا، ولا شاشة نصفها فارغ).
             */
            $table->string('landing_headline')->nullable()->after('name_en');
            $table->text('landing_promise')->nullable()->after('landing_headline');

            /*
             | قوائم اللاندنج — كلّها يحرّرها الأدمن لكلّ بندل على حدة:
             |  - `landing_outcomes`: «بعد الباقة هتقدر…» (أفعال لا مزايا).
             |  - `landing_fit_for` / `landing_not_fit_for`: **التأهيل قبل البيع**.
             |    وهذا ليس زينة: 19.4 يمنع الاسترجاع نهائيًّا، فبيع الباقة لمن لا
             |    تناسبه ضررٌ **لا يُصلَح** — فالتصريح بمن لا تناسبه واجبٌ أخلاقيّ
             |    لا خيار تسويقيّ (2.9 «نصمّم لمصلحة المتدرب مش ضده»).
             |  - `landing_faq`: أسئلة هذا البندل، وللإعدادات افتراضيٌّ عامّ.
             */
            $table->json('landing_outcomes')->nullable()->after('landing_promise');
            $table->json('landing_fit_for')->nullable()->after('landing_outcomes');
            $table->json('landing_not_fit_for')->nullable()->after('landing_fit_for');
            $table->json('landing_faq')->nullable()->after('landing_not_fit_for');

            // ---------------------------------------------------- [العرض] (24)
            // «Toggle شطب السعر الطبيعيّ (Anchoring)» · «Toggle إظهار القيمة الإجماليّة»
            $table->boolean('show_anchor_strikethrough')->default(true)->after('original_value');
            $table->boolean('show_total_value')->default(true)->after('show_anchor_strikethrough');
            // «قالب نصّ البونص» — لكلّ بندل، وفارغه يرث قالب الإعدادات العامّ
            $table->string('bonus_text_template')->nullable()->after('show_total_value');

            // ---------------------------------------------------- [الإتاحة] (24)
            /*
             | ⭐ **الندرة مسموحةٌ إن كانت حقيقيّة وممنوعةٌ إن كانت مصطنعة.**
             | 2.9-10 يعتمد «ندرة حقيقية: إبراز نوافذ الإتاحة/الوقت اليومي كعدّادات
             | **صادقة**»، و21.1-د يمنع «عدّادات وهميّة وندرة مزيّفة». فالعدّاد على
             | اللاندنج **لا يوجد في الـHTML أصلًا** ما لم يكن لهذا العمود تاريخ،
             | و«باقي N مقعدًا» لا يظهر ما لم يكن لهذا الحدّ رقم — والرقم يُعَدّ من
             | الطلبات المدفوعة لا يُكتَب.
             */
            $table->timestamp('available_from')->nullable()->after('bonus_text_template');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->unsignedInteger('purchase_limit')->nullable()->after('available_until');

            // ---------------------------------------------------- مطالب الميديا باير (21.1-أ · 21.2-ب)
            // صورة OG لكلّ بندل — «صورة OG تلقائيّة لكلّ رابط» (21.1-أ)
            $table->string('og_image_path')->nullable()->after('cover_path');
            /*
             | علَم فهرسة البندل نفسه: `StoreController::indexable()` كان يقرأ
             | `$item->is_indexable ?? true` — والبندل بلا العمود، فالإعداد العامّ
             | وحده يحكم ولا يملك الأدمن استثناء بندلٍ بعينه (21.1-هـ).
             */
            $table->boolean('is_indexable')->default(true)->after('status');
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            /*
             | ⭐ **Toggle «اعرضه كبونص»** (24 — قسم [العناصر] حرفيًّا).
             |
             | وبلا هذا العمود كان القالب يطبع سطر البونص لكلّ عنصرٍ له سعر، فيقرأ
             | الزائر «🎁 بونص» أربع مرّات في باقةٍ من أربعة عناصر — والبونص الذي
             | يشمل كلّ شيء لا يعني شيئًا، وهو **قيمة مُدرَكة منفوخة** أي عين ما
             | يمنعه 2.9. فصار البونص **قرارًا يتّخذه الأدمن لعنصرٍ بعينه**.
             */
            $table->boolean('is_bonus')->default(false)->after('price_coins');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn([
                'name_en', 'description_en', 'landing_headline', 'landing_promise',
                'landing_outcomes', 'landing_fit_for', 'landing_not_fit_for', 'landing_faq',
                'show_anchor_strikethrough', 'show_total_value', 'bonus_text_template',
                'available_from', 'available_until', 'purchase_limit',
                'og_image_path', 'is_indexable',
            ]);
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
};
