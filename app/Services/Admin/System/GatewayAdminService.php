<?php

namespace App\Services\Admin\System;

use App\Models\GatewayWebhookLog;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\TransferMethod;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * إعدادات بوّابة الدفع وطرق التحويل وعروض الشحن (19.5-ب-1/2 · 19.5-ج-5).
 *
 * 🔒 **المفاتيح لمالك المنصّة وحده** — ولا تُعرَض لغيره ولو مقنَّعة.
 */
class GatewayAdminService
{
    /** المفاتيح الحسّاسة: لا تُقرَأ ولا تُكتَب إلّا لمالك المنصّة (12.2.1) */
    public const SECRET_KEYS = [
        'topup.gateway.api_key',
        'topup.gateway.vendor_key',
    ];

    /** إعدادات البوّابة غير السرّيّة — يراها المسؤول الماليّ ويعدّلها */
    public const PUBLIC_KEYS = [
        'topup.gateway.enabled' => 'تفعيل البوّابة',
        'topup.gateway.sandbox' => 'وضع الاختبار (Sandbox) ⇄ الإنتاج',
        'topup.gateway.currency' => 'العملة',
        'topup.gateway.success_url' => 'رابط النجاح',
        'topup.gateway.fail_url' => 'رابط الفشل',
        'topup.gateway.pending_url' => 'رابط المعلّق',
        'topup.gateway.methods' => 'وسائل الدفع المفعَّلة',
        'topup.gateway.min_amount' => 'الحدّ الأدنى للعمليّة',
        'topup.gateway.max_amount' => 'الحدّ الأقصى للعمليّة',
        'topup.gateway.fees_on' => 'تحميل الرسوم (المنصّة/المستخدم)',
    ];

    /** @return Collection<int, Setting> */
    public function settingsFor(User $user): Collection
    {
        $keys = array_keys(self::PUBLIC_KEYS);

        // 🔒 المفاتيح تُضاف فقط لمالك المنصّة — وغيره لا يعرف بوجودها أصلًا
        if ($user->isPlatformOwner()) {
            $keys = array_merge($keys, self::SECRET_KEYS);
        }

        return Setting::query()->whereIn('key', $keys)->orderBy('key')->get();
    }

    public function maySeeSecrets(User $user): bool
    {
        return $user->isPlatformOwner();
    }

    /**
     * ⭐ [اختبار الاتّصال] — نداء خفيف بالمفاتيح المحفوظة ونتيجة فوريّة،
     * فلا يكتشف الأدمن الخطأ عند أوّل عميل يدفع.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(User $user): array
    {
        if (! $this->maySeeSecrets($user)) {
            return ['ok' => false, 'message' => setting('store.gateway_admin_service.test_connection_1', 'اختبار الاتّصال لمالك المنصّة وحده.')];
        }

        $key = (string) setting('topup.gateway.api_key', '');

        if ($key === '') {
            return ['ok' => false, 'message' => setting('store.gateway_admin_service.test_connection_2', 'مفتاح API فاضي — ضيفه الأوّل ثمّ جرّب.')];
        }

        $base = setting('topup.gateway.sandbox', true)
            ? 'https://staging.fawaterk.com/api/v2/'
            : 'https://app.fawaterk.com/api/v2/';

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout((int) setting('topup.gateway.timeout_seconds', 8))
                ->get($base.'getPaymentmethods');

            return $response->successful()
                ? ['ok' => true, 'message' => setting('store.gateway_admin_service.test_connection_3', 'الاتّصال تمام ✓ — البوّابة ردّت بنجاح.')]
                : ['ok' => false, 'message' => strtr(setting('store.gateway_admin_service.test_connection_4', 'البوّابة ردّت بكود :p1 — راجع المفتاح والبيئة.'), [':p1' => (string) ($response->status())])];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => strtr(setting('store.gateway_admin_service.test_connection_5', 'تعذّر الوصول للبوّابة: :p1'), [':p1' => (string) ($e->getMessage())])];
        }
    }

    /** ⭐ سجلّ الـWebhook الخام بنتيجة التحقّق — عرضٌ فقط ولا تعديل (19.5-ج-2) */
    public function webhookLogs(array $filters): LengthAwarePaginator
    {
        return GatewayWebhookLog::query()
            ->when($filters['invoice'] ?? null, fn ($q, $id) => $q->where('invoice_id', 'like', "%{$id}%"))
            ->when(($filters['hash'] ?? null) === 'valid', fn ($q) => $q->where('hash_valid', true))
            ->when(($filters['hash'] ?? null) === 'invalid', fn ($q) => $q->where('hash_valid', false))
            ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('result', $result))
            ->latest('id')
            ->paginate((int) setting('topup.gateway.logs_per_page', 25))
            ->withQueryString();
    }

    /** طرق التحويل — إضافة/تعديل/حذف/ترتيب/تفعيل، كلّها من اللوحة (19.5-ب-1) */
    public function transferMethods(): Collection
    {
        return TransferMethod::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function transferTypes(): array
    {
        return [
            'bank' => setting('store.gateway_admin_service.transfer_types_1', 'حساب بنكيّ'),
            'wallet' => setting('store.gateway_admin_service.transfer_types_2', 'محفظة موبايل'),
            'instapay' => setting('store.gateway_admin_service.transfer_types_3', 'إنستا باي'),
            'other' => setting('store.gateway_admin_service.transfer_types_4', 'أخرى'),
        ];
    }

    /** عروض الشحن لكلّ طريقة — وقيمة العرض تُحسَب في الخادم دائمًا (19.5-أ) */
    public function offers(string $method = 'manual'): Collection
    {
        return TopupOffer::query()->where('method', $method)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * نسبة الزيادة المعلنة صراحةً: «ادفع 500 ← تحصل على 550 (+10%)» —
     * **بلا مبالغة وبلا Dark Patterns** (2.9 · 19.5-ب-2).
     */
    public function bonusPercent(float $pay, float $credit): float
    {
        return $pay > 0 ? round((($credit - $pay) / $pay) * 100, 2) : 0.0;
    }
}
