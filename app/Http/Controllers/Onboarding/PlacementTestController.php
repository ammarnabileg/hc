<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\OnboardingJourney;
use App\Services\Onboarding\PlacementTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الاختبار التمهيديّ (2.5-د-2) — الخطوة الثانية في رحلة ما بعد التسجيل.
 *
 * الأسئلة يديرها الأدمن وقد تكون **فيديو و/أو HTML مضمَّنًا و/أو نصًّا و/أو صورة**،
 * والإجابات كإجابات الاختبارات العادية، و**لكلّ سؤال مكافأته**.
 *
 * ⭐ التصحيح والصرف في الخادم حصرًا: الإجابة الصحيحة لا تُرسَل للمتصفّح أبدًا،
 * فلا يقرأها أحدٌ من مصدر الصفحة قبل أن يجيب.
 */
class PlacementTestController extends Controller
{
    public function __construct(
        private readonly OnboardingJourney $journey,
        private readonly PlacementTest $test,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->guard($request)) {
            return $redirect;
        }

        return view('onboarding.placement', [
            'questions' => $this->test->questions(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard($request)) {
            return $redirect;
        }

        $answers = $request->input('answers');
        $answers = is_array($answers) ? $answers : [];

        $result = $this->test->submit($request->user(), $answers);

        $message = strtr((string) setting(
            'onboarding.placement.result_text',
            'خلّصت ✓ إجاباتك الصحيحة :score من :total — وكسبت :xp XP و:tickets تذكرة.',
        ), [
            ':score' => (string) $result['score'],
            ':total' => (string) $result['total'],
            ':xp' => (string) $result['xp'],
            ':tickets' => (string) $result['tickets'],
        ]);

        return redirect()
            ->route($this->journey->routeFor($request->user()->refresh()))
            ->with('status', $message);
    }

    /**
     * حارس الترتيب — وفيه معالجةٌ لحالةٍ حقيقيّة: الأدمن أطفأ الاختبار أو أفرغ
     * بنكه بعد أن وصل المستخدم للشاشة. عندها لا نتركه أمام صفحةٍ فارغة بلا مخرج:
     * نختم الخطوة ونمضي به لخطوته التالية.
     */
    private function guard(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($this->journey->isAt($user, OnboardingJourney::STEP_PLACEMENT)) {
            return null;
        }

        return redirect()->route($this->journey->routeFor($user));
    }
}
