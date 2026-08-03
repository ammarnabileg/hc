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
