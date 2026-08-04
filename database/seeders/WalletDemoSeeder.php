<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\TransferMethod;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات المحفظة التجريبيّة (19 · 19.5): إعدادات المجال · طرق التحويل ·
 * عروض الشحن للطريقتين · حركات على محفظة مستخدم تجريبيّ.
 */
class WalletDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->screenTextSettings();
        $this->financeSettings();
        $this->transferMethods();
        $this->offers();
        $this->demoWallet();
    }

    /**
     * 🔒 أسعار الصرف ورسوم العمليّات الثلاث (19.1 · 19.3).
     *
     * `firstOrCreate` لا `updateOrCreate`: هذه إعدادات ماليّة قد يكون مالك المنصّة
     * عدّلها فعلًا، فلا يجوز أن يعيدها سيدر تجريبيّ إلى الافتراضيّ بلا قرارٍ منه.
     */
    public function financeSettings(): void
    {
        $rows = [
            ['finance.rates.usd_to_coins', '1$ = كام كوين', 'number', '50'],
            ['finance.rates.ticket_to_coins', '1 تذكرة = كام كوين', 'number', '10'],
            ['finance.rates.ticket_to_xp', '1 تذكرة = كام XP', 'number', '300'],
            ['finance.transfer.coins_fee_percent', 'رسوم حوالة الكوينز (%)', 'number', '15'],
            // مرتفعة عمدًا لتثبيط تبادل الـXP حفاظًا على نزاهة الليدر بورد (19.3)
            ['finance.transfer.xp_fee_percent', 'رسوم حوالة الـXP (%)', 'number', '85'],
            ['finance.transfer.tickets_fee_percent', 'رسوم حوالة التذاكر (%)', 'number', '0'],
            ['finance.transfer.min_amount', 'أقلّ قيمة حوالة', 'number', '10'],
            ['finance.transfer.rounding', 'تقريب صافي الحوالة', 'string', 'ceil'],
            ['finance.exchange.fee_percent', 'رسوم تحويل العملة (%)', 'number', '5'],
            ['finance.exchange.min_amount_usd', 'أقلّ قيمة تحويل (بالدولار)', 'number', '1'],
            ['finance.withdraw.fee_percent', 'رسوم السحب (%)', 'number', '1'],
            ['finance.withdraw.min_fee_usd', 'أدنى رسوم سحب بالدولار', 'number', '0.5'],
            ['finance.withdraw.min_amount_usd', 'أقلّ قيمة سحب بالدولار', 'number', '5'],
            ['finance.referral.commission_percent', 'عمولة الريفيرال (%)', 'number', '7'],
        ];

        foreach ($rows as [$key, $label, $type, $default]) {
            Setting::firstOrCreate(['key' => $key], [
                'group' => 'finance',
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
                'is_owner_only' => true,
                'is_sensitive' => true,
            ]);
        }

        // سطر السياسة في الفاتورة نصٌّ يحرّره **الأدمن** لا مالك المنصّة وحده (19.4)
        Setting::firstOrCreate(['key' => 'library.invoice.refund_note'], [
            'group' => 'store',
            'label_ar' => 'سطر سياسة عدم الاسترجاع في الفاتورة',
            'type' => 'text',
            'default_value' => 'لا يوجد استرجاع نقديّ — ورصيدك يفضل في محفظتك تشتري بيه اللي انت عايزه من الموقع.',
            'value' => 'لا يوجد استرجاع نقديّ — ورصيدك يفضل في محفظتك تشتري بيه اللي انت عايزه من الموقع.',
        ]);

        Cache::forget('settings');
    }

    /**
     * **نصوص شاشات المحفظة** (2.13-أ: «النصوص الظاهرة للمستخدم») — كلّ جملةٍ
     * يقرؤها المستخدم على `resources/views/wallet/**` لها مفتاحها هنا،
     * والوحدة **جملةٌ كاملة** كما تُقرَأ لا كلمةً مقتطعة. و`:amount` وأخواتها
     * مواضع استبدال لا نصًّا.
     *
     * ⚠️ «المحفظة» و«رصيدي» و«المعاملات» و«المسحوبات» أسماءٌ **منصوصة حرفيًّا
     * في الدستور** (19.2 · 24.5 · 12.0)، فافتراضيُّها هو النصّ المنصوص
     * وتغييرُه من اللوحة يخالف الخريطة والمواصفة.
     */
    public function screenTextSettings(): void
    {
        $rows = [
            ['wallet.earnings.in_transit', '«كروت الأرباح» — كارت الأرباح قيد التحويل', 'قيد التحويل'],
            ['wallet.earnings.ready', '«كروت الأرباح» — كارت الأرباح الجاهزة', 'جاهزة للسحب'],
            ['wallet.earnings.received', '«كروت الأرباح» — وسم المستلَم', 'مستلمة'],
            ['wallet.earnings.total', '«كروت الأرباح» — كارت الأرباح الإجماليّة', 'إجماليّة'],
            ['wallet.exchange.amount_label', '«بوب-أب تحويل العملة» — عنوان خانة القيمة', 'الكمّيّة'],
            ['wallet.exchange.fee_note', '«بوب-أب تحويل العملة» — شرح رسوم التحويل (:fee)', 'رسوم ثابتة :fee% على كلّ المسارات.'],
            ['wallet.exchange.path_label', '«بوب-أب تحويل العملة» — عنوان مسار التحويل', 'من ← إلى'],
            ['wallet.exchange.quote_hint', '«بوب-أب تحويل العملة» — تلميح الملخّص قبل الكتابة', 'اكتب الكمّيّة وهنحسب لك الناتج.'],
            ['wallet.exchange.submit', '«بوب-أب تحويل العملة» — زرّ التنفيذ', 'أكّد التحويل'],
            ['wallet.exchange.title', '«بوب-أب تحويل العملة» — العنوان', 'تحويل العملة'],
            ['wallet.index.all_transactions', '«رصيدي وشحن» — رابط كلّ المعاملات', 'كلّ المعاملات'],
            ['wallet.index.balance_label', '«رصيدي وشحن» — اسم الرصيد الافتراضيّ', 'الرصيد'],
            ['wallet.index.breadcrumb_root', '«رصيدي وشحن» — جذر مسار التنقّل (منصوص في 24.5)', 'المحفظة'],
            ['wallet.index.earnings_title', '«رصيدي وشحن» — عنوان بلوك الأرباح', 'أرباحي'],
            ['wallet.index.exchange_action', '«رصيدي وشحن» — زرّ التحويل في كارت الرصيد', 'تحويل'],
            ['wallet.index.hours_hint', '«رصيدي وشحن» — تلميح عملة الساعات', 'عملة جايّة قدّام — بنعرضها من دلوقتي.'],
            ['wallet.index.more_actions_aria', '«رصيدي وشحن» — زرّ «⋯» لقارئ الشاشة', 'إجراءات أخرى'],
            ['wallet.index.recent_empty', '«رصيدي وشحن» — الحالة الفارغة لآخر الحركات', 'لسّه مافيش حركة على محفظتك — أوّل شحنة مستنّياك.'],
            ['wallet.index.recent_title', '«رصيدي وشحن» — عنوان آخر الحركات', 'آخر الحركات'],
            ['wallet.index.subtitle', '«رصيدي وشحن» — السطر تحت العنوان', 'رصيدك وأرباحك وكلّ حركة عليه — في مكان واحد.'],
            ['wallet.index.title', '«رصيدي وشحن» — العنوان', 'رصيدي وشحن'],
            ['wallet.index.topup_action', '«رصيدي وشحن» — زرّ الشحن', 'اشحن رصيدك'],
            ['wallet.quote.error', '«الملخّص اللحظيّ» — رسالة تعذّر الحساب', 'مش قادرين نحسب دلوقتي — جرّب تاني بعد شويّة.'],
            ['wallet.quote.fee', '«الملخّص اللحظيّ» — وسم الرسوم', 'الرسوم'],
            ['wallet.quote.offline', '«الملخّص اللحظيّ» — رسالة انقطاع الاتّصال', 'الاتّصال اتقطع فمقدرناش نحسب — راجع النت وجرّب تاني.'],
            ['wallet.quote.rate_used', '«الملخّص اللحظيّ» — السعر المستعمَل (:from · :rate · :to)', 'السعر المستعمَل: 1 :from = :rate :to.'],
            ['wallet.quote.received', '«الملخّص اللحظيّ» — وسم المستلَم', 'يستلم'],
            ['wallet.quote.recipient', '«الملخّص اللحظيّ» — اسم المستلِم في الملخّص (:name · :code)', 'المستلِم: :name (:code)'],
            ['wallet.quote.sent', '«الملخّص اللحظيّ» — وسم المُرسَل', 'المُرسَل'],
            ['wallet.quote.server_note', '«الملخّص اللحظيّ» — طمأنة أنّ الحساب خادميّ', 'الأرقام دي محسوبة في الخادم، ومش هتتغيّر بعد التأكيد.'],
            ['wallet.rates.breadcrumb_root', '«أسعار الصرف والرسوم» — جذر مسار التنقّل (منصوص في 24.5)', 'المحفظة'],
            ['wallet.rates.breadcrumb_self', '«أسعار الصرف والرسوم» — آخر مسار التنقّل', 'أسعار الصرف'],
            ['wallet.rates.field_meta', '«أسعار الصرف والرسوم» — سطر المفتاح والافتراضيّ (:key · :default)', 'المفتاح: :key · الافتراضيّ: :default'],
            ['wallet.rates.preview_exchange', '«أسعار الصرف والرسوم» — معاينة تحويل العملة (:amount)', 'تحويل :amount كوين ← تذاكر'],
            ['wallet.rates.preview_fee_net', '«أسعار الصرف والرسوم» — معاينة الرسوم والصافي (:fee · :net)', 'رسوم :fee ⟵ يستلم :net'],
            ['wallet.rates.preview_title', '«أسعار الصرف والرسوم» — عنوان المعاينة اللحظيّة', 'معاينة لحظيّة'],
            ['wallet.rates.preview_transfer', '«أسعار الصرف والرسوم» — معاينة الحوالة (:amount)', 'حوالة :amount كوين'],
            ['wallet.rates.preview_withdraw', '«أسعار الصرف والرسوم» — معاينة السحب (:amount)', 'سحب $:amount'],
            ['wallet.rates.preview_withdraw_fee_net', '«أسعار الصرف والرسوم» — معاينة رسوم السحب والصافي (:fee · :net)', 'رسوم $:fee ⟵ يوصله :net'],
            ['wallet.rates.reason_label', '«أسعار الصرف والرسوم» — عنوان سبب التعديل', 'سبب التعديل'],
            ['wallet.rates.reason_placeholder', '«أسعار الصرف والرسوم» — تلميح سبب التعديل', 'اكتب ليه بتغيّر الرقم ده'],
            ['wallet.rates.save_action', '«أسعار الصرف والرسوم» — زرّ الحفظ', 'احفظ'],
            ['wallet.rates.subtitle', '«أسعار الصرف والرسوم» — السطر تحت العنوان', 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ في المحفظة — لمالك المنصّة وحده.'],
            ['wallet.rates.title', '«أسعار الصرف والرسوم» — العنوان', 'أسعار الصرف والرسوم'],
            ['wallet.tabs.balance', '«تابات المحفظة» — تاب رصيدي', 'رصيدي'],
            ['wallet.tabs.transactions', '«تابات المحفظة» — تاب المعاملات', 'المعاملات'],
            ['wallet.tabs.withdrawals', '«تابات المحفظة» — تاب المسحوبات', 'المسحوبات'],
            ['wallet.tickets.balance_label', '«التذاكر» — اسم الرصيد الافتراضيّ', 'رصيد التذاكر'],
            ['wallet.tickets.earn_title', '«التذاكر» — عنوان مصادر الكسب', 'إزّاي تكسب تذاكر'],
            ['wallet.tickets.earned_label', '«التذاكر» — وسم المكتسب', 'مكتسب'],
            ['wallet.tickets.empty_action', '«التذاكر» — زرّ الحالة الفارغة', 'ابدأ تعلّمك'],
            ['wallet.tickets.empty_message', '«التذاكر» — نصّ الحالة الفارغة', 'لسّه مافيش تذاكر — أوّل درس هيجيبلك أوّل تذكرة.'],
            ['wallet.tickets.history_action', '«التذاكر» — زرّ حركة التذاكر', 'حركة تذاكري'],
            ['wallet.tickets.recent_title', '«التذاكر» — عنوان آخر الحركات', 'آخر حركات التذاكر'],
            ['wallet.tickets.spend_title', '«التذاكر» — عنوان مواضع الصرف', 'تصرفها فين'],
            ['wallet.tickets.spent_label', '«التذاكر» — وسم المصروف', 'مصروف'],
            ['wallet.tickets.subtitle', '«التذاكر» — السطر تحت العنوان', 'التذكرة عملة تفاعل: بتكسبها من تعلّمك، وبتصرفها على اللي يهمّك.'],
            ['wallet.tickets.title', '«التذاكر» — العنوان', 'التذاكر'],
            ['wallet.topup.amount_label', '«شحن الحساب» — عنوان خانة القيمة', 'القيمة المحوَّلة'],
            ['wallet.topup.beneficiary', '«شحن الحساب» — اسم المستفيد (:name)', 'المستفيد: :name'],
            ['wallet.topup.contact_phone_label', '«شحن الحساب» — عنوان رقم التواصل', 'رقم التواصل'],
            ['wallet.topup.copy_action', '«شحن الحساب» — زرّ نسخ رقم الحساب', 'نسخ'],
            ['wallet.topup.copy_done', '«شحن الحساب» — ردّ النسخ الناجح', 'اتنسخ ✓'],
            ['wallet.topup.copy_manual', '«شحن الحساب» — ردّ تعذّر النسخ', 'انسخه يدويًّا'],
            ['wallet.topup.disabled_action', '«شحن الحساب» — زرّ العودة للمحفظة', 'ارجع للمحفظة'],
            ['wallet.topup.disabled_message', '«شحن الحساب» — رسالة طريقة الشحن الموقوفة', 'طريقة الشحن دي متوقّفة حاليًّا.'],
            ['wallet.topup.form_title', '«شحن الحساب» — عنوان فورم بيانات التحويل', 'بيانات التحويل'],
            ['wallet.topup.gateway_empty', '«شحن الحساب» — الحالة الفارغة لعروض البوّابة', 'لسّه مافيش عروض على البوّابة — جرّب التحويل اليدويّ.'],
            ['wallet.topup.gateway_empty_action', '«شحن الحساب» — زرّ الحالة الفارغة للبوّابة', 'التحويل اليدويّ'],
            ['wallet.topup.gateway_note', '«شحن الحساب» — شرح التحويل لصفحة الدفع', 'بنحوّلك لصفحة الدفع الآمنة، ورصيدك بيتحدّث لمّا يوصلنا تأكيد الدفع من البوّابة.'],
            ['wallet.topup.method_label', '«شحن الحساب» — عنوان طريقة التحويل', 'طريقة التحويل'],
            ['wallet.topup.method_placeholder', '«شحن الحساب» — خيار «اختر…» لطريقة التحويل', 'اختر…'],
            ['wallet.topup.method_type_bank', '«شحن الحساب» — نوع الطريقة: حساب بنكيّ', 'حساب بنكيّ'],
            ['wallet.topup.method_type_instapay', '«شحن الحساب» — نوع الطريقة: إنستا باي', 'إنستا باي'],
            ['wallet.topup.method_type_other', '«شحن الحساب» — نوع الطريقة: أخرى', 'أخرى'],
            ['wallet.topup.method_type_wallet', '«شحن الحساب» — نوع الطريقة: محفظة موبايل', 'محفظة موبايل'],
            ['wallet.topup.methods_empty', '«شحن الحساب» — الحالة الفارغة لطرق التحويل', 'لسّه مافيش طرق تحويل متاحة — جرّب بوّابة الدفع.'],
            ['wallet.topup.methods_title', '«شحن الحساب» — عنوان طرق التحويل', 'طرق التحويل'],
            ['wallet.topup.no_refund_note', '«شحن الحساب» — سطر سياسة عدم الاسترجاع', 'الشحن يزيد رصيد الكوينز، ولا استرجاع نقديّ — الرصيد يفضل في محفظتك تشتري بيه اللي يعجبك.'],
            ['wallet.topup.offer_label', '«شحن الحساب» — عنوان اختيار العرض', 'عرض الشحن'],
            ['wallet.topup.offer_line', '«شحن الحساب» — سطر العرض (:pay · :credit)', 'ادفع :pay ← تحصل على :credit كوينز'],
            ['wallet.topup.offer_option', '«شحن الحساب» — خيار العرض في القائمة (:pay · :credit)', 'ادفع :pay ← :credit كوينز'],
            ['wallet.topup.offer_other', '«شحن الحساب» — خيار مبلغ آخر', 'مبلغ آخر'],
            ['wallet.topup.offers_title', '«شحن الحساب» — عنوان عروض الشحن', 'عروض الشحن'],
            ['wallet.topup.paid_at_label', '«شحن الحساب» — عنوان وقت الدفع', 'وقت وتاريخ الدفع'],
            ['wallet.topup.pay_now_action', '«شحن الحساب» — زرّ الدفع الآن', 'ادفع دلوقتي'],
            ['wallet.topup.pending_note', '«شحن الحساب» — شرح الطلب المعلَّق', 'بنراجع طلبك الحاليّ الأوّل. تقدر تتابع حالته، وأوّل ما يخلص تبعت التالي.'],
            ['wallet.topup.pending_title', '«شحن الحساب» — عنوان الطلب المعلَّق (:number)', 'طلبك رقم :number تحت التحقّق'],
            ['wallet.topup.popular_badge', '«شحن الحساب» — وسم العرض الأكثر شيوعًا', 'الأكثر شيوعًا'],
            ['wallet.topup.receipt_hint', '«شحن الحساب» — شرح مرفق الإيصال', 'مرفق إلزاميّ — صورة أو PDF.'],
            ['wallet.topup.receipt_label', '«شحن الحساب» — عنوان صورة الإيصال', 'صورة الإيصال'],
            ['wallet.topup.submit_action', '«شحن الحساب» — زرّ إرسال الطلب', 'ابعت الطلب'],
            ['wallet.topup.subtitle', '«شحن الحساب» — السطر تحت العنوان', 'اختر طريقتك: تحويل يدويّ بإيصال، أو دفع مباشر من البوّابة.'],
            ['wallet.topup.tab_gateway', '«شحن الحساب» — تاب بوّابة الدفع', 'بوّابة الدفع'],
            ['wallet.topup.tab_manual', '«شحن الحساب» — تاب التحويل اليدويّ', 'تحويل يدويّ'],
            ['wallet.topup.title', '«شحن الحساب» — العنوان', 'شحن الحساب'],
            ['wallet.topup.track_action', '«شحن الحساب» — زرّ متابعة الطلب', 'تابع طلبك'],
            ['wallet.topup_requests.credited_note', '«طلبات الشحن» — سطر إضافة الرصيد (:amount)', 'اتضاف لمحفظتك :amount كوينز ✓'],
            ['wallet.topup_requests.empty_message', '«طلبات الشحن» — نصّ الحالة الفارغة', 'لسّه مابعتّش أيّ طلب شحن.'],
            ['wallet.topup_requests.method_unknown', '«طلبات الشحن» — طريقة التحويل غير المحدّدة', 'طريقة غير محدّدة'],
            ['wallet.topup_requests.new_action', '«طلبات الشحن» — زرّ طلب شحن جديد', 'طلب شحن جديد'],
            ['wallet.topup_requests.paid_at', '«طلبات الشحن» — وقت الدفع في سطر الطلب (:at)', '· دُفِع في :at'],
            ['wallet.topup_requests.resend_action', '«طلبات الشحن» — زرّ التعديل وإعادة الإرسال', 'عدّل وأعد الإرسال'],
            ['wallet.topup_requests.state_cancelled', '«طلبات الشحن» — حالة الطلب: ملغاة', 'ملغاة'],
            ['wallet.topup_requests.state_completed', '«طلبات الشحن» — حالة الطلب: مكتملة', 'مكتملة'],
            ['wallet.topup_requests.state_duplicate', '«طلبات الشحن» — حالة الطلب: مكرَّرة', 'مكرَّرة'],
            ['wallet.topup_requests.state_pending', '«طلبات الشحن» — حالة الطلب: قيد التحقّق', 'قيد التحقّق'],
            ['wallet.topup_requests.step_sent', '«طلبات الشحن» — خطوة «أُرسِل» في التسلسل', 'أُرسِل'],
            ['wallet.topup_requests.subtitle', '«طلبات الشحن» — السطر تحت العنوان', 'كلّ طلباتك وحالتها لحظةً بلحظة.'],
            ['wallet.topup_requests.title', '«طلبات الشحن» — العنوان', 'طلبات الشحن'],
            ['wallet.topup_return.fail_body', '«حالة الدفع» — شرح فشل الدفع', 'ما اتخصمش منك حاجة. تقدر تجرّب تاني أو تستعمل التحويل اليدويّ.'],
            ['wallet.topup_return.fail_title', '«حالة الدفع» — عنوان فشل الدفع', 'الدفع ما تمّش'],
            ['wallet.topup_return.pending_body', '«حالة الدفع» — شرح الدفع تحت التأكيد', 'لسّه بننتظر تأكيد البوّابة. سيبها علينا وهنبلّغك.'],
            ['wallet.topup_return.pending_title', '«حالة الدفع» — عنوان الطلب المعلَّق (:number)', 'الدفع تحت التأكيد'],
            ['wallet.topup_return.success_body', '«حالة الدفع» — شرح نجاح الدفع', 'رصيدك هيتحدّث أوّل ما يوصلنا تأكيد البوّابة، وهيوصلك إشعار.'],
            ['wallet.topup_return.success_title', '«حالة الدفع» — عنوان نجاح الدفع', 'استلمنا دفعتك'],
            ['wallet.topup_return.title', '«حالة الدفع» — العنوان', 'حالة الدفع'],
            ['wallet.transactions.badge_capped', '«المعاملات والفواتير» — شارة تجاوز الحدّ اليوميّ', 'تجاوز الحدّ اليوميّ'],
            ['wallet.transactions.badge_correction', '«المعاملات والفواتير» — شارة التصحيح', 'تصحيح'],
            ['wallet.transactions.balance_after_inline', '«المعاملات والفواتير» — الرصيد بعد الحركة في الكارت (:balance)', 'الرصيد بعدها: :balance'],
            ['wallet.transactions.col_amount', '«المعاملات والفواتير» — عمود الكمية', 'الكمية'],
            ['wallet.transactions.col_currency', '«المعاملات والفواتير» — عمود العملة', 'العملة'],
            ['wallet.transactions.col_date', '«المعاملات والفواتير» — عمود التاريخ', 'التاريخ'],
            ['wallet.transactions.col_flow', '«المعاملات والفواتير» — عمود من ← إلى', 'من ← إلى'],
            ['wallet.transactions.col_notes', '«المعاملات والفواتير» — عمود الملاحظات', 'ملاحظات'],
            ['wallet.transactions.col_reason', '«المعاملات والفواتير» — عمود السبب', 'السبب'],
            ['wallet.transactions.details_title', '«المعاملات والفواتير» — عنوان بانل التفاصيل', 'تفاصيل الحركة'],
            ['wallet.transactions.empty_message', '«المعاملات والفواتير» — نصّ الحالة الفارغة', 'مافيش حركات في المدى ده — وسّع المدى أو ابدأ بشحن رصيدك.'],
            ['wallet.transactions.export_action', '«المعاملات والفواتير» — زرّ تصدير الكشف', 'تصدير كشف CSV'],
            ['wallet.transactions.field_amount', '«المعاملات والفواتير» — حقل القيمة المسجَّلة', 'القيمة المسجَّلة'],
            ['wallet.transactions.field_applied', '«المعاملات والفواتير» — حقل المطبَّق فعلًا', 'المطبَّق فعلًا'],
            ['wallet.transactions.field_balance_after', '«المعاملات والفواتير» — حقل الرصيد بعدها', 'الرصيد بعدها'],
            ['wallet.transactions.field_invoice', '«المعاملات والفواتير» — حقل رقم الفاتورة', 'رقم الفاتورة'],
            ['wallet.transactions.field_reference', '«المعاملات والفواتير» — حقل المرجع', 'المرجع'],
            ['wallet.transactions.filter_all', '«المعاملات والفواتير» — خيار الكلّ في الفلاتر', 'الكلّ'],
            ['wallet.transactions.filter_all_time', '«المعاملات والفواتير» — خيار توسيع المدى', 'وسّع المدى لكلّ الفترات'],
            ['wallet.transactions.filter_apply', '«المعاملات والفواتير» — زرّ تطبيق الفلاتر', 'طبّق'],
            ['wallet.transactions.filter_currency', '«المعاملات والفواتير» — عنوان فلتر العملة', 'العملة'],
            ['wallet.transactions.filter_from', '«المعاملات والفواتير» — عنوان تاريخ البداية', 'من تاريخ'],
            ['wallet.transactions.filter_search', '«المعاملات والفواتير» — عنوان خانة البحث', 'بحث بالسبب'],
            ['wallet.transactions.filter_search_placeholder', '«المعاملات والفواتير» — تلميح خانة البحث', 'اكتب كلمة…'],
            ['wallet.transactions.filter_source', '«المعاملات والفواتير» — عنوان فلتر النوع', 'النوع'],
            ['wallet.transactions.filter_to', '«المعاملات والفواتير» — عنوان تاريخ النهاية', 'إلى تاريخ'],
            ['wallet.transactions.note_capped', '«المعاملات والفواتير» — شرح تجاوز الحدّ اليوميّ', 'الحركة دي تعدّت الحدّ اليوميّ، فاتسجّلت كاملة واتطبّق منها الجزء المسموح.'],
            ['wallet.transactions.note_correction', '«المعاملات والفواتير» — شرح حركة التصحيح', 'دي حركة تصحيح موثّقة تعكس حركةً سابقة.'],
            ['wallet.transactions.subtitle', '«المعاملات والفواتير» — السطر تحت العنوان', 'كلّ حركة على محفظتك بمرجعها ورصيدك بعدها.'],
            ['wallet.transactions.title', '«المعاملات والفواتير» — العنوان', 'المعاملات والفواتير'],
            ['wallet.transfer.amount_hint', '«بوب-أب الحوالة» — حدود خانة القيمة', 'أقلّ حوالة :min.'],
            ['wallet.transfer.amount_label', '«بوب-أب الحوالة» — عنوان خانة القيمة', 'الكمّيّة'],
            ['wallet.transfer.code_hint', '«بوب-أب الحوالة» — شرح كود المستلِم', 'اكتب كود صاحبك زيّ ما هو، وهيظهر لك اسمه قبل التأكيد.'],
            ['wallet.transfer.code_label', '«بوب-أب الحوالة» — عنوان كود المستلِم', 'كود المستلِم'],
            ['wallet.transfer.currency_label', '«بوب-أب الحوالة» — عنوان العملة', 'العملة'],
            ['wallet.transfer.currency_option', '«بوب-أب الحوالة» — خيار العملة برسومها (:name · :fee)', ':name — رسوم :fee%'],
            ['wallet.transfer.quote_hint', '«بوب-أب الحوالة» — تلميح الملخّص قبل الكتابة', 'اكتب الكمّيّة وهنحسب لك كلّ حاجة.'],
            ['wallet.transfer.submit', '«بوب-أب الحوالة» — زرّ التنفيذ', 'أكّد الحوالة'],
            ['wallet.transfer.title', '«بوب-أب الحوالة» — العنوان', 'إرسال حوالة'],
            ['wallet.transfer.xp_fee_note', '«بوب-أب الحوالة» — شرح ارتفاع رسوم الـXP', 'رسوم الـXP مرتفعة عن قصد عشان تفضل لوحة المتصدّرين نضيفة.'],
            ['wallet.withdraw.account_name_label', '«بوب-أب السحب» — عنوان اسم صاحب الحساب', 'اسم صاحب الحساب (اختياريّ)'],
            ['wallet.withdraw.account_number_label', '«بوب-أب السحب» — عنوان رقم الحساب', 'رقم الحساب أو المحفظة'],
            ['wallet.withdraw.amount_hint', '«بوب-أب السحب» — حدود خانة القيمة', 'أقلّ سحب $:min · رسوم :fee% بحدّ أدنى $:minfee.'],
            ['wallet.withdraw.amount_label', '«بوب-أب السحب» — عنوان خانة القيمة', 'قيمة السحب بالدولار'],
            ['wallet.withdraw.available', '«بوب-أب السحب» — وسم المتاح للسحب', 'متاح للسحب'],
            ['wallet.withdraw.method_label', '«بوب-أب السحب» — عنوان طريقة التحويل', 'طريقة التحويل'],
            ['wallet.withdraw.pending_note', '«بوب-أب السحب» — شرح الطلب المعلَّق', 'عندك طلب سحب رقم :number لسّه تحت المراجعة — استنّى نتيجته وبعدين ابعت طلبًا جديدًا.'],
            ['wallet.withdraw.quote_hint', '«بوب-أب السحب» — تلميح الملخّص قبل الكتابة', 'اكتب القيمة وهنحسب لك الصافي.'],
            ['wallet.withdraw.requested', '«بوب-أب السحب» — وسم المبلغ المطلوب', 'المطلوب'],
            ['wallet.withdraw.submit', '«بوب-أب السحب» — زرّ التنفيذ', 'ابعت الطلب'],
            ['wallet.withdraw.title', '«بوب-أب السحب» — العنوان', 'سحب الأرباح'],
            ['wallet.withdraw.you_get', '«بوب-أب السحب» — وسم الصافي الواصل', 'يوصلك'],
            ['wallet.withdrawals.col_amount', '«المسحوبات» — عمود الكمية', 'القيمة'],
            ['wallet.withdrawals.col_date', '«المسحوبات» — عمود التاريخ', 'التاريخ'],
            ['wallet.withdrawals.col_fee', '«المسحوبات» — عمود الرسوم', 'الرسوم'],
            ['wallet.withdrawals.col_number', '«المسحوبات» — عمود رقم الطلب', 'رقم الطلب'],
            ['wallet.withdrawals.col_receipt', '«المسحوبات» — عمود صورة الفاتورة', 'صورة الفاتورة'],
            ['wallet.withdrawals.col_status', '«المسحوبات» — عمود الحالة', 'الحالة'],
            ['wallet.withdrawals.empty_message', '«المسحوبات» — نصّ الحالة الفارغة', 'لسّه مافيش مسحوبات — أوّل أرباحك على بُعد دعوة واحدة.'],
            ['wallet.withdrawals.limits_note', '«المسحوبات» — حدود السحب (:fee · :minfee · :min)', 'رسوم السحب :fee% بحدّ أدنى $:minfee، وأقلّ سحب $:min.'],
            ['wallet.withdrawals.net_inline', '«المسحوبات» — الصافي في كارت الموبايل (:net)', 'يوصلك $:net'],
            ['wallet.withdrawals.open_receipt', '«المسحوبات» — رابط فتح صورة الفاتورة', 'افتح الصورة'],
            ['wallet.withdrawals.subtitle', '«المسحوبات» — السطر تحت العنوان', 'أرباحك وطلبات سحبها وحالة كلّ طلب.'],
            ['wallet.withdrawals.table_title', '«المسحوبات» — عنوان الجدول', 'جدول المسحوبات'],
        ];

        foreach ($rows as [$key, $label, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'wallet',
                'label_ar' => $label,
                'type' => 'string',
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        Cache::forget('settings');
    }

    /** إعدادات المجال — لا رقم ولا مفتاح محروق في الكود (2.13) */
    public function settings(): void
    {
        $rows = [
            ['topup.credit_currency', 'store', 'عملة الشحن', 'string', 'coins'],
            ['topup.min_amount', 'store', 'أدنى قيمة تحويل مقبولة', 'number', '10'],
            ['topup.gateway.currency', 'store', 'عملة البوّابة', 'string', 'EGP'],
            ['topup.gateway.customer_address', 'store', 'عنوان العميل المرسَل للبوّابة', 'string', '-'],
            ['wallet.tickets.earn_sources', 'wallet', 'مصادر كسب التذاكر', 'json', json_encode([
                'إكمال درس قبل نصف الديدلاين',
                'إكمال ستريك 7 أيّام متواصلة',
                'الدعوات: تذكرة للداعي وتذكرة للمدعوّ',
                'الاختبار التمهيديّ ومفاجآت الرسائل الإيجابيّة',
            ], JSON_UNESCAPED_UNICODE)],
            ['wallet.tickets.spend_targets', 'wallet', 'مواضع صرف التذاكر', 'json', json_encode([
                'دخول الامتحان النهائيّ للتدريب',
                'استخراج السيرة الذاتيّة',
                'تجميد الستريك ليومٍ فايت',
                'حروب التركيز',
            ], JSON_UNESCAPED_UNICODE)],
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

        Cache::forget('settings');
    }

    /** طرق التحويل الأربع بأرقام قابلة للنسخ (19.5-ب-1) */
    private function transferMethods(): void
    {
        $rows = [
            ['bank', 'البنك الأهليّ المصريّ', '1234567890123456', 'مؤسّسة المنصّة للتدريب', 'حوّل ثمّ ارفع صورة الإيصال.', 1],
            ['wallet', 'فودافون كاش', '01001234567', 'محمد عبد الرحمن', 'المحفظة تستقبل تحويلات المحافظ فقط.', 2],
            ['instapay', 'إنستا باي', 'platform@instapay', 'مؤسّسة المنصّة للتدريب', 'اكتب كودك في خانة الملاحظات.', 3],
            ['other', 'تحويل آخر', null, 'مؤسّسة المنصّة للتدريب', 'كلّمنا الأوّل قبل التحويل بطريقة غير المذكور.', 4],
        ];

        foreach ($rows as [$type, $name, $account, $beneficiary, $notes, $order]) {
            TransferMethod::updateOrCreate(['name_ar' => $name], [
                'type' => $type,
                'account_number' => $account,
                'beneficiary_name' => $beneficiary,
                'notes' => $notes,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    /** عروض الشحن — منفصلة لكلّ طريقة وبقيمتها الحقيقيّة صراحةً (19.5-ب-2 · ج-3) */
    private function offers(): void
    {
        $rows = [
            ['manual', 'باقة البداية', 100, 100, 0, false, 1],
            ['manual', 'باقة المتعلّم', 500, 550, 10, true, 2],
            ['manual', 'باقة المثابر', 1000, 1200, 20, false, 3],
            ['gateway', 'باقة البداية', 100, 100, 0, false, 1],
            ['gateway', 'باقة المتعلّم', 500, 540, 8, true, 2],
            ['gateway', 'باقة المثابر', 1000, 1150, 15, false, 3],
        ];

        foreach ($rows as [$method, $label, $pay, $credit, $bonus, $popular, $order]) {
            TopupOffer::updateOrCreate(['method' => $method, 'label_ar' => $label], [
                'pay_amount' => $pay,
                'credit_amount' => $credit,
                'bonus_percent' => $bonus,
                'is_popular' => $popular,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    /** محفظة مستخدم تجريبيّ بحركات واقعيّة */
    private function demoWallet(): void
    {
        $user = User::query()->where('status', 'active')->first();

        if (! $user) {
            return;
        }

        $ledger = app(LedgerService::class);

        $ledger->credit($user, 'coins', 550, 'topup', null, 'training', 'شحن الحساب — باقة المتعلّم');
        $ledger->debit($user, 'coins', 300, 'purchase', null, 'training', 'شراء تدريب «أساسيّات إدارة المشروعات»');
        $ledger->credit($user, 'tickets', 2, 'academy', null, 'training', 'إكمال درس قبل نصف الديدلاين');
        $ledger->debit($user, 'tickets', 1, 'academy', null, 'training', 'دخول الامتحان النهائيّ');
        $ledger->credit($user, 'xp', 150, 'academy', null, 'training', 'حضور نادي الخامسة صباحًا');

        $this->command?->info('محفظة تجريبيّة للمستخدم: '.$user->code);
    }
}
