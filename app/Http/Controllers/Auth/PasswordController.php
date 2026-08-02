<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\OtpService;
use App\Services\Security\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * «نسيت كلمة السرّ» (2.3): طلب ⟵ رمز/رابط ⟵ إعادة تعيين.
 *
 * كلّ النصوص من الإعدادات (2.13)، و**الردّ محايد دائمًا** فلا تكشف الشاشة
 * مَن عنده حساب ومَن لا.
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwords,
        private readonly OtpService $otp,
    ) {}

    public function request(): View
    {
        return view('auth.password.request');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ], [
            'email.required' => 'اكتب بريدك الأوّل.',
            'email.email' => 'الصيغة دي مش بريد صحيح — راجعها وجرّب تاني.',
        ]);

        $result = $this->passwords->request($data['email']);

        return redirect()->route('password.sent', ['email' => Str::lower(trim($data['email']))])
            ->with('status', $result['message']);
    }

    /** شاشة «بعتنالك» — فيها مدخل الرمز الرباعيّ لمن يفتح بريده على جهاز تاني */
    public function sent(Request $request): View|RedirectResponse
    {
        $email = Str::lower(trim((string) $request->query('email')));

        if ($email === '') {
            return redirect()->route('password.request');
        }

        return view('auth.password.sent', [
            'email' => $email,
            'length' => $this->otp->length(),
            'wait' => $this->otp->secondsUntilResend($email, OtpService::PURPOSE_PASSWORD),
            'resendSeconds' => $this->otp->resendSeconds(),
            'ttlMinutes' => $this->passwords->ttlMinutes(),
        ]);
    }

    /** شاشة إعادة التعيين — تُفتَح برابط البريد أو برمز رباعيّ صحيح */
    public function reset(Request $request): View|RedirectResponse
    {
        $email = Str::lower(trim((string) $request->query('email')));
        $token = (string) $request->route('token');

        if ($email === '' || ! $this->passwords->tokenIsValid($email, $token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => (string) setting('auth.password_reset.expired_text', 'الرابط ده انتهت صلاحيّته. اطلب واحدًا جديدًا — بياخد ثانية.'),
            ]);
        }

        return view('auth.password.reset', ['email' => $email, 'token' => $token]);
    }

    /** تبديل الرمز الرباعيّ برابط إعادة التعيين — نفس الطلب ونفس الشاشة */
    public function exchangeCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code' => ['required', 'string', 'size:'.$this->otp->length()],
        ], [
            'code.required' => 'اكتب الرمز اللي وصلك.',
            'code.size' => 'الرمز '.$this->otp->length().' أرقام بالظبط.',
        ]);

        $email = Str::lower(trim($data['email']));
        $result = $this->otp->verify($email, OtpService::PURPOSE_PASSWORD, $data['code']);

        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return back()->withErrors(['code' => $result['message']]);
        }

        // رمزٌ صحيح = صاحب البريد — فنُصدر له توكن الشاشة التالية
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => bcrypt($token), 'created_at' => now()],
        );

        return redirect()->to($this->passwords->resetUrl($email, $token));
    }

    /** أدنى طول لكلمة السرّ — إعدادٌ يعدّله المالك، وافتراضيّه «أكثر من 6» (12.1) */
    private function minLength(): int
    {
        return max(1, (int) setting('auth.password.min_length', 7));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            /*
             | 12.1-الأمان حرفيًّا: «**بدون شروط غير أن تكون أكثر من 6 خانات**».
             | فلا `mixedCase()` ولا `symbols()` ولا `uncompromised()` — الطول وحده،
             | وحدُّه من الإعدادات لا محروقًا (2.13). كان 8 بلا سندٍ من النصّ.
             */
            'password' => ['required', 'confirmed', Password::min($this->minLength())],
        ], [
            'password.required' => 'اكتب كلمة السرّ الجديدة.',
            'password.confirmed' => 'الكلمتان مش متطابقتين — راجعهم وجرّب تاني.',
            'password.min' => 'كلمة السرّ لازم تبقى '.$this->minLength().' خانات على الأقلّ ومفيش أيّ شرط تاني.',
        ]);

        $email = Str::lower(trim($data['email']));

        if (! $this->passwords->tokenIsValid($email, $data['token'])) {
            return redirect()->route('password.request')->withErrors([
                'email' => (string) setting('auth.password_reset.expired_text', 'الرابط ده انتهت صلاحيّته. اطلب واحدًا جديدًا — بياخد ثانية.'),
            ]);
        }

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->passwords->reset($user, $data['password']);

        return redirect()->route('login')->with(
            'status',
            (string) setting('auth.password_reset.done_text', 'كلمة السرّ اتغيّرت ✓ — ادخل بيها دلوقتي. وقفلنا كلّ الجلسات القديمة للأمان.'),
        );
    }
}
