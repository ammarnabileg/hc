<?php

namespace App\Http\Controllers;

use App\Models\PlacementRequest;
use App\Services\Ads\AdEvents;
use App\Services\Ads\Consent;
use App\Services\Volunteer\Org\HonoraryElement;
use App\Services\Volunteer\People\JourneyService;
use App\Services\Volunteer\People\LandingContent;
use App\Services\Volunteer\People\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * صفحات مشتركة لا يملكها مجال بعينه.
 */
class PublicPagesController extends Controller
{
    /**
     * صفحة «تطوّع معنا» (13.4-أ) + **شاشة حالة المتقدّم** (13.4-ج) —
     * **كامل محتواها من لوحة الإدارة** (`volunteer_page.*`)، ولا تظهر للمتطوّع
     * المُسكَّن لأنّ عنصر السايد بار عنده يتحوّل إلى «لوحة التطوّع» (24.5-أ).
     *
     * كانت هذه الصفحة تقرأ مفاتيح `volunteering.landing.*` بينما الأدمن يكتب في
     * `volunteer_page.*` — فيكتب المالك كتلةً وميثاقًا وعنوانًا وتعرض الصفحة
     * «محتوى الصفحة بيتجهّز». والمفتاح واحدٌ من اليوم (2.13).
     */
    public function volunteering(
        Request $request,
        JourneyService $journey,
        LandingContent $content,
        HonoraryElement $honorary,
        PlacementService $placements,
    ): View|RedirectResponse {
        $user = $request->user();

        if ($user->isVolunteer()) {
            return redirect()->route('volunteer.overview');
        }

        // مهلة الـ48 ساعة ومكافأة الإتمام تُطبَّقان عند كلّ فتح — فلا تتعلّق
        // الميزة بوجود كرون (نفس نهج `PlacementService::expireOverdue`)
        $placements->expireOverdue();
        $journey->completeIfDue($user);

        $blocks = $content->blocks();
        $stats = $content->stats();
        $status = $journey->status($user);
        $candidate = $status['candidate'];

        return view('public.volunteering', [
            // الهيرو ومحتوى الصفحة — من مفاتيح الأدمن حرفيًّا
            'heroTitle' => (string) setting('volunteer_page.hero_title', 'تطوّع معنا'),
            'heroBody' => (string) setting('volunteer_page.hero_subtitle', ''),
            'heroMedia' => (string) setting('volunteer_page.hero_media', ''),
            'ctaLabel' => (string) setting('volunteer_page.cta_label', 'ابدأ التدريب التأهيليّ'),
            'blocks' => $blocks,
            'hasContent' => $content->hasAnyBlock($blocks),
            'emptyMessage' => (string) setting('volunteer_page.empty_message', 'لسّه محتوى الصفحة فاضي.'),
            'faqTitle' => (string) setting('volunteer_page.faq_title', 'أسئلة بتتسأل كتير'),
            'storiesTitle' => (string) setting('volunteer_page.stories_title', 'حكايات من الفريق'),
            'stats' => $stats,
            'impactLine' => $content->impactLine($stats),

            // الميثاق (13.4-أ) — الموافقة عليه شرط قبل بدء التأهيليّ
            'charterTitle' => (string) setting('volunteer_page.charter_title', 'ميثاق المتطوّع'),
            'charterText' => $journey->charterText(),
            'charterAgreeLabel' => (string) setting('volunteer_page.charter_agree_label', 'قرأت الميثاق وموافق عليه'),
            'charterAccepted' => $journey->hasAcceptedCharter($user),

            // الرحلة والحالة (13.4-ج)
            'gate' => $journey->startGate($user),
            'status' => $status,
            'candidate' => $candidate,
            'qualifyingPath' => $journey->path(),
            'nextCta' => (string) setting('volunteer.qualifying.cta_label', 'الدخول للمرحلة التالية'),
            'canEnterPipeline' => $candidate === null && $status['progress']['complete'],
            'canRenew' => $candidate && $journey->canRenew($candidate),
            'renewAt' => $candidate ? $journey->renewAvailableAt($candidate) : null,
            'renewCta' => (string) setting('volunteer.journey.renew_cta', 'جدّد استعدادك'),
            'renewHint' => (string) setting('volunteer.journey.renew_hint', ''),
            'statusTitle' => (string) setting('volunteer.journey.status_title', 'حالتي'),
            'interviewTitle' => (string) setting('volunteer.journey.interview_title', 'موعد مقابلتك'),
            'interviewCta' => (string) setting('volunteer.journey.interview_cta', 'ادخل المقابلة'),
            'interviewNoLink' => (string) setting('volunteer.journey.interview_no_link', ''),
            'responseHours' => $placements->responseHours(),

            // سطر شرفيّ اختياريّ يضبطه الأدمن (13.4-ص-ب)
            'honorary' => $honorary->showsOn('landing') ? $honorary->resolve() : null,
        ]);
    }

    /** ⭐ الموافقة على ميثاق المتطوّع — **قبل** بدء التأهيليّ (13.4-أ) */
    public function acceptCharter(Request $request, JourneyService $journey): RedirectResponse
    {
        $request->validate(['agree' => ['accepted']], [], ['agree' => (string) setting('home.public.accept_charter_msg', 'الموافقة على الميثاق')]);

        $journey->acceptCharter($request->user());

        return back()->with('status', (string) setting('volunteer_page.charter_done_message', 'اتسجّل ✓'));
    }

    /** ⭐ «الدخول للمرحلة التالية» ⟵ قائمة الانتظار المبدئيّة (13.4-ب) */
    public function enterPipeline(Request $request, JourneyService $journey): RedirectResponse
    {
        try {
            $journey->enterPipeline($request->user());
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', (string) setting('volunteer.journey.shortlist_title', 'دخلت قائمة الانتظار المبدئيّة ✓'));
    }

    /** ⭐ «جدّد استعدادك» — يرفعه في القائمة بمهلة تبريد (13.4-هـ) */
    public function renewReadiness(Request $request, JourneyService $journey): RedirectResponse
    {
        $candidate = $journey->candidateOf($request->user());

        if (! $candidate) {
            return back()->with('status', (string) setting('volunteer.journey.renew_not_waiting', ''));
        }

        try {
            $journey->renewReadiness($candidate);
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', (string) setting('volunteer.journey.renew_done', 'اتسجّل ✓'));
    }

    /**
     * ردّ المرشّح على طلب التسكين من صفحة حالته (13.4-هـ — جانب المرشّح).
     * والحارس هنا **الملكيّة** لا صلاحيّة فريق التوظيف: الطلب طلبُه هو.
     */
    public function respondPlacement(Request $request, PlacementRequest $placementRequest, PlacementService $placements): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            (int) $placementRequest->recruitment_candidate?->user_id === (int) $request->user()->id,
            403,
            (string) setting('home.public.respond_placement_denied', 'الطلب ده مش بتاعك.'),
        );

        try {
            $placements->respond($placementRequest, $data['decision'], $request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', $data['decision'] === 'accepted'
            ? (string) setting('home.public.respond_placement_msg', 'مبروك 🎉 أهلًا بيك معانا.')
            : (string) setting('home.public.respond_placement_ok', 'اتسجّل ✓ شكرًا لوضوحك.'));
    }

    /**
     * ⭐ صفحة سياسة الخصوصيّة — **عامّة تمامًا: بلا تسجيل دخول ولا صلاحيّة** (21.3-د).
     *
     * البند الحاكم حرفيًّا: «**صفحة سياسة خصوصيّة** توضّح ما يُجمَع ولمن يُرسَل،
     * وحقّ السحب في أيّ وقت من إعدادات الخصوصيّة والأمان». وبانر الموافقة
     * (21.3-د) يظهر **للزائر قبل أيّ حساب**، فلو كان رابطها محجوبًا خلف `auth`
     * (كما كان يشير قبلًا إلى `settings.privacy`) لا يقدر الزائر يفتحه أصلًا.
     *
     * والمحتوى كلّه من `setting()` (2.13) — والرابط لإعدادات الخصوصيّة داخل
     * الصفحة يظهر لصاحب الحساب فقط؛ الزائر يقرأ بدله سطرًا يشرح أنّ اختياره
     * محفوظٌ على متصفّحه وقابلٌ للتغيير من البانر، أو بتسجيل الدخول.
     */
    public function privacyPolicy(): View
    {
        return view('public.privacy-policy', [
            'title' => (string) setting('ads.privacy_page.title', 'سياسة الخصوصيّة'),
            'subtitle' => (string) setting('ads.privacy_page.subtitle', 'إيه اللي بنجمعه من بياناتك، ولمين بيتبعت، وإزاي تسحب موافقتك في أيّ وقت.'),
            'collectedTitle' => (string) setting('ads.privacy_page.collected_title', 'إيه اللي بنجمعه'),
            'collectedBody' => (string) setting('ads.privacy_page.collected_body', 'بيانات حسابك (الاسم، البريد، رقم الموبايل، بيانات ملفّك)، وبيانات استخدامك للمنصّة (الصفحات اللي بتفتحها، لحظات زيّ التسجيل والشراء)، وبيانات جهازك (عنوان IP ونوع المتصفّح)، وده كلّه لتشغيل حسابك وتحسين المنصّة، وللإعلان بعد موافقتك الصريحة وحدها.'),
            'sharedTitle' => (string) setting('ads.privacy_page.shared_title', 'لمين بيتبعت'),
            'sharedBody' => (string) setting('ads.privacy_page.shared_body', 'لو وافقت على التتبّع، بترسل أحداثٌ لمنصّات الإعلان (Meta وGoogle) لقياس الحملات وإعادة الاستهداف، وبتتبعت مشفّرة من غير ما بيانات خام تغادر خوادمنا. ولو رفضت أو ما اخترتش، بيتوقّف الإرسال فعليًّا لا شكليًّا. وباقي بياناتك بتفضل في خوادمنا ولا تتبعت لأيّ جهة تالتة من غير موافقتك.'),
            'rightsTitle' => (string) setting('ads.privacy_page.rights_title', 'حقّك في السحب في أيّ وقت'),
            'rightsBody' => (string) setting('ads.privacy_page.rights_body', 'تقدر توافق أو ترفض أو تخصّص أغراض التتبّع من البانر أوّل ما تدخل المنصّة، وتقدر كمان تغيّر اختيارك في أيّ وقت بعد كده من غير ما تنتظر البانر يرجع.'),
            'withdrawTitle' => (string) setting('ads.privacy_page.withdraw_title', 'تغيير اختيارك'),
            'withdrawCtaLabel' => (string) setting('ads.privacy_page.withdraw_cta_label', 'اذهب لإعدادات الخصوصيّة والأمان'),
            'withdrawGuestNote' => (string) setting('ads.privacy_page.withdraw_guest_note', 'لسّه معملتش حساب؟ اختيارك بيتسجّل على المتصفّح ده، وتقدر تغيّره من بانر الموافقة في أيّ وقت. وبعد ما تسجّل حسابك، هتقدر تديره من صفحة الخصوصيّة والأمان.'),
        ]);
    }

    /** إشعارات التطوّع = مركز الإشعارات على تاب التطوّع — مصدر واحد بلا ازدواج (2.8) */
    public function volunteerNotifications(): RedirectResponse
    {
        return redirect()->route('notifications.index', ['layer' => 'volunteer']);
    }

    /**
     * موافقة التتبّع (21.3-د): قبول · رفض · **تخصيص بأغراضٍ بعينها**.
     *
     * ⭐ **والرفض يوقف البكسل وأحداث الخادم فعليًّا لا شكليًّا**: الحالة تُخزَّن على
     *    المستخدم وعلى الكوكي معًا، ويقرأها `Consent` قبل كلّ حدث — من المتصفّح ومن
     *    الخادم. و«تخصيص» بلا اختيارٍ = **رفض**، لأنّ الصمت ليس موافقة.
     */
    public function storeConsent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'choice' => ['required', 'in:accepted,rejected,custom'],
            'scopes' => ['array'],
            'scopes.*' => ['string', 'in:'.implode(',', Consent::PURPOSES)],
        ]);

        $choice = $data['choice'];
        $scopes = array_values(array_intersect((array) ($data['scopes'] ?? []), Consent::PURPOSES));

        if ($choice === Consent::CUSTOM && $scopes === []) {
            $choice = Consent::REJECTED;
        }

        $scopes = $choice === Consent::CUSTOM ? $scopes : [];

        if ($user = $request->user()) {
            $user->forceFill([
                'tracking_consent' => $choice,
                'tracking_consent_at' => now(),
                'tracking_scopes' => $scopes,
            ])->saveQuietly();
        }

        // السحب فعليّ: أيّ اختيارٍ جديد يفرّغ طابور الأحداث المنتظرة في المتصفّح
        $request->session()->forget(AdEvents::QUEUE_KEY);

        $minutes = (int) setting('ads.consent.remember_days', 180) * 24 * 60;

        return back()
            ->withCookie(cookie('tracking_consent', $choice, $minutes))
            ->withCookie(cookie('tracking_scopes', json_encode($scopes), $minutes));
    }
}
