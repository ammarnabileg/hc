<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * تحقّق البريد بـOTP عند التسجيل (2.5-ب).
 *
 * السلوك المنصوص: زرّ **«إرسال»** يتحوّل لـ**«تأكيد»** معطَّل حتى تُكتب الأرقام ·
 * **إعادة الإرسال بعد دقيقة بعدّاد تنازليّ** · و**الرمز لا يتغيّر أبدًا لنفس البريد**.
 * ورقم الموبايل يُجمَع **بلا** تحقّق OTP.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(Request $request): View|RedirectResponse
    {
        $pending = (array) $request->session()->get(RequireVerifiedEmail::SESSION_PENDING, []);
        $email = Str::lower(trim((string) ($pending['email'] ?? '')));

        if ($email === '') {
            return redirect()->route('register');
        }

        return view('security.verify-email', [
            'email' => $email,
            'length' => $this->otp->length(),
            'wait' => $this->otp->secondsUntilResend($email, OtpService::PURPOSE_REGISTER),
            'resendSeconds' => $this->otp->resendSeconds(),
            'sent' => $request->session()->get('otp_sent', false),
        ]);
    }

    /** [إرسال] — والرمز نفسه لنفس البريد مهما تكرّر الطلب (2.5-ب) */
    public function send(Request $request): RedirectResponse
    {
        $email = $this->pendingEmail($request);

        if ($email === null) {
            return redirect()->route('register');
        }

        $result = $this->otp->send($email, OtpService::PURPOSE_REGISTER);

        return back()->with([
            'otp_sent' => true,
            'status' => $result['sent']
                ? (string) setting('auth.otp.sent_text', 'بعتنا الرمز على بريدك. بصّ في «غير الهامّ» كمان.')
                : str_replace('{seconds}', (string) $result['wait'], (string) setting('auth.otp.wait_text', 'استنّى {seconds} ثانية قبل ما تطلب تاني.')),
        ]);
    }

    /**
     * [تأكيد] — وعند النجاح يُعاد إرسال بيانات التسجيل المحفوظة لمتحكّم التسجيل
     * كما كتبها المستخدم بالضبط، فلا يعيد ملء الفورم.
     */
    public function confirm(Request $request, AuthController $auth): RedirectResponse
    {
        $email = $this->pendingEmail($request);

        if ($email === null) {
            return redirect()->route('register');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:'.$this->otp->length()],
        ], [
            'code.required' => 'اكتب الرمز اللي وصلك الأوّل.',
            'code.size' => 'الرمز '.$this->otp->length().' أرقام بالظبط.',
        ]);

        $result = $this->otp->verify($email, OtpService::PURPOSE_REGISTER, $data['code']);

        if (! $result['ok']) {
            return back()->with('otp_sent', true)->withErrors(['code' => $result['message']]);
        }

        $request->session()->put(RequireVerifiedEmail::SESSION_VERIFIED, $email);

        return $this->replayRegistration($request, $auth);
    }

    // ------------------------------------------------------------------ داخليّ

    private function pendingEmail(Request $request): ?string
    {
        $pending = (array) $request->session()->get(RequireVerifiedEmail::SESSION_PENDING, []);
        $email = Str::lower(trim((string) ($pending['email'] ?? '')));

        return $email === '' ? null : $email;
    }

    /** إعادة تشغيل التسجيل بنفس المدخلات — ولو سقطت تحقّقات تانية نرجّعه للفورم برسائلها */
    private function replayRegistration(Request $request, AuthController $auth): RedirectResponse
    {
        $pending = (array) $request->session()->pull(RequireVerifiedEmail::SESSION_PENDING, []);

        // الريفيرال وباركامترات الحملة تُقرأ من الـQuery في متحكّم التسجيل — فنحفظها هناك
        $tracking = collect($pending)->only(['offer', 'utm_source', 'utm_medium', 'utm_campaign'])->filter()->all();
        $url = route('register').($tracking === [] ? '' : '?'.http_build_query($tracking));

        $replay = Request::create($url, 'POST', $pending);
        $replay->setLaravelSession($request->session());
        $replay->setUserResolver($request->getUserResolver());

        try {
            return $auth->register($replay);
        } catch (ValidationException $exception) {
            return redirect()->route('register')
                ->withInput(collect($pending)->except(['password', 'password_confirmation'])->all())
                ->withErrors($exception->errors());
        }
    }
}
