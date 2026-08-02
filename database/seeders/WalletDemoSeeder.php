<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\TransferMethod;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات المحفظة التجريبيّة (19 · 19.5): إعدادات المجال · طرق التحويل ·
 * عروض الشحن للطريقتين · حركات على محفظة مستخدم تجريبيّ.
 */
class WalletDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->traineeGrants();
        $this->transferMethods();
        $this->offers();
        $this->demoWallet();
    }

    /** إعدادات المجال — لا رقم ولا مفتاح محروق في الكود (2.13) */
    private function settings(): void
    {
        $rows = [
            ['topup.credit_currency', 'store', 'عملة الشحن', 'string', 'coins'],
            ['topup.min_amount', 'store', 'أدنى قيمة تحويل مقبولة', 'number', '10'],
            ['topup.gateway.currency', 'store', 'عملة البوّابة', 'string', 'EGP'],
            ['topup.gateway.customer_address', 'store', 'عنوان العميل المرسَل للبوّابة', 'string', '-'],
            ['wallet.tickets.earn_sources', 'store', 'مصادر كسب التذاكر', 'json', json_encode([
                'إكمال درس قبل نصف الديدلاين',
                'إكمال ستريك 7 أيّام متواصلة',
                'الدعوات: تذكرة للداعي وتذكرة للمدعوّ',
                'الاختبار التمهيديّ ومفاجآت الرسائل الإيجابيّة',
            ], JSON_UNESCAPED_UNICODE)],
            ['wallet.tickets.spend_targets', 'store', 'مواضع صرف التذاكر', 'json', json_encode([
                'دخول الامتحان النهائيّ للتدريب',
                'استخراج السيرة الذاتيّة',
                'تجميد الستريك ليومٍ فايت',
                'الألعاب وحروب التركيز',
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

    /**
     * صلاحيّات المحفظة للمتدرّب بنطاق SELF — حساباته هو وحدها.
     * (مصفوفة الأدوار العامّة تسمّي المورد `wallets` بينما مفتاحه `wallet`، فنسدّ الفرق هنا.)
     */
    private function traineeGrants(): void
    {
        $role = Role::query()->where('key', 'trainee')->first();

        if (! $role) {
            return;
        }

        $keys = ['wallet.view', 'wallet.list', 'wallet.export', 'topup.create', 'topup.list'];

        $ids = Permission::query()->whereIn('key', $keys)->pluck('id')->all();

        foreach ($ids as $id) {
            DB::table('permission_role')->upsert([[
                'role_id' => $role->id,
                'permission_id' => $id,
                'scope' => 'SELF',
                'effect' => 'allow',
                'conditions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['role_id', 'permission_id', 'scope'], ['effect', 'updated_at']);
        }
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
