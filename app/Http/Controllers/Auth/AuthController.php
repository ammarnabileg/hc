<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Services\Learning\TimezoneDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * التسجيل والدخول — والتفعيل **مجّانيّ باعتماد إداريّ** (الدستور 2.5-د).
 * لا رسوم تفعيل ولا اشتراك دوريّ ولا أيّ حاجز ماليّ عند الباب.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL) ? 'email'
            : (preg_match('/^\+?\d[\d\s-]{6,}$/', $data['identifier']) ? 'phone' : 'code');

        if (! Auth::attempt([$field => $data['identifier'], 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput()->withErrors([
                'identifier' => 'البيانات مش مظبوطة. راجع الكود أو البريد وكلمة السرّ.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        // كشف الدولة/المنطقة الزمنيّة تلقائيًّا عند الدخول — والتعديل اليدويّ يبقى أعلى (5)
        app(TimezoneDetector::class)->sync($request, $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', ['referral' => $request->query('offer')]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'country_id' => ['nullable', 'exists:countries,id'],
            'governorate_id' => ['nullable', 'exists:governorates,id'],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                ...$data,
                'code' => $this->generateCode(),
                // الحساب يبدأ «تحت المراجعة» ويُفعَّل باعتماد إداريّ مجّانًا
                'status' => 'pending',
            ]);

            if ($role = Role::where('key', 'pending_review')->first()) {
                $user->assignRole($role);
            }

            // الريفيرال (7.6 · 21.1): مكافأة الطرفين — والمدعوّ له تذكرة ترحيب عند التفعيل
            if ($code = $request->input('offer')) {
                if ($referrer = User::where('code', $code)->first()) {
                    Referral::create([
                        'referrer_id' => $referrer->id,
                        'referred_id' => $user->id,
                        'code' => $code,
                        'commission_percent' => (float) setting('referral.commission_percent', 7),
                        'utm_source' => $request->query('utm_source'),
                        'utm_medium' => $request->query('utm_medium'),
                        'utm_campaign' => $request->query('utm_campaign'),
                    ]);
                }
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        // ونفس الكشف عند التسجيل — فأوّل شاشة يراها تُحسَب بساعته هو (5)
        app(TimezoneDetector::class)->sync($request, $user);

        return redirect()->route('account.pending');
    }

    public function pending(): View|RedirectResponse
    {
        if (auth()->user()->isActive()) {
            return redirect()->route('dashboard');
        }

        return view('auth.pending');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function generateCode(): string
    {
        $prefix = (string) setting('accounts.code.prefix', 'U');

        do {
            $code = $prefix.Str::upper(Str::random(7));
        } while (User::where('code', $code)->exists());

        return $code;
    }
}
