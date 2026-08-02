<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\Wallet\ExchangeService;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * العمليّات المالِيّة الثلاث (19.3): إرسال حوالة · تحويل العملة · سحب الأرباح.
 *
 * ⭐ **القاعدة الحاكمة هنا: لا سعر ولا رسم ولا صافي يُقبَل من الطلب.**
 * الطلب لا يحمل إلّا ما لا يمكن للخادم أن يعرفه وحده (المستلِم · العملة · الكمّيّة
 * · بيانات الحساب)، وكلّ رقمٍ ماليّ يُشتقّ في الخادم من الإعدادات وقت التنفيذ.
 * ولذلك فحتّى «الملخّص اللحظيّ» في البوب-أب يُطلَب من الخادم ولا يُحسَب في المتصفّح:
 * الرقم الذي يراه المستخدم قبل التأكيد هو نفسه الذي يُنفَّذ بعده.
 */
class WalletOperationsController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly ExchangeService $exchanges,
        private readonly WithdrawService $withdrawals,
    ) {}

    // -------------------------------------------------------------- الحوالة

    /** الملخّص اللحظيّ للحوالة — من الخادم لا من المتصفّح */
    public function quoteTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'in:'.implode(',', TransferService::CURRENCIES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $quote = $this->transfers->quote($data['currency'], (float) $data['amount']);
        } catch (WalletException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $recipient = null;

        if (! empty($data['code'])) {
            try {
                $found = $this->transfers->findRecipient($request->user(), $data['code']);
                // اسم المستلِم يظهر قابلًا للنقر للبروفايل (19.3)
                $recipient = ['name' => $found->name, 'code' => $found->code, 'url' => $this->profileUrl($found->code)];
            } catch (WalletException $e) {
                $recipient = ['error' => $e->getMessage()];
            }
        }

        return response()->json($quote + [
            'currency' => $this->currencyName($data['currency']),
            'recipient' => $recipient,
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'string', 'in:'.implode(',', TransferService::CURRENCIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $transfer = $this->transfers->send(
                $request->user(),
                $data['code'],
                $data['currency'],
                (float) $data['amount'],
            );
        } catch (WalletException $e) {
            return back()->withInput()->withErrors(['wallet' => $e->getMessage()]);
        }

        return back()->with('status', 'الحوالة اتبعتت ✓ — وصله '
            .$this->number((float) $transfer->net_amount).' '.$transfer->currency?->name_ar.'.');
    }

    // --------------------------------------------------------- تحويل العملة

    public function quoteExchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'in:'.implode(',', ExchangeService::FROM)],
            'to' => ['required', 'string', 'in:'.implode(',', ExchangeService::TO)],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $quote = $this->exchanges->quote($data['from'], $data['to'], (float) $data['amount']);
        } catch (WalletException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($quote + [
            'from_name' => $this->currencyName($data['from']),
            'to_name' => $this->currencyName($data['to']),
        ]);
    }

    public function exchange(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'in:'.implode(',', ExchangeService::FROM)],
            'to' => ['required', 'string', 'in:'.implode(',', ExchangeService::TO)],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $exchange = $this->exchanges->exchange(
                $request->user(),
                $data['from'],
                $data['to'],
                (float) $data['amount'],
            );
        } catch (WalletException $e) {
            return back()->withInput()->withErrors(['wallet' => $e->getMessage()]);
        }

        return back()->with('status', 'التحويل تمّ ✓ — اتضاف لك '
            .$this->number((float) $exchange->credited_amount).' '.$exchange->to_currency?->name_ar.'.');
    }

    // ---------------------------------------------------------- سحب الأرباح

    public function quoteWithdraw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->withdrawals->quote((float) $data['amount']) + [
            'available' => $this->withdrawals->available($request->user()),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(WithdrawService::METHODS))],
            'account_number' => ['required', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $withdrawal = $this->withdrawals->request(
                $request->user(),
                (float) $data['amount'],
                $data['method'],
                $data['account_number'],
                $data['account_name'] ?? null,
            );
        } catch (WalletException $e) {
            return back()->withInput()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()->route('wallet.withdrawals')->with('status',
            'طلب السحب '.$withdrawal->number.' اتسجّل ✓ — هيوصلك $'
            .$this->number((float) $withdrawal->net_amount).' بعد المراجعة.');
    }

    // ------------------------------------------------------------------ داخليّ

    private function currencyName(string $code): string
    {
        return (string) (Currency::query()->where('code', $code)->value('name_ar') ?? $code);
    }

    /** رابط البروفايل العامّ — وإن لم يكن مسجَّلًا في هذه البيئة نرجع بلا رابط */
    private function profileUrl(string $code): ?string
    {
        return Route::has('u.profile')
            ? route('u.profile', $code)
            : null;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
