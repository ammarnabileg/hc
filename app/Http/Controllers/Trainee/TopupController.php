<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\TransferMethod;
use App\Services\Wallet\GatewayService;
use App\Services\Wallet\TopupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * شحن الحساب (19.5): صفحة واحدة بتابين — تحويل يدويّ وبوّابة دفع.
 *
 * ⛔ ولا تُعلَن أيّ مهلة مراجعة للمُرسِل في أيّ نصّ هنا: العدّاد وتلوين المتأخّر
 *    أدوات داخليّة للأدمن وحده (19.5-أ).
 */
class TopupController extends Controller
{
    public function __construct(private readonly TopupService $topups) {}

    /** 🖥️ صفحة الشحن — التاب الافتراضيّ من لوحة الأدمن */
    public function index(Request $request)
    {
        $user = $request->user();
        $manualEnabled = (bool) setting('topup.manual.enabled', true);
        $gatewayEnabled = (bool) setting('topup.gateway.enabled', true);

        $default = (string) setting('topup.default_tab', 'manual');
        $tab = $request->string('tab')->toString() ?: $default;

        if (($tab === 'manual' && ! $manualEnabled) || ($tab === 'gateway' && ! $gatewayEnabled)) {
            $tab = $manualEnabled ? 'manual' : 'gateway';
        }

        // [عدّل وأعد الإرسال]: يعيد ملء الفورم من طلبٍ مقفول فلا يبدأ من الصفر (19.5-ب-5)
        $resend = $request->filled('resend')
            ? TopupRequest::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [TopupService::CANCELLED, TopupService::DUPLICATE])
                ->find($request->integer('resend'))
            : null;

        return view('wallet.topup', [
            'tab' => $tab,
            'manualEnabled' => $manualEnabled,
            'gatewayEnabled' => $gatewayEnabled,
            'transferMethods' => TransferMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'manualOffers' => $this->offers('manual'),
            'gatewayOffers' => $this->offers('gateway'),
            'pending' => $this->topups->pendingRequestFor($user),
            'resend' => $resend,
            'defaultPhone' => $resend?->contact_phone ?? $user->phone,
        ]);
    }

    /** إرسال طلب تحويل يدويّ */
    public function storeManual(Request $request)
    {
        $user = $request->user();

        if (! setting('topup.manual.enabled', true)) {
            return back()->with('status', (string) setting('topup.screen.store_manual_msg', 'التحويل اليدويّ متوقّف حاليًّا — جرّب بوّابة الدفع.'));
        }

        // ⭐ قفل: طلب معلَّق واحد لكلّ مستخدم — إمّا يلغي القديم أو ينتظر (19.5-ب-5)
        if ($this->topups->pendingRequestFor($user)) {
            return redirect()
                ->route('wallet.topup.requests')
                ->with('status', (string) setting('topup.screen.store_manual_msg_2', 'عندك طلب شحن قيد التحقّق. تقدر تتابعه من هنا، ولمّا يخلص ابعت التالي.'));
        }

        $data = $request->validate([
            'topup_offer_id' => ['nullable', Rule::exists('topup_offers', 'id')->where('method', 'manual')->where('is_active', true)],
            'transferred_amount' => ['required', 'numeric', 'min:'.(float) setting('topup.min_amount', 1)],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'transfer_method_id' => ['required', Rule::exists('transfer_methods', 'id')->where('is_active', true)],
            'contact_phone' => ['required', 'string', 'max:32'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) setting('topup.receipt.max_size_kb', 4096)],
        ], [], [
            'topup_offer_id' => (string) setting('topup.screen.store_manual_msg_3', 'عرض الشحن'),
            'transferred_amount' => (string) setting('topup.screen.store_manual_msg_4', 'القيمة المحوَّلة'),
            'paid_at' => (string) setting('topup.screen.store_manual_msg_5', 'وقت وتاريخ الدفع'),
            'transfer_method_id' => (string) setting('topup.screen.store_manual_msg_6', 'طريقة التحويل'),
            'contact_phone' => (string) setting('topup.screen.store_manual_msg_7', 'رقم التواصل'),
            'receipt' => (string) setting('topup.screen.store_manual_msg_8', 'صورة الإيصال'),
        ]);

        $topupRequest = $this->topups->submit($user, $data, $request->file('receipt'));

        return redirect()->route('wallet.topup.requests')->with(
            'status',
            $topupRequest->status === TopupService::DUPLICATE
                ? (string) setting('topup.screen.store_manual_msg_9', 'الإيصال ده مرفوع قبل كده، فالطلب اتوسم «مكرَّرة». ارفع إيصال العمليّة الصحيحة وابعت تاني.')
                : strtr((string) setting('topup.screen.store_manual_ok', 'استلمنا طلبك :a1 ✓ وهتوصلك إشعارات بأيّ تغيير في حالته.'), [':a1' => (string) ($topupRequest->number)]),
        );
    }

    /** بدء الدفع عبر البوّابة — والرصيد لا يُضاف هنا بل من الويب هوك وحده */
    public function startGateway(Request $request, GatewayService $gateway)
    {
        if (! setting('topup.gateway.enabled', true)) {
            return back()->with('status', (string) setting('topup.screen.start_gateway_msg', 'بوّابة الدفع متوقّفة حاليًّا — تقدر تستعمل التحويل اليدويّ.'));
        }

        $data = $request->validate([
            'topup_offer_id' => ['required', Rule::exists('topup_offers', 'id')->where('method', 'gateway')->where('is_active', true)],
        ]);

        $offer = TopupOffer::query()->findOrFail($data['topup_offer_id']);

        try {
            $invoice = $gateway->startInvoice($request->user(), $offer, [
                'successUrl' => route('wallet.topup.return', ['state' => 'success']),
                'failUrl' => route('wallet.topup.return', ['state' => 'fail']),
                'pendingUrl' => route('wallet.topup.return', ['state' => 'pending']),
            ]);
        } catch (Throwable $e) {
            report($e);

            // رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب)
            return back()->with('status', (string) setting('topup.screen.start_gateway_msg_2', 'ما قدرناش نفتح صفحة الدفع دلوقتي. جرّب تاني بعد شويّة أو استعمل التحويل اليدويّ.'));
        }

        if (! $invoice->payment_url) {
            return back()->with('status', (string) setting('topup.screen.start_gateway_msg_3', 'البوّابة ما رجّعتش رابط دفع. جرّب تاني أو استعمل التحويل اليدويّ.'));
        }

        return redirect()->away($invoice->payment_url);
    }

    /**
     * ⭐ رابط الرجوع: للعرض فقط ولا يزيد رصيدًا أبدًا (19.5-ج-2).
     * الرصيد ينزل من الويب هوك الموقَّع، فالصفحة هنا تطمئن المستخدم لا أكثر.
     */
    public function gatewayReturn(Request $request)
    {
        return view('wallet.topup-return', [
            'state' => $request->string('state')->toString() ?: 'pending',
        ]);
    }

    /** 🖥️ طلبات الشحن — تسلسل زمنيّ وسبب الإلغاء و[عدّل وأعد الإرسال] */
    public function requests(Request $request)
    {
        return view('wallet.topup-requests', [
            'rows' => TopupRequest::query()
                ->where('user_id', $request->user()->id)
                ->with(['topup_offer', 'transfer_method'])
                ->latest('id')
                ->paginate((int) setting('topup.history.per_page', 10)),
        ]);
    }

    /** عروض طريقةٍ بعينها — مرتّبة ومفعَّلة فقط، وقيمها تُقرأ من الخادم دائمًا */
    private function offers(string $method)
    {
        return TopupOffer::query()
            ->where('method', $method)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
