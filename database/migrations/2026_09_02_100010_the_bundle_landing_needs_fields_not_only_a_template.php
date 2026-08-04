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
 * ⭐⭐ **ولماذا `landing_texts` خريطةٌ واحدة لا ثلاثين عمودًا؟**
 * لأنّ أمر المالك: «خلّي أيّ نصوص وأيّ سكشن في صفحة البندل قابل للتعديل من
 * إعدادات نفس البندل» — وثلاثون نصًّا × عمود = هجرةٌ جديدة مع كلّ سطرٍ يُضاف.
 * فالخريطة تقبل مفتاحًا جديدًا **بلا هجرة**، والمفتاح فيها **عين مفتاح الإعداد
 * العامّ** فالوراثة تُقرأ بلا جدول ترجمة.
 *
 * ⚠️ **والفخّ الذي تتجنّبه هذه البنية عمدًا:** لا تُنسَخ القيمة العامّة إلى صفّ
 * البندل عند الإنشاء أبدًا. لو نُسِخت لصار كلّ بندلٍ **لقطةً مجمّدة**، وتعديلُ
 * المالك للنصّ العامّ لاحقًا **لا يصل أحدًا** — أي إعدادٌ بلا أثر، وهو نقضٌ
 * لـ2.13 من داخلها. فالمفتاح الغائب = **وراثة**، والقيمة تُحسَب لحظةَ العرض.
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
             | ⭐ **override نصوص اللاندنج لهذا البندل** — خريطة `مفتاح ⟵ نصّ`
             | بنفس مفاتيح `BundleLanding::TEXTS`. المفتاح الغائب أو الفارغ =
             | **وراثةٌ حيّة** من الإعداد العامّ، لا لقطةٌ مجمّدة.
             */
            $table->json('landing_texts')->nullable()->after('name_en');

            /*
             | ⭐ **حالة كلّ سكشن لهذا البندل** — خريطة `سكشن ⟵ حالة` بثلاث حالات:
             | `inherit` (أو الغياب) · `show` · `hide`. وتوجّلٌ ثنائيّ **لا يكفي**:
             | به يستحيل التمييز بين «أخفِه لهذا البندل» و«اتبع الإعداد العامّ»،
             | فيتجمّد البندل على قيمة اليوم ويُبطِل الإعداد العامّ من حيث لا يُرى.
             */
            $table->json('landing_sections')->nullable()->after('landing_texts');

            /*
             | قوائم اللاندنج — محتوًى خاصّ بهذا البندل لا override لنصّ عامّ:
             |  - `landing_outcomes`: «بعد الباقة هتقدر…» (أفعال لا مزايا).
             |  - `landing_fit_for` / `landing_not_fit_for`: **التأهيل قبل البيع**.
             |    وهذا ليس زينة: 19.4 يمنع الاسترجاع نهائيًّا، فبيع الباقة لمن لا
             |    تناسبه ضررٌ **لا يُصلَح** — فالتصريح بمن لا تناسبه واجبٌ أخلاقيّ
             |    لا خيار تسويقيّ (2.9 «نصمّم لمصلحة المتدرب مش ضده»).
             |  - `landing_faq`: أسئلة هذا البندل، وفارغُها يرث الافتراضيّ العامّ.
             */
            $table->json('landing_outcomes')->nullable()->after('landing_sections');
            $table->json('landing_fit_for')->nullable()->after('landing_outcomes');
            $table->json('landing_not_fit_for')->nullable()->after('landing_fit_for');
            $table->json('landing_faq')->nullable()->after('landing_not_fit_for');

            /*
             |------------------------------------------------------------------
             | ⭐ [كود مخصّص] — القسم السادس في الفورم (أمر المالك)
             |------------------------------------------------------------------
             | كودٌ حرّ يُحقَن في `<head>` وقبل `</body>` مباشرةً، **بلا تعقيم**
             | ولا تصفية — «مسموح أضيف فيهم أي حاجة» بنصّ المالك.
             |
             | 🔒 **ولذلك بيد مالك المنصّة وحده**: جافاسكربت في `<head>` يملك جلسة
             |    كلّ من يفتح الصفحة — بما فيها جلسة المالك. فمنحه لمسؤول التسويق
             |    (12.2.3-6) يمنحه المنصّة كلّها من بابٍ خلفيّ ويُبطِل عزل الماليّات
             |    (12.7) وكلّ سقفٍ في مصفوفة 12.2.2.
             |
             | و`*_when` **خانة الموافقة الإلزاميّة**: `always` · `analytics` · `ads`.
             |    والافتراضيّ **الأضيق** (`ads`) لأنّ أغلب ما يوضَع هنا بكسلاتُ تتبّع،
             |    وحقنُها بلا شرطٍ يكسر بصمتٍ ضمانًا **قائمًا ومقيسًا** للمستخدم
             |    (21.3-د · 2.9) ويجعل بانر الموافقة يَعِد بما لا يقع.
             */
            $table->text('landing_head_code')->nullable()->after('landing_faq');
            $table->string('landing_head_code_when', 16)->default('ads')->after('landing_head_code');
            $table->text('landing_body_end_code')->nullable()->after('landing_head_code_when');
            $table->string('landing_body_end_code_when', 16)->default('ads')->after('landing_body_end_code');

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
                'name_en', 'description_en', 'landing_texts', 'landing_sections',
                'landing_outcomes', 'landing_fit_for', 'landing_not_fit_for', 'landing_faq',
                'landing_head_code', 'landing_head_code_when',
                'landing_body_end_code', 'landing_body_end_code_when',
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
