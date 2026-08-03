<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نصوص **شاشتَي التسجيل المفصولتين** (2.5-ب و2.5-ج) — كلّها إعدادات (2.13-أ).
 *
 * لماذا هجرة لا سيدر عرض؟ لأنّ إعدادات رحلة التسجيل موطنها الهجرات منذ
 * `2026_08_09_120030` بالحجّة نفسها: **الإنتاج يشغّل الهجرات ولا يشغّل بذور
 * العرض**. فمن يضع نصًّا جديدًا للرحلة يضعه حيث وُضِع أخوه.
 *
 * وما الذي استدعاها؟ فصلُ 2.5-ب عن 2.5-ج أنشأ **شاشةً كاملةً جديدة** (بيانات
 * الدخول) ونقل الـOTP إلى موضعه المنصوص تحت الإيميل — ومعهما تسميات وأزرار
 * ورسائل لم تكن موجودة. وكتابتها في القالب حرقٌ صريح لـ2.13-أ: «الحدّ الأدنى
 * الإلزاميّ … **النصوص الظاهرة للمستخدم**».
 *
 * ⚠️ وتاريخها **بعد آخر هجرة في الشجرة** — الهجرة المؤرَّخة قبل ما تعدّله
 *    تعمل مرّةً على تنصيبٍ قائم ثمّ يبطُل أثرها على تنصيبٍ جديد.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---------------- ب) صفحة التسجيل الأساسية (2.5-ب)
        ['onboarding.account.title', 'onboarding', 'عنوان شاشة بيانات الدخول', 'string', 'إنشاء حساب — بياناتك الأساسيّة'],
        ['onboarding.account.step_label', 'onboarding', 'سطر رقم الخطوة (شاشة الدخول)', 'string', 'خطوة 1 من 2 — بيانات الدخول'],
        ['onboarding.account.subtitle', 'onboarding', 'سطر تعريفيّ تحت العنوان', 'text', 'التسجيل والتفعيل مجّانيّان بالكامل. هنأكّد بريدك دلوقتي، وبيانات الشهادة في الخطوة اللي بعدها.'],
        ['onboarding.account.email_label', 'onboarding', 'تسمية حقل البريد', 'string', 'البريد الإلكترونيّ'],
        ['onboarding.account.email_invalid', 'onboarding', 'خطأ: صيغة البريد', 'string', 'الشكل ده مش بريد صالح — راجع الكتابة.'],
        ['onboarding.account.email_taken', 'onboarding', 'خطأ: البريد مستعمَل', 'string', 'البريد ده مستعمَل قبل كده — ادخل بيه أو استرجع كلمة السرّ.'],
        ['onboarding.account.email_ok', 'onboarding', 'رسالة: البريد متاح', 'string', 'البريد متاح ✓'],
        ['onboarding.account.phone_label', 'onboarding', 'تسمية حقل الموبايل', 'string', 'رقم الموبايل'],
        ['onboarding.account.phone_hint', 'onboarding', 'سطر تحت حقل الموبايل', 'string', 'الرقم للتواصل بس — مش هنبعتلك عليه كود تحقّق.'],
        ['onboarding.account.dial_label', 'onboarding', 'تسمية قائمة أكواد الدول', 'string', 'كود الدولة'],
        ['onboarding.account.dial_search', 'onboarding', 'حقل البحث في أكواد الدول', 'string', 'دوّر بالاسم أو الكود…'],
        ['onboarding.account.password_label', 'onboarding', 'تسمية كلمة السرّ', 'string', 'كلمة السرّ'],
        ['onboarding.account.password_confirm_label', 'onboarding', 'تسمية تأكيد كلمة السرّ', 'string', 'تأكيد كلمة السرّ'],
        ['onboarding.account.next_label', 'onboarding', 'زرّ الانتقال للشاشة الثانية', 'string', 'كمّل ⟵ بيانات الشهادة'],
        ['onboarding.account.has_account', 'onboarding', 'سؤال «عندك حساب؟»', 'string', 'عندك حساب؟'],
        ['onboarding.account.login_label', 'onboarding', 'رابط الدخول', 'string', 'ادخل من هنا'],
        ['onboarding.account.loading_label', 'onboarding', 'نصّ الانتظار في القوائم', 'string', 'لحظة…'],

        // ---------------- ب) الـOTP في موضعه: تحت الإيميل (2.5-ب)
        ['auth.otp.inline_hint', 'security', 'سطر شرح الرمز تحت الإيميل', 'text', 'هنبعت رمز من {length} أرقام على بريدك — اكتبه هنا عشان نتأكّد إنّه بريدك فعلًا.'],
        ['auth.otp.verified_text', 'security', 'رسالة بعد تأكيد البريد', 'string', 'بريدك اتأكّد — كمّل باقي البيانات.'],
        ['auth.otp.error_not_verified', 'security', 'خطأ: محاولة تخطّي التأكيد', 'text', 'أكّد بريدك الأوّل: اضغط «إرسال» واكتب الرمز اللي هيوصلك، وبعدها كمّل.'],
        ['auth.otp.error_step_lost', 'security', 'خطأ: ضاعت الخطوة الأولى', 'text', 'الجلسة رجعت لأوّل خطوة — اكتب بريدك وأكّده تاني وهنكمّل من هناك.'],

        // ---------------- ج) صفحة المعلومات (2.5-ج)
        ['onboarding.identity.title', 'onboarding', 'عنوان شاشة بيانات الشهادة', 'string', 'بيانات الشهادات والإفادات'],
        ['onboarding.identity.step_label', 'onboarding', 'سطر رقم الخطوة (شاشة الشهادة)', 'string', 'خطوة 2 من 2 — بيانات الشهادة'],
        ['onboarding.identity.subtitle', 'onboarding', 'سطر تعريفيّ تحت العنوان', 'text', 'البيانات دي هي اللي بتطلع على شهاداتك وإفاداتك — اكتبها زيّ ما تحبّ تشوفها عليها.'],
        ['onboarding.identity.title_label', 'onboarding', 'تسمية حقل اللقب', 'string', 'اللقب'],
        ['onboarding.identity.title_placeholder', 'onboarding', 'الخيار الفارغ في اللقب', 'string', 'اختر اللقب'],
        ['onboarding.identity.name_ar_label', 'onboarding', 'تسمية الاسم بالعربيّ', 'string', 'الاسم بالعربيّ (ثلاثيّ)'],
        ['onboarding.identity.name_en_label', 'onboarding', 'تسمية الاسم بالإنجليزيّ', 'string', 'الاسم بالإنجليزيّ (ثلاثيّ)'],
        ['onboarding.identity.name_words_error', 'onboarding', 'خطأ عدد كلمات الاسم ({words})', 'string', 'الاسم لازم يكون {words} كلمات على الأقلّ.'],
        ['onboarding.identity.gender_label', 'onboarding', 'تسمية النوع', 'string', 'النوع'],
        ['onboarding.identity.gender_male', 'onboarding', 'خيار «ذكر»', 'string', 'ذكر'],
        ['onboarding.identity.gender_female', 'onboarding', 'خيار «أنثى»', 'string', 'أنثى'],
        ['onboarding.identity.country_label', 'onboarding', 'تسمية الدولة', 'string', 'الدولة'],
        ['onboarding.identity.country_placeholder', 'onboarding', 'الخيار الفارغ في الدولة', 'string', 'اختر الدولة'],
        ['onboarding.identity.governorate_label', 'onboarding', 'تسمية المحافظة', 'string', 'المحافظة'],
        ['onboarding.identity.governorate_placeholder', 'onboarding', 'الخيار الفارغ في المحافظة', 'string', 'اختر المحافظة'],
        ['onboarding.identity.governorate_blocked', 'onboarding', 'نصّ المحافظة قبل اختيار الدولة', 'string', 'اختر الدولة الأوّل'],
        ['onboarding.identity.governorate_failed', 'onboarding', 'خطأ تحميل المحافظات', 'string', 'تعذّر تحميل المحافظات — جرّب تاني'],
        ['onboarding.identity.submit_label', 'onboarding', 'زرّ الاستكمال', 'string', 'استكمال التسجيل'],
        ['onboarding.identity.back_question', 'onboarding', 'سؤال الرجوع للخطوة الأولى', 'string', 'عايز تعدّل بريدك أو رقمك؟'],
        ['onboarding.identity.back_label', 'onboarding', 'رابط الرجوع للخطوة الأولى', 'string', 'ارجع للخطوة الأولى'],
        ['onboarding.identity.load_governorates_label', 'onboarding', 'زرّ بناء المحافظات بلا جافاسكربت', 'string', 'أظهر محافظات الدولة المختارة'],

        // ⚠️ ومفاتيح `countries.registration.*` والإسناد موطنها
        // `AdminSystemDemoSeeder::settings()` مع أخواتها في مجموعة «countries»،
        // و`celebrations.hold_ms` مع أخواتها في `ExamDemoSeeder::settings()` —
        // فلا يُعرَّف مفتاحٌ في موضعين ويختلف افتراضيّاه بمرور الوقت.
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$key, $group, $label, $type, $default]) {
            // `insertOrIgnore` عمدًا: قيمةٌ عدّلها المالك لا تُدهَس (2.13-د)
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }
};
