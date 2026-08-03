<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    /**
     * [إرسال] — والرمز نفسه لنفس البريد مهما تكرّر الطلب (2.5-ب).
     *
     * ⭐ والبريد يُقرأ **من الحقل الذي كتبه المستخدم للتوّ** لا من السيشن وحده:
     * النصّ يضع حقل الرمز **تحت الإيميل في الصفحة نفسها**، فالزرّ يُضغَط والبريد
     * ما زال في يد الفورم لا في جلسةٍ سبقت. ولا يُبعَث رمزٌ لبريدٍ **مرفوض
     * أصلًا** (صيغةً أو لأنّه مستعمَل) — «تحقق الإيميل لحظيًا» يسبق الإرسال.
     */
    public function send(Request $request): RedirectResponse
    {
        $email = $this->emailInPlay($request);

        if ($email === null) {
            return back()->withInput($this->safeInput($request))->withErrors([
                'email' => (string) setting('onboarding.account.email_invalid', 'الشكل ده مش بريد صالح — راجع الكتابة.'),
            ]);
        }

        if (User::query()->where('email', $email)->withTrashed()->exists()) {
            return back()->withInput($this->safeInput($request))->withErrors([
                'email' => (string) setting('onboarding.account.email_taken', 'البريد ده مستعمَل قبل كده — ادخل بيه أو استرجع كلمة السرّ.'),
            ]);
        }

        $result = $this->otp->send($email, OtpService::PURPOSE_REGISTER);

        return back()->withInput($this->safeInput($request))->with([
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
        $email = $this->emailInPlay($request);

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
            return back()->withInput($this->safeInput($request))->with('otp_sent', true)->withErrors(['code' => $result['message']]);
        }

        $request->session()->put(RequireVerifiedEmail::SESSION_VERIFIED, $email);

        /*
         | الرمز في مكانه المنصوص (**تحت الإيميل في الشاشة الأولى**)، فالتأكيد
         | يرجّع المستخدم إلى **نفس الشاشة** بمدخلاته كما تركها ليضغط «استكمال»
         | — لا يقفز به إلى إنشاء الحساب من وراء بقيّة الفورم.
         |
         | و«الإعادة» تبقى للحالة القديمة وحدها: حمولةُ تسجيلٍ **كاملة** حُجِزت
         | في السيشن قبل التأكيد (الميدل وير) — يُعرَف كمالها بحقلٍ من الشاشة
         | الثانية. فلا يُهدَر طريقٌ قطعه مستخدمٌ على المسار القديم.
         */
        $pending = (array) $request->session()->get(RequireVerifiedEmail::SESSION_PENDING, []);

        if (! array_key_exists('name_ar', $pending)) {
            return back()->withInput($this->safeInput($request))->with('status', (string) setting('auth.otp.success', 'اتأكّد ✓'));
        }

        return $this->replayRegistration($request, $auth);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * المدخلات التي تعود للفورم بعد الرحلة — **بلا كلمة السرّ**.
     *
     * ⚠️ `withInput()` العارية تُومِض **كلّ** الطلب في السيشن، والفورم هنا يحمل
     * كلمة السرّ (فالـOTP تحت الإيميل في نفس الصفحة). فتُستثنى صراحةً: الاستثناء
     * الذي يفعله معالج Laravel للتحقّق الفاشل لا يسري على إعادة توجيهٍ نكتبها.
     *
     * @return array<string, mixed>
     */
    private function safeInput(Request $request): array
    {
        return $request->except(['password', 'password_confirmation', '_token', 'code']);
    }

    /**
     * البريد المقصود الآن: ما كتبه المستخدم في الفورم، وإلّا ما حُجِز في السيشن.
     * ويُحفَظ في السيشن فورًا ليصمد عبر خطوة «إرسال ⟵ تأكيد».
     */
    private function emailInPlay(Request $request): ?string
    {
        $pending = (array) $request->session()->get(RequireVerifiedEmail::SESSION_PENDING, []);
        $email = Str::lower(trim((string) ($request->input('email') ?: ($pending['email'] ?? ''))));

        if ($email === '' || Validator::make(['email' => $email], ['email' => ['email', 'max:190']])->fails()) {
            return null;
        }

        $request->session()->put(RequireVerifiedEmail::SESSION_PENDING, ['email' => $email] + $pending);

        return $email;
    }

    /**
     * إعادة تشغيل التسجيل بنفس المدخلات — والحمولة تُقرأ ولا تُسحَب، فلا تضيع
     * لو سقطت الإعادة (وهو ما كان يُفقِد المستخدم مدخلاته في أوّل تعثّر).
     *
     * والحارس صار يتحقّق من الفورم كاملًا قبل إرسال الرمز، فالسقوط هنا لم يعد
     * خطأ كتابةٍ يقع فيه المستخدم، بل تغيّرٌ بين الخطوتين (رقمٌ سُجِّل قبله مثلًا)
     * — فنقول له ماذا حدث وماذا يفعل بدل تحويلةٍ صامتة (2.17-ب).
     */
    private function replayRegistration(Request $request, AuthController $auth): RedirectResponse
    {
        $pending = (array) $request->session()->get(RequireVerifiedEmail::SESSION_PENDING, []);

        // الريفيرال وباركامترات الحملة تُقرأ من الـQuery في متحكّم التسجيل — فنحفظها هناك
        $tracking = collect($pending)->only(['offer', 'utm_source', 'utm_medium', 'utm_campaign'])->filter()->all();
        $url = route('register').($tracking === [] ? '' : '?'.http_build_query($tracking));

        $replay = Request::create($url, 'POST', $pending);
        $replay->setLaravelSession($request->session());
        $replay->setUserResolver($request->getUserResolver());

        try {
            $response = $auth->register($replay);

            $request->session()->forget(RequireVerifiedEmail::SESSION_PENDING);

            return $response;
        } catch (ValidationException $exception) {
            return redirect()->route('register')
                ->withInput(collect($pending)->except(['password', 'password_confirmation'])->all())
                // نفس ما يفعله معالج Laravel نفسه: مزوّد الرسائل وحقيبته لا مصفوفة
                // مسطّحة — فيصل الخطأ بشكله القياسيّ لكلّ من يقرؤه (بليد أو اختبار)
                ->withErrors($exception->validator, $exception->errorBag)
                ->with('status', (string) setting(
                    'auth.otp.replay_failed_text',
                    'بريدك اتأكّد ✓ بس فيه بيانات اتغيّرت وإحنا بنكمّل — صحّح المكتوب بالأحمر واضغط استكمال، ومش هنطلب منك الرمز تاني.',
                ));
        }
    }
}
