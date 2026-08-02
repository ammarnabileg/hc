<?php

namespace App\Http\Controllers;

use App\Models\Offboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحات مشتركة لا يملكها مجال بعينه.
 */
class PublicPagesController extends Controller
{
    /**
     * صفحة «تطوّع معنا» التعريفيّة (13.4-أ) — **محتواها كلّه يُدار من لوحة الإدارة**.
     * ولا تظهر أصلًا للمتطوّع المُسكَّن، لأنّ عنصر السايد بار عنده يتحوّل إلى «لوحة التطوّع» (24.5-أ).
     */
    public function volunteering(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isVolunteer()) {
            return redirect()->route('volunteer.overview');
        }

        // التبريد بعد الخروج (13.4-س/ق): الزرّ لا يُفتَح قبل موعده، ويظهر مكانه سطرٌ محترم
        $offboarding = Offboarding::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $excluded = $offboarding?->type === 'exclusion';
        $coolingUntil = $offboarding?->cooldown_until;
        $inCooldown = ! $excluded && $coolingUntil && now()->lessThan($coolingUntil);

        return view('public.volunteering', [
            'sections' => (array) setting('volunteering.landing.sections', []),
            'heroTitle' => setting('volunteering.landing.title', 'تطوّع معنا'),
            'heroBody' => setting('volunteering.landing.body', 'انضمّ لفريقٍ بيتعلّم وبيبني.'),
            'ctaLabel' => setting('volunteering.landing.cta', 'ابدأ المسار التأهيليّ'),
            'excluded' => $excluded,
            'inCooldown' => $inCooldown,
            'coolingUntil' => $coolingUntil,
            'returning' => (bool) $offboarding,
        ]);
    }

    /** إشعارات التطوّع = مركز الإشعارات على تاب التطوّع — مصدر واحد بلا ازدواج (2.8) */
    public function volunteerNotifications(): RedirectResponse
    {
        return redirect()->route('notifications.index', ['layer' => 'volunteer']);
    }

    /**
     * موافقة التتبّع (21.3-د): قبول · رفض · تخصيص.
     * **والرفض يوقف البكسل وأحداث الخادم فعليًّا لا شكليًّا** — تُقرأ الحالة من المستخدم أو الكوكي.
     */
    public function storeConsent(Request $request): RedirectResponse
    {
        $choice = $request->validate([
            'choice' => ['required', 'in:accepted,rejected,custom'],
        ])['choice'];

        if ($user = $request->user()) {
            $user->forceFill([
                'tracking_consent' => $choice,
                'tracking_consent_at' => now(),
            ])->saveQuietly();
        }

        $days = (int) setting('ads.consent.remember_days', 180);

        return back()->withCookie(cookie('tracking_consent', $choice, $days * 24 * 60));
    }
}
