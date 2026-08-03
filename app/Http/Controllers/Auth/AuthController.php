<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Services\Growth\AcquisitionSource;
use App\Services\Learning\TimezoneDetector;
use App\Services\Onboarding\OnboardingJourney;
use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
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
    /** ما تحفظه **الشاشة الأولى (2.5-ب)** حتى تُنشَأ الحساب في الثانية */
    public const SESSION_ACCOUNT = 'auth.register.account';

    public const STEP_ACCOUNT = 'account';

    public const STEP_IDENTITY = 'identity';

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
     * ⭐ رحلة التسجيل **شاشتان لا شاشة** — كما ينصّ 2.5 حرفيًّا:
     *
     *   «**ب) صفحة التسجيل الأساسية:** الحقول: **الإيميل** + **رقم الموبايل**
     *    مع **Select لأكواد الدول (بالأعلام، بشكل احترافي)** + **الباسوورد**.»
     *   «**ج) صفحة المعلومات (بيانات الشهادات والإفادات):** اللقب … الاسم
     *    بالعربي … الاسم بالإنجليزي … النوع … الدولة … المحافظة … العنوان
     *    الفرعي … **زر الاستكمال معطّل** حتى تكتمل **كل** البيانات.»
     *
     * بندان بحرفين وعنوانين وحقول لا تتقاطع = **شاشتان**. وكانتا مدموجتين في
     * فورم واحد اسمه «صفحة المعلومات» يحمل حقول الشاشتين معًا، فسقط معنى
     * «التحقّق بالـOTP للإيميل» الذي **يقع في الشاشة الأولى قبل الثانية**.
     *
     * والشاشتان على **مسارٍ واحد** (`GET /register`) لأنّ `routes/**` تحت يد
     * إيجنتات أخرى الآن ولا يجوز لمسه: الحالة في السيشن هي التي تحكم أيّ شاشة
     * تُعرَض — والمستخدم لا يرى الثانية قبل أن يُتمّ الأولى ويؤكّد بريده.
     */
    public function showRegister(Request $request): View|RedirectResponse|JsonResponse
    {
        // رابط الدعوة يتخطّى الشاشة ويحفظ آيدي الداعي في السيشن (2.5-أ)
        if ($code = trim((string) $request->query('offer'))) {
            $request->session()->put([
                OnboardingController::SESSION_CODE => $code,
                OnboardingController::SESSION_ANSWERED => true,
            ]);
        }

        /*
         | ردودٌ صغيرة على نفس المسار — بلا إضافة مسارٍ في `routes/**`:
         |  · `?probe=email` ⟵ «تحقّق الإيميل **لحظيًّا**: صيغة صحيحة + **غير
         |    مستخدم من قبل**» (2.5-ب) — والصفحة لا تُعاد تحميلها لأجل سؤال.
         |  · `?governorates=<id>` ⟵ «المحافظة Select **مبني على الدولة**، يُملأ
         |    **تلقائيًّا**» (2.5-ج). و**5,249 محافظة** لا تُطبَع كلّها في الصفحة:
         |    كان ذلك مقبولًا بمحافظةٍ واحدة، أمّا الآن فهي مئاتُ الكيلوبايتات
         |    على هاتفٍ في 375px — وهو ما تمنعه 2.7 صراحةً.
         */
        if ($request->wantsJson() && $request->query('probe') === 'email') {
            return $this->probeEmail($request);
        }

        if ($request->wantsJson() && $request->filled('governorates')) {
            return response()->json(['governorates' => $this->governoratesOf((int) $request->query('governorates'))]);
        }

        if (setting('onboarding.referral.enabled', true)
            && ! $request->session()->get(OnboardingController::SESSION_ANSWERED)) {
            return redirect()->route('onboarding.referral');
        }

        // «ارجع عدّله» — الرجوع للشاشة الأولى فعلٌ صريح لا فقدٌ للبيانات
        if ($request->boolean('back')) {
            $request->session()->forget(self::SESSION_ACCOUNT);

            return redirect()->route('register');
        }

        $account = (array) $request->session()->get(self::SESSION_ACCOUNT, []);

        // (ج) لا تُفتَح إلّا بعد أن تكتمل (ب) **ويتأكّد البريد** — لا بالرابط ولا بالرجوع
        if ($account !== [] && $this->emailVerified($request, (string) ($account['email'] ?? ''))) {
            return view('auth.register.identity', [
                'referral' => (string) $request->session()->get(OnboardingController::SESSION_CODE, ''),
                'account' => $account,
                'titles' => $this->titleGroups(),
                'countries' => $this->countries(),
                'governorates' => $this->governoratesOf((int) (old('country_id') ?: 0)),
            ]);
        }

        $otp = app(OtpService::class);
        $email = Str::lower(trim((string) (old('email') ?: ($account['email'] ?? ''))));

        return view('auth.register.account', [
            'referral' => (string) $request->session()->get(OnboardingController::SESSION_CODE, ''),
            'dialCodes' => $this->dialCodes(),
            'defaultIso2' => mb_strtoupper((string) setting('countries.registration.default_iso2', 'EG')),
            'showDialCode' => (bool) setting('countries.registration.phone_code', true),
            'dialWidth' => max(80, (int) setting('countries.registration.phone_code_width', 110)),
            'otpLength' => $otp->length(),
            'resendSeconds' => $otp->resendSeconds(),
            'otpWait' => $email === '' ? 0 : $otp->secondsUntilResend($email, OtpService::PURPOSE_REGISTER),
            'otpSent' => $email !== '' && $otp->wasSent($email, OtpService::PURPOSE_REGISTER),
            'otpVerified' => $email !== '' && $this->emailVerified($request, $email),
        ]);
    }

    /**
     * (ب) ⟵ (ج) ⟵ إنشاء الحساب. الخطوة تُقرأ من الطلب، والافتراضيّ **الأولى**
     * فحمولةٌ بلا خطوة لا تنشئ حسابًا من وراء الشاشة الأولى.
     */
    public function register(Request $request): RedirectResponse
    {
        if ($request->input('step') === self::STEP_IDENTITY) {
            return $this->registerIdentity($request);
        }

        if ($request->input('step') === self::STEP_ACCOUNT) {
            return $this->registerAccount($request);
        }

        /*
         | حمولةٌ بلا خطوة وفيها حقول **الشاشتين معًا**: هذا هو مسار الحارس
         | (`RequireVerifiedEmail`) حين يحجز فورمًا كاملًا قبل التأكيد ثمّ يعيد
         | تشغيله بعده. يبقى عاملًا كما هو — والفصل لم يُلغِ بابًا قائمًا، بل
         | جعل الشاشتين هما الطريق الافتراضيّ.
         |
         | ⛔ وبنفس حارس الـOTP: لا إنشاء بلا بريدٍ مؤكَّد مهما كان الباب.
         */
        if ($request->filled('name_ar') && $request->filled('email')) {
            return $this->registerCombined($request);
        }

        return $this->registerAccount($request);
    }

    /** الفورم الكامل في طلبٍ واحد (مسار إعادة تشغيل الحارس) — بنفس التحقّقات. */
    private function registerCombined(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        if (! $this->emailVerified($request, (string) $data['email'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->withErrors(['code' => (string) setting(
                    'auth.otp.error_not_verified',
                    'أكّد بريدك الأوّل: اضغط «إرسال» واكتب الرمز اللي هيوصلك، وبعدها كمّل.',
                )]);
        }

        return $this->createAccount($request, $data);
    }

    /**
     * **(ب) صفحة التسجيل الأساسية** — البريد والموبايل بكود دولته والباسوورد.
     *
     * ⛔ ولا خطوةَ بعدها بلا OTP: «**التحقق بالـ OTP للإيميل فقط**» (2.5-ب).
     * الحارس هنا لا في الميدل وير وحده، لأنّ الميدل وير يمرّر حمولةً ناقصة
     * الحقول عمدًا (ليقول المتحكّم خطأها) — فلو كان هو الحارس الوحيد لمرّت.
     */
    private function registerAccount(Request $request): RedirectResponse
    {
        $data = $request->validate($this->accountRules(), $this->messages(), $this->attributes());

        $email = Str::lower(trim($data['email']));

        if (! $this->emailVerified($request, $email)) {
            // ⚠️ بلا كلمة السرّ: `withInput()` العارية تُومِضها في السيشن
            return back()->withInput($request->except(['password', 'password_confirmation', '_token']))->withErrors([
                'code' => (string) setting(
                    'auth.otp.error_not_verified',
                    'أكّد بريدك الأوّل: اضغط «إرسال» واكتب الرمز اللي هيوصلك، وبعدها كمّل.',
                ),
            ]);
        }

        $request->session()->put(self::SESSION_ACCOUNT, [
            'email' => $email,
            'phone' => $this->fullPhone($data),
            'phone_iso2' => mb_strtoupper((string) ($data['phone_iso2'] ?? '')),
            'password' => $data['password'],
            'password_confirmation' => $data['password'],
        ]);

        return redirect()->route('register');
    }

    /** **(ج) صفحة المعلومات (بيانات الشهادات والإفادات)** — وعندها يُنشَأ الحساب. */
    private function registerIdentity(Request $request): RedirectResponse
    {
        $account = (array) $request->session()->get(self::SESSION_ACCOUNT, []);
        $email = Str::lower(trim((string) ($account['email'] ?? '')));

        // الرجوع للخطوة الأولى لا يُقال صامتًا: يُقال ماذا حدث وماذا يفعل (2.17-ب)
        if ($email === '' || ! $this->emailVerified($request, $email)) {
            return redirect()->route('register')->with('status', (string) setting(
                'auth.otp.error_step_lost',
                'الجلسة رجعت لأوّل خطوة — اكتب بريدك وأكّده تاني وهنكمّل من هناك.',
            ));
        }

        $identity = $request->validate($this->identityRules(), $this->messages(), $this->attributes());

        $data = $identity + [
            'email' => $email,
            'phone' => (string) ($account['phone'] ?? ''),
            'password' => (string) ($account['password'] ?? ''),
        ];

        // الحمولتان معًا تُسألان بنفس قواعد الشاشتين — فلا يُنشَأ حسابٌ بحقلٍ
        // مرّ من شاشةٍ ثمّ بطل بين الخطوتين (رقمٌ سُجِّل لغيره مثلًا)
        Validator::make(
            $data + ['password_confirmation' => $data['password']],
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        )->validate();

        return $this->createAccount($request, $data);
    }

    /** @param  array<string, mixed>  $data */
    private function createAccount(Request $request, array $data): RedirectResponse
    {
        // «الاسم» المعروض في المنصّة = الاسم بالعربيّ، والإنجليزيّ بديله عند غيابه
        $data['name'] = $data['name_ar'] ?: $data['name_en'];

        $offer = trim((string) ($request->input('offer')
            ?: $request->session()->get(OnboardingController::SESSION_CODE, '')));

        $acquisition = app(AcquisitionSource::class);

        $user = DB::transaction(function () use ($data, $offer, $acquisition) {
            $user = User::create([
                ...$data,
                'code' => $this->generateCode(),
                // الحساب يبدأ «تحت المراجعة» ويُفعَّل باعتماد إداريّ مجّانًا
                'status' => 'pending',
            ]);

            if ($role = Role::where('key', 'pending_review')->first()) {
                $user->assignRole($role);
            }

            /*
             | ⭐ **الوصلة الثانية في سلسلة 21.2-ح**: «المصدر ⟵ **التسجيل** ⟵ التفعيل ⟵ الشراء».
             |
             | كانت مقطوعة هنا بالضبط: `POST /register` يأتي **بلا query**، فكلّ قراءةٍ
             | للمصدر من `$request->query('utm_*')` تعود `NULL` — فيُسجَّل المسجّل بلا
             | مصدر (المقيس: `visits=2 · registered=0`، و**0 من 10 إحالة تحمل مصدرًا**).
             | فالمصدر يُقرأ الآن ممّا التُقِط عند **الزيارة** ويُثبَّت على الحساب.
             |
             | ⛔ ولا شيء من هذا يقع بلا موافقةٍ على **القياس الداخليّ** (21.3-د):
             |    الحارس داخل `attach()` نفسه، فالرافض يُسجَّل بأعمدةٍ فارغة.
             |
             | ⚠️ وبلا تمرير `$request` عن قصد: مسار تأكيد البريد (2.5-ب) يعيد
             |    تشغيل التسجيل بطلبٍ **مُصطنَع** (`Request::create`) لا كوكي فيه،
             |    وموافقةُ الزائر لا تعيش إلّا في الكوكي. فتُقرأ من الطلب الحقيقيّ.
             */
            $acquisition->attach($user);

            // الريفيرال (7.6 · 21.1): مكافأة الطرفين — والمدعوّ له تذكرة ترحيب عند التفعيل
            if ($offer !== '') {
                if ($referrer = User::where('code', $offer)->first()) {
                    // ونفس المصدر يوسم سطر الإحالة — لا `query` فارغة (21.2-ح)
                    $source = $acquisition->sourceOf($user);

                    Referral::create([
                        'referrer_id' => $referrer->id,
                        'referred_id' => $user->id,
                        'code' => $offer,
                        'commission_percent' => (float) setting('referral.commission_percent', 7),
                        'utm_source' => $source['utm_source'] ?? null,
                        'utm_medium' => $source['utm_medium'] ?? null,
                        'utm_campaign' => $source['utm_campaign'] ?? null,
                    ]);
                }
            }

            return $user;
        });

        // نفس قاعدة 2.3 عند التسجيل: الجلسة تبدأ دائمةً لا مؤقّتة
        Auth::login($user, (bool) setting('auth.session.remember_always', true));
        $request->session()->regenerate();
        $request->session()->forget([
            OnboardingController::SESSION_CODE,
            OnboardingController::SESSION_ANSWERED,
            // ⛔ بيانات الشاشة الأولى (وفيها كلمة السرّ) لا تعيش بعد إنشاء الحساب
            self::SESSION_ACCOUNT,
            RequireVerifiedEmail::SESSION_PENDING,
            RequireVerifiedEmail::SESSION_VERIFIED,
        ]);

        // ونفس الكشف عند التسجيل — فأوّل شاشة يراها تُحسَب بساعته هو (5)
        app(TimezoneDetector::class)->sync($request, $user);

        // ⭐ الترتيب المنصوص: تعليمات ⟵ اختبار تمهيديّ ⟵ تحت المراجعة (2.5-د)
        return redirect()->route(app(OnboardingJourney::class)->routeFor($user));
    }

    /**
     * تحقّقات التسجيل جاهزةً لمن يقف على الباب قبل المتحكّم.
     *
     * ⭐ لماذا تُنشَر؟ لأنّ حارس تأكيد البريد (2.5-ب) يسبق التحقّق، فكان يبعث
     * رمزًا لفورمٍ **لن يكتمل بعده** — فيؤكّد المستخدم بريده ثمّ يُرَدّ للفورم.
     * فالحارس يسأل بنفس القواعد **حرفيًّا** لا بنسخةٍ ثانية منها تتخلّف عنها.
     *
     * @param  array<string, mixed>  $data
     */
    public function registrationValidator(array $data): ValidatorContract
    {
        return Validator::make($data, $this->rules(), $this->messages(), $this->attributes());
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
     * تحقّقات **(ب) الشاشة الأولى** وحدها: البريد والموبايل بكود دولته والباسوورد.
     *
     * @return array<string, array<int, mixed>>
     */
    private function accountRules(): array
    {
        /*
         | كود الدولة إلزاميّ **حين توجد قائمةٌ يُختار منها** — ولا يُقفَل الباب
         | على المسجّلين حين لا تكون هناك. نفس منطق `location_required` في
         | الشاشة الثانية: قائمةٌ فارغة عطبٌ في البيانات لا ذنبَ للمستخدم فيه.
         */
        $dialAvailable = setting('countries.registration.phone_code', true)
            && Country::query()->where('is_active', true)
                ->whereNotNull('phone_code')->where('phone_code', '!=', '')->exists();

        return [
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            // كود الدولة يُختار من قائمة الأعلام لا يُكتَب — فيُسأل عن وجوده
            'phone_iso2' => [
                $dialAvailable ? 'required' : 'nullable',
                'string', 'size:2', Rule::exists('countries', 'iso2')->where('is_active', true),
            ],
            'phone_national' => ['required', 'string', 'max:20', 'regex:/^\d[\d\s\-]*$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * تحقّقات **(ج) صفحة المعلومات** وحدها — بلا حقول الشاشة الأولى.
     *
     * @return array<string, array<int, mixed>>
     */
    private function identityRules(): array
    {
        return collect($this->rules())->except(['email', 'phone', 'password'])->all();
    }

    /**
     * تحقّقات الشاشتين معًا (2.5-ب + 2.5-ج) — والرسائل تقول ماذا حدث وماذا تفعل (2.17-ب).
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

    /** هل تأكّد هذا البريد بالذات في هذه الجلسة؟ (2.5-ب) */
    private function emailVerified(Request $request, string $email): bool
    {
        $email = Str::lower(trim($email));

        return $email !== ''
            && Str::lower((string) $request->session()->get(RequireVerifiedEmail::SESSION_VERIFIED)) === $email;
    }

    /**
     * «تحقق الإيميل لحظيًا: **صيغة صحيحة + غير مستخدم من قبل**» (2.5-ب).
     *
     * والمحذوف حسابه يُحسَب مستعمَلًا (`withTrashed`) — فالـSoft-delete في 2.3
     * «يُعامَل كأنّه غير موجود» أمام المستخدمين، لكنّ بريده ما زال في الجدول
     * ولو سمحنا بتسجيلٍ عليه لسقط الإنشاء على قيد التفرّد بعد كلّ الطريق.
     */
    private function probeEmail(Request $request): JsonResponse
    {
        $email = Str::lower(trim((string) $request->query('email')));
        $valid = $email !== '' && Validator::make(['email' => $email], ['email' => ['email', 'max:190']])->passes();

        /*
         | ⚠️ سؤال «هل هذا البريد مستعمَل؟» **يكشف بريدًا مسجَّلًا لمن يسأل** —
         | وهو ثمنُ ما نصّ عليه البند («غير مستخدم من قبل» لحظيًّا)، لا خيارَ فيه.
         | لكنّ **الجرد بالجملة** ليس من البند في شيء: بلا حدٍّ يمرّ عليه سائلٌ
         | آليّ بمليون بريد فيخرج بقائمة أعضاء المنصّة. فالحدّ لكلّ IP في نافذة،
         | والرقمان **إعدادان** لا محروقان (2.13). والمتجاوز يُردّ بلا جواب.
         */
        $limit = max(1, (int) setting('auth.email_probe.max_per_window', 30));
        $window = max(1, (int) setting('auth.email_probe.window_seconds', 60));
        $key = 'email-probe:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'valid' => $valid, 'taken' => false, 'ok' => false,
                'message' => (string) setting('auth.email_probe.throttled', 'استنّى شويّة وجرّب تاني.'),
            ], 429);
        }

        RateLimiter::hit($key, $window);

        $taken = $valid && User::query()->where('email', $email)->withTrashed()->exists();

        return response()->json([
            'valid' => $valid,
            'taken' => $taken,
            'ok' => $valid && ! $taken,
            'message' => match (true) {
                ! $valid => (string) setting('onboarding.account.email_invalid', 'الشكل ده مش بريد صالح — راجع الكتابة.'),
                $taken => (string) setting('onboarding.account.email_taken', 'البريد ده مستعمَل قبل كده — ادخل بيه أو استرجع كلمة السرّ.'),
                default => (string) setting('onboarding.account.email_ok', 'البريد متاح ✓'),
            },
        ]);
    }

    /**
     * الدول المعروضة في الاختيار — **الظاهرة وحدها**، والمخفيّة لا تُعرَض ولا تُحذَف.
     *
     * والترتيب بالاسم العربيّ بعد `sort_order`: خمسة آلاف صفٍّ دخلت من المصدر
     * بترتيبٍ صفر، فبلا هذا تخرج القائمة بترتيب الإدراج — وهو لا ترتيب.
     *
     * @return Collection<int, Country>
     */
    private function countries()
    {
        return Country::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name_ar')
            ->get(['id', 'iso2', 'name_ar', 'phone_code']);
    }

    /**
     * «المحافظة: Select **مبني على الدولة**، يُملأ **تلقائيًّا**» (2.5-ج).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function governoratesOf(int $countryId): array
    {
        if ($countryId <= 0) {
            return [];
        }

        return Governorate::query()
            ->where('country_id', $countryId)
            // ⛔ ولا شرط `is_active` هنا: «**المحافظة لا تُخفى أبدًا**» (قاعدة
            // مالك صريحة في 12.7-د) — فقائمة الاختيار لا تُسقِط واحدةً أبدًا.
            ->orderBy('sort_order')->orderBy('name_ar')
            ->get(['id', 'name_ar'])
            ->map(fn (Governorate $g) => ['id' => (int) $g->id, 'name' => (string) $g->name_ar])
            ->all();
    }

    /**
     * «**Select لأكواد الدول (بالأعلام، بشكل احترافي)**» (2.5-ب) — والمصدر نفسه
     * الذي نصّ عليه 2.5-ج: «نستخدمه أيضًا لـ**Select أكواد الدول بالأعلام**».
     *
     * والمفتاح يُعرَض بعلامة «+» دائمًا مهما كُتِب في القاعدة: المصدر يعطيه بلا
     * علامة (`20`) والبذرة القديمة كتبته بها (`+20`) — والقائمة لا تُظهر شكلين.
     *
     * @return array<int, array{iso2: string, name: string, dial: string}>
     */
    private function dialCodes(): array
    {
        return $this->countries()
            ->filter(fn (Country $c) => trim((string) $c->phone_code) !== '')
            ->map(fn (Country $c) => [
                'iso2' => mb_strtoupper((string) $c->iso2),
                'name' => (string) $c->name_ar,
                'dial' => '+'.ltrim(trim((string) $c->phone_code), '+'),
            ])
            ->values()
            ->all();
    }

    /**
     * الرقم كما يُخزَّن: كود الدولة + الرقم المحلّيّ بلا مسافات ولا شرطات.
     *
     * @param  array<string, mixed>  $data
     */
    private function fullPhone(array $data): string
    {
        $national = ltrim(preg_replace('/\D+/', '', (string) ($data['phone_national'] ?? '')) ?: '', '0');
        $iso2 = mb_strtoupper(trim((string) ($data['phone_iso2'] ?? '')));

        $dial = $iso2 === '' ? '' : (string) Country::query()->where('iso2', $iso2)->value('phone_code');
        $dial = $dial === '' ? '' : '+'.ltrim($dial, '+');

        return $dial.$national;
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
