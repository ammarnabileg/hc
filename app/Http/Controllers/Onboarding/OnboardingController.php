<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Gamification\CelebrationService;
use App\Services\Onboarding\OnboardingJourney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * رحلة التسجيل (2.5): شاشة الدعوة · التعليمات · صفحة القبول.
 *
 * ⭐ كلّ نصٍّ هنا من `setting()` ومن مجموعة `onboarding` — فالمالك يكتب صفحة
 * التعليمات وصفحة القبول بنفسه من لوحة الإدارة (2.13)، ولا يُنشَر كود لتغيير
 * كلمة. ولذلك المحتوى **HTML** لا نصًّا مسطَّحًا: البند يطلب صورًا وفيديوهات.
 */
class OnboardingController extends Controller
{
    /** مفتاح السيشن الذي يقول إنّ شاشة الدعوة أُجيبت (بكود أو بتخطٍّ) */
    public const SESSION_ANSWERED = 'onboarding.referral_answered';

    /** كود الداعي المحفوظ في السيشن — كما نصّ 2.5-أ لرابط الدعوة */
    public const SESSION_CODE = 'onboarding.referral_code';

    public function __construct(private readonly OnboardingJourney $journey) {}

    // ==================================================== أ) «هل دعاك شخص ما؟»

    public function referral(Request $request): View|RedirectResponse
    {
        // الدخول عبر رابط دعوة: تُتخطّى الشاشة والآيدي يُحفَظ في السيشن (2.5-أ)
        if ($code = trim((string) $request->query('offer'))) {
            $request->session()->put([self::SESSION_CODE => $code, self::SESSION_ANSWERED => true]);

            return redirect()->route('register');
        }

        if (! setting('onboarding.referral.enabled', true) || $request->session()->get(self::SESSION_ANSWERED)) {
            return redirect()->route('register');
        }

        return view('onboarding.referral');
    }

    /** تفعيل الهديّة ⟸ صوت واحتفال (2.5-أ) — والاحتفال يُعرَض على شاشة التسجيل */
    public function applyReferral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ], ['code.required' => (string) setting('onboarding.flow.apply_referral_msg', 'اكتب كود صاحبك الأوّل، أو اتخطّى الخطوة.')]);

        $code = trim($data['code']);

        // كودٌ لا صاحب له: نقول ما حدث وما العمل — ولا نمرّره صامتًا (2.17-ب)
        if (! User::query()->where('code', $code)->exists()) {
            return back()->withInput()->withErrors([
                'code' => (string) setting('onboarding.referral.invalid_text', 'الكود ده مش موجود. راجعه مع صاحبك أو اتخطّى الخطوة.'),
            ]);
        }

        $request->session()->put([self::SESSION_CODE => $code, self::SESSION_ANSWERED => true]);

        return redirect()->route('register')->with('referral_celebrate', true);
    }

    public function skipReferral(Request $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_ANSWERED, true);
        $request->session()->forget(self::SESSION_CODE);

        return redirect()->route('register');
    }

    // ==================================================== د-1) صفحة «تعليمات»

    public function instructions(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, OnboardingJourney::STEP_INSTRUCTIONS)) {
            return $redirect;
        }

        return view('onboarding.instructions');
    }

    /**
     * زرّ «موافقة» (2.5-د-1) — والموافقة تُختَم بوقتها على الحساب لا في السيشن،
     * فلا تضيع بتسجيل خروج ولا تُعاد على المستخدم مرّةً ثانية.
     */
    public function agree(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard($request, OnboardingJourney::STEP_INSTRUCTIONS)) {
            return $redirect;
        }

        $request->validate([
            'agreed' => ['accepted'],
        ], ['agreed.accepted' => (string) setting('onboarding.flow.agree_must', 'لازم توافق على التعليمات قبل ما تكمّل.')]);

        $request->user()->forceFill(['instructions_agreed_at' => now()])->save();

        return redirect()->route($this->journey->routeFor($request->user()->refresh()));
    }

    // ============================================ د-4) «تمّ قبول حسابك» 🎉

    public function accepted(Request $request, CelebrationService $celebrations): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, OnboardingJourney::STEP_ACCEPTED)) {
            return $redirect;
        }

        return view('onboarding.accepted', [
            // احتفال الذروة المعتمَد `account.approved` (2.14-3) — مرّة واحدة Server-side
            'celebration' => $celebrations->fire($request->user(), 'account.approved'),
        ]);
    }

    /** [يلا ندخل] — تُختَم الرؤية فلا تتكرّر صفحة القبول بعد اليوم */
    public function enter(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['acceptance_seen_at' => now()])->save();

        return redirect()->route('dashboard');
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * حارس الترتيب: من ليس عند هذه الخطوة يُردّ لخطوته هو (2.5-د).
     * فلا يقفز أحدٌ فوق التعليمات ولا يعلق أحدٌ في شاشة أتمّها.
     */
    private function guard(Request $request, string $step): ?RedirectResponse
    {
        $user = $request->user();

        if ($this->journey->isAt($user, $step)) {
            return null;
        }

        return redirect()->route($this->journey->routeFor($user));
    }
}
