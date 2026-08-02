<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Services\Learning\TimezoneDetector;
use App\Services\Onboarding\OnboardingJourney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /**
     * الدخول (2.3): **الجلسة تُحفَظ مدى الحياة ولا تنتهي تلقائيًّا** — تفضل
     * مفتوحة حتى يعمل المستخدم «تسجيل خروج» بنفسه.
     *
     * ⭐ لذلك «فكّرني» ليس اختيارًا يُنسى: الدخول يصدر **Remember-me token آمن**
     * دائمًا (وهو ما نصّت عليه الملاحظة الأمنيّة في البند نفسه). بلا هذا كان
     * المستخدم يُطرَد مع أوّل انتهاء جلسة — وهو ما يكسر الستريكس ونادي الخامسة
     * صباحًا مباشرةً: مَن يُطرَد فجرًا لا يسجّل حضوره.
     */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL) ? 'email'
            : (preg_match('/^\+?\d[\d\s-]{6,}$/', $data['identifier']) ? 'phone' : 'code');

        // الافتراضيّ إبقاء الدخول مفتوحًا، والمالك يقدر يوقفه من اللوحة (2.13)
        $remember = setting('auth.session.remember_always', true)
            ? true
            : $request->boolean('remember');

        if (! Auth::attempt([$field => $data['identifier'], 'password' => $data['password']], $remember)) {
            return back()->withInput()->withErrors([
                'identifier' => 'البيانات مش مظبوطة. راجع الكود أو البريد وكلمة السرّ.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        // كشف الدولة/المنطقة الزمنيّة تلقائيًّا عند الدخول — والتعديل اليدويّ يبقى أعلى (5)
        app(TimezoneDetector::class)->sync($request, $request->user());

        // مَن لم يُتمّ رحلته يُكمِلها من حيث وقف لا من الرئيسيّة (2.5-د)
        return redirect()->intended(route(app(OnboardingJourney::class)->routeFor($request->user())));
    }

    /**
     * صفحة التسجيل = **صفحة المعلومات** (2.5-ج): «بيانات الشهادات والإفادات».
     * ومن قبلها شاشة «هل دعاك شخص ما؟» (2.5-أ) إلّا لمن دخل برابط دعوة.
     */
    public function showRegister(Request $request): View|RedirectResponse
    {
        // رابط الدعوة يتخطّى الشاشة ويحفظ آيدي الداعي في السيشن (2.5-أ)
        if ($code = trim((string) $request->query('offer'))) {
            $request->session()->put([
                OnboardingController::SESSION_CODE => $code,
                OnboardingController::SESSION_ANSWERED => true,
            ]);
        }

        if (setting('onboarding.referral.enabled', true)
            && ! $request->session()->get(OnboardingController::SESSION_ANSWERED)) {
            return redirect()->route('onboarding.referral');
        }

        return view('auth.register', [
            'referral' => (string) $request->session()->get(OnboardingController::SESSION_CODE, ''),
            'titles' => $this->titleGroups(),
            'countries' => Country::where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'phone_code']),
            'governorates' => Governorate::where('is_active', true)->orderBy('sort_order')->get(['id', 'country_id', 'name_ar']),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        // «الاسم» المعروض في المنصّة = الاسم بالعربيّ، والإنجليزيّ بديله عند غيابه
        $data['name'] = $data['name_ar'] ?: $data['name_en'];

        $offer = trim((string) ($request->input('offer')
            ?: $request->session()->get(OnboardingController::SESSION_CODE, '')));

        $user = DB::transaction(function () use ($data, $request, $offer) {
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
            if ($offer !== '') {
                if ($referrer = User::where('code', $offer)->first()) {
                    Referral::create([
                        'referrer_id' => $referrer->id,
                        'referred_id' => $user->id,
                        'code' => $offer,
                        'commission_percent' => (float) setting('referral.commission_percent', 7),
                        'utm_source' => $request->query('utm_source'),
                        'utm_medium' => $request->query('utm_medium'),
                        'utm_campaign' => $request->query('utm_campaign'),
                    ]);
                }
            }

            return $user;
        });

        // نفس قاعدة 2.3 عند التسجيل: الجلسة تبدأ دائمةً لا مؤقّتة
        Auth::login($user, (bool) setting('auth.session.remember_always', true));
        $request->session()->regenerate();
        $request->session()->forget([OnboardingController::SESSION_CODE, OnboardingController::SESSION_ANSWERED]);

        // ونفس الكشف عند التسجيل — فأوّل شاشة يراها تُحسَب بساعته هو (5)
        app(TimezoneDetector::class)->sync($request, $user);

        // ⭐ الترتيب المنصوص: تعليمات ⟵ اختبار تمهيديّ ⟵ تحت المراجعة (2.5-د)
        return redirect()->route(app(OnboardingJourney::class)->routeFor($user));
    }

    /** صفحة «تحت المراجعة» (2.5-د-3) — ومحتواها HTML يكتبه الأدمن */
    public function pending(): View|RedirectResponse
    {
        $journey = app(OnboardingJourney::class);
        $user = auth()->user();

        if (! $journey->isAt($user, OnboardingJourney::STEP_REVIEW)) {
            return redirect()->route($journey->routeFor($user));
        }

        return view('auth.pending');
    }

    /** «تسجيل الخروج» يبقى **فعلًا صريحًا** — وهو وحده ما ينهي الجلسة (2.3) */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * تحقّقات صفحة المعلومات (2.5-ج) — والرسائل تقول ماذا حدث وماذا تفعل (2.17-ب).
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $words = max(1, (int) setting('onboarding.identity.name_words', 3));
        $locationRequired = setting('onboarding.identity.location_required', true)
            && Country::where('is_active', true)->exists();

        $titles = $this->titles();

        return [
            // اللقب: من قائمة الأدمن المجمَّعة لا نصًّا حرًّا — وقائمةٌ فارغة لا تقفل
            // الباب على المسجّلين، فالقائمة إعدادٌ قد يفرغه المالك سهوًا (2.13)
            'title' => array_filter(['required', 'string', 'max:64', $titles === [] ? null : Rule::in($titles)]),

            // الاسم بالعربيّ ثلاثيًّا — وحروفه عربيّة (2.5-ج)
            'name_ar' => ['required', 'string', 'max:190', 'regex:/^[\p{Arabic}\s\x{0640}]+$/u', $this->wordsRule($words)],

            // الاسم بالإنجليزيّ ثلاثيًّا — وحروفه لاتينيّة
            'name_en' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z\s\'\-\.]+$/', $this->wordsRule($words)],

            'gender' => ['required', Rule::in(['male', 'female'])],
            'country_id' => [$locationRequired ? 'required' : 'nullable', 'exists:countries,id'],
            'governorate_id' => [$locationRequired ? 'required' : 'nullable', 'exists:governorates,id'],
            'address_line' => ['required', 'string', 'max:255'],

            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /** «ثلاثيّ» = عدد كلمات لا يقلّ عن المضبوط في اللوحة (2.13) */
    private function wordsRule(int $words): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($words) {
            if (count(preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: []) < $words) {
                $fail('الاسم لازم يكون '.$words.' كلمات على الأقلّ.');
            }
        };
    }

    private function messages(): array
    {
        return [
            'required' => 'الحقل ده مطلوب — من غيره الشهادة هتطلع ناقصة.',
            'name_ar.regex' => (string) setting('onboarding.identity.name_ar_error', 'اكتب اسمك ثلاثيًّا بالعربيّ — الشهادة هتطلع بالاسم ده.'),
            'name_en.regex' => (string) setting('onboarding.identity.name_en_error', 'اكتب اسمك ثلاثيًّا بالإنجليزيّ — النسخة الإنجليزيّة من الشهادة بتطلع بيه.'),
            'title.in' => 'اختار لقبًا من القائمة.',
            'gender.in' => 'اختار النوع من الخيارين.',
            'email.unique' => 'البريد ده مستعمَل قبل كده — ادخل بيه أو استرجع كلمة السرّ.',
            'phone.unique' => 'الرقم ده مسجَّل قبل كده — جرّب رقمًا تانيًا.',
            'password.confirmed' => 'الكلمتان مش متطابقتين — راجعهم وجرّب تاني.',
        ];
    }

    private function attributes(): array
    {
        return [
            'title' => 'اللقب',
            'name_ar' => 'الاسم بالعربيّ',
            'name_en' => 'الاسم بالإنجليزيّ',
            'gender' => 'النوع',
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
            'address_line' => 'العنوان الفرعيّ',
            'email' => 'البريد الإلكترونيّ',
            'phone' => 'رقم الموبايل',
            'password' => 'كلمة السرّ',
        ];
    }

    /**
     * الألقاب مجمَّعةً بمجموعاتها (عامّة · مهنيّة · أكاديميّة) — **قابلة للامتداد**
     * من اللوحة كما نصّ البند، فلا قائمة محروقة في الكود (2.13).
     *
     * @return array<string, array<int, string>>
     */
    private function titleGroups(): array
    {
        $groups = setting('onboarding.identity.titles', []);

        if (! is_array($groups)) {
            return [];
        }

        $clean = [];

        foreach ($groups as $label => $titles) {
            $titles = array_values(array_filter(array_map('strval', (array) $titles)));

            if ($titles !== []) {
                $clean[(string) $label] = $titles;
            }
        }

        return $clean;
    }

    /** @return array<int, string> */
    private function titles(): array
    {
        $groups = $this->titleGroups();

        return $groups === [] ? [] : array_merge(...array_values($groups));
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
