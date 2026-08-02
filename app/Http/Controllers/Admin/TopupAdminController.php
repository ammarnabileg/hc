<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\TransferMethod;
use App\Services\Admin\System\GatewayAdminService;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Admin\System\TopupReviewService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * إدارة طلبات الشحن وطرق التحويل والعروض وبوّابة الدفع (19.5-ب-5 · ج-5).
 *
 * ⛔ **لا موافقة سريعة بضغطة**: `approve` يرفض أيّ طلب لم تُسجَّل مراجعة إيصاله.
 * ⛔ **ولا مهلة مراجعة معلَنة للمُرسِل**: العدّاد وتلوين المتأخّر يُحسبان هنا
 *    ويُعرَضان في شاشة الأدمن فقط، ولا يخرجان في أيّ إشعار أو صفحة للمستخدم.
 */
class TopupAdminController extends Controller
{
    public function __construct(
        private readonly TopupReviewService $review,
        private readonly GatewayAdminService $gateway,
        private readonly LedgerService $ledger,
        private readonly SettingsRegistry $registry,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: TopupService::PENDING,
            'method' => $request->integer('method') ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'q' => $request->string('q')->toString() ?: null,
        ];

        $rows = TopupRequest::query()
            ->with(['user', 'transfer_method', 'topup_offer'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['method'], fn ($q, $id) => $q->where('transfer_method_id', $id))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'], fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))
            ))
            ->latest('id')
            ->paginate((int) setting('topup.admin.per_page', 20))
            ->withQueryString();

        return view('admin.store.topups.index', [
            'rows' => $rows,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            // ⭐ عدّاد N بجوار التاب لأيّ جديد
            'pendingCount' => $this->review->pendingCount(),
            'methods' => $this->gateway->transferMethods(),
            'review' => $this->review,
        ]);
    }

    /** شاشة المراجعة: بيانات الطلب + **معاينة الإيصال بجوار الفورم** + المستخدم ورصيده */
    public function show(Request $request, TopupRequest $topupRequest): View
    {
        $topupRequest->load(['user', 'transfer_method', 'topup_offer', 'reviewed_by']);
        $currency = (string) setting('topup.credit_currency', 'coins');

        return view('admin.store.topups.show', [
            'request' => $topupRequest,
            'offers' => $this->gateway->offers('manual'),
            'balance' => $this->ledger->balance($topupRequest->user, $currency),
            'duplicateOf' => $this->review->duplicateOf($topupRequest),
            'receiptReviewed' => $this->review->receiptReviewed($topupRequest),
            // ⛔ داخليّ للأدمن فقط — لا يُعرَض للمُرسِل ولا يُعلَن كالتزام
            'internalAgeHours' => $this->review->internalAgeHours($topupRequest),
            'internalIsLate' => $this->review->internalIsLate($topupRequest),
            'history' => TopupRequest::query()
                ->where('user_id', $topupRequest->user_id)
                ->whereKeyNot($topupRequest->getKey())
                ->latest('id')->limit(5)->get(),
        ]);
    }

    /** تسجيل أنّ الأدمن فتح الإيصال وراجعه — بوّابة الاعتماد الإلزاميّة */
    public function markReviewed(Request $request, TopupRequest $topupRequest): RedirectResponse
    {
        $this->review->markReceiptReviewed($topupRequest, $request->user());

        return back()->with('status', 'اتسجّلت مراجعة الإيصال — تقدر تكمّل الاعتماد دلوقتي.');
    }

    /** ⭐ معاينة الرصيد قبل/بعد قبل التأكيد — والقيمة تُحسَب في الخادم دائمًا */
    public function preview(Request $request, TopupRequest $topupRequest): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:offer,manual'],
            'topup_offer_id' => ['nullable', 'integer'],
            'manual_amount' => ['nullable', 'numeric'],
        ]);

        try {
            return response()->json($this->review->preview(
                $topupRequest,
                $data['mode'],
                $data['topup_offer_id'] ?? null,
                isset($data['manual_amount']) ? (float) $data['manual_amount'] : null,
            ));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, TopupRequest $topupRequest): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:offer,manual'],
            'topup_offer_id' => ['nullable', 'integer'],
            'manual_amount' => ['nullable', 'numeric'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->review->approve(
                $topupRequest,
                $request->user(),
                $data['mode'],
                $data['topup_offer_id'] ?? null,
                isset($data['manual_amount']) ? (float) $data['manual_amount'] : null,
                $data['reason'],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.topups.index')->with('status', 'الطلب اتعتمد والرصيد اتضاف ✓');
    }

    /** ⭐ سبب الإلغاء إلزاميّ ويصل للمستخدم بنصٍّ واضح */
    public function cancel(Request $request, TopupRequest $topupRequest): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->review->cancel($topupRequest, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.topups.index')->with('status', 'الطلب اتلغى والسبب وصل صاحبه ✓');
    }

    // ---------------------------------------------------------------- طرق التحويل والعروض

    public function methods(Request $request): View
    {
        return view('admin.store.topups.methods', [
            'methods' => $this->gateway->transferMethods(),
            'types' => $this->gateway->transferTypes(),
            'manualOffers' => $this->gateway->offers('manual'),
            'gatewayOffers' => $this->gateway->offers('gateway'),
            'service' => $this->gateway,
        ]);
    }

    public function saveMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:transfer_methods,id'],
            'type' => ['required', 'in:bank,wallet,instapay,other'],
            'name_ar' => ['required', 'string', 'max:190'],
            'account_number' => ['nullable', 'string', 'max:190'],
            'beneficiary_name' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = collect($data)->except('id')->all();
        $payload['is_active'] = (bool) ($data['is_active'] ?? false);
        $payload['sort_order'] = (int) ($data['sort_order'] ?? 0);

        isset($data['id'])
            ? TransferMethod::query()->findOrFail($data['id'])->update($payload)
            : TransferMethod::create($payload);

        return back()->with('status', 'طريقة التحويل اتحفظت ✓');
    }

    public function deleteMethod(TransferMethod $transferMethod): RedirectResponse
    {
        $transferMethod->delete();

        return back()->with('status', 'الطريقة اتشالت ✓');
    }

    public function saveOffer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:topup_offers,id'],
            'method' => ['required', 'in:manual,gateway'],
            'label_ar' => ['required', 'string', 'max:190'],
            'pay_amount' => ['required', 'numeric', 'min:1'],
            'credit_amount' => ['required', 'numeric', 'min:1'],
            'is_popular' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = collect($data)->except('id')->all();
        // ⭐ نسبة الزيادة تُحسَب في الخادم وتُعرَض صراحةً — بلا مبالغة (2.9)
        $payload['bonus_percent'] = $this->gateway->bonusPercent((float) $data['pay_amount'], (float) $data['credit_amount']);
        $payload['is_popular'] = (bool) ($data['is_popular'] ?? false);
        $payload['is_active'] = (bool) ($data['is_active'] ?? false);
        $payload['sort_order'] = (int) ($data['sort_order'] ?? 0);

        isset($data['id'])
            ? TopupOffer::query()->findOrFail($data['id'])->update($payload)
            : TopupOffer::create($payload);

        return back()->with('status', 'العرض اتحفظ ✓');
    }

    public function deleteOffer(TopupOffer $topupOffer): RedirectResponse
    {
        $topupOffer->delete();

        return back()->with('status', 'العرض اتشال ✓');
    }

    // ---------------------------------------------------------------- بوّابة الدفع

    public function gateway(Request $request): View
    {
        return view('admin.store.topups.gateway', [
            'settings' => $this->gateway->settingsFor($request->user()),
            'labels' => GatewayAdminService::PUBLIC_KEYS,
            'secretKeys' => GatewayAdminService::SECRET_KEYS,
            // 🔒 المفاتيح لمالك المنصّة وحده — وغيره لا يرى الحقول أصلًا
            'maySeeSecrets' => $this->gateway->maySeeSecrets($request->user()),
            'registry' => $this->registry,
        ]);
    }

    public function saveGateway(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        $allowed = array_keys(GatewayAdminService::PUBLIC_KEYS);

        if ($this->gateway->maySeeSecrets($request->user())) {
            $allowed = array_merge($allowed, GatewayAdminService::SECRET_KEYS);
        }

        if (! in_array($data['key'], $allowed, true)) {
            return response()->json(['saved' => false, 'message' => 'المفتاح ده مش من إعدادات البوّابة المسموحة ليك.'], 403);
        }

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    public function testGateway(Request $request): JsonResponse
    {
        $result = $this->gateway->testConnection($request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** ⭐ عرض سجلّ الـWebhook الخام بنتيجة التحقّق من الهاش (19.5-ج-2) */
    public function webhookLogs(Request $request): View
    {
        return view('admin.store.topups.webhooks', [
            'logs' => $this->gateway->webhookLogs([
                'invoice' => $request->string('invoice')->toString() ?: null,
                'hash' => $request->string('hash')->toString() ?: null,
                'result' => $request->string('result')->toString() ?: null,
            ]),
        ]);
    }

    /** @return array<string,string> */
    private function statuses(): array
    {
        return [
            TopupService::PENDING => '🟡 قيد التحقّق',
            TopupService::COMPLETED => '🟢 مكتملة',
            TopupService::CANCELLED => '🔴 ملغاة',
            TopupService::DUPLICATE => '⚪ مكرَّرة',
        ];
    }
}
