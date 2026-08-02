<?php

namespace App\Http\Controllers;

use App\Services\Engagement\AmbassadorService;
use App\Services\Engagement\PositiveMessages;
use App\Services\Home\HomeContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الواجهة العامّة للمنصّة (21.1 · 21.2).
 *
 * صفحة هبوط عربيّة **مفهرسة** تعمل للزائر بلا تسجيل — لأنّها أوّل حلقة نموّ:
 * صفحة مفتوحة لمحرّكات البحث تعرض ما نُشِر فعلًا وتدعو للتسجيل المجّانيّ (2.5-د).
 * والمستخدم المسجَّل لا شأن له بها فيُحوَّل للوحته مباشرةً.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly HomeContent $content,
        private readonly AmbassadorService $ambassadors,
        private readonly PositiveMessages $positive,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($user = $request->user()) {
            // مزامنة صامتة للقب السفير قبل التحويل — فلا يتأخّر لقبٌ استحقّه (7.6.1)
            $this->ambassadors->sync($user);

            return redirect()->route('dashboard');
        }

        return view('welcome', [
            'courses' => $this->content->courses(),
            'paths' => $this->content->paths(),
            'articles' => $this->content->articles(),
            'events' => $this->content->events(),
            'valueBlocks' => $this->content->valueBlocks(),
            'schema' => $this->content->organizationSchema(),
            'ambassadors' => $this->ambassadors->enabled() && setting('ambassadors.leaderboard.public', true)
                ? $this->ambassadors->leaderboard((int) setting('home.ambassadors.limit', 5))
                : collect(),
            // الأيقونة المفاجئة (2.6-ب) — احتمالها حقيقيّ لا موجَّه
            'surprise' => $this->positive->shouldShowIcon()
                ? $this->positive->forContext((string) setting('engagement.positive.surprise_context', 'surprise'))
                : null,
            'offerTicket' => false, // التذكرة لصاحب حسابٍ فقط — والزائر لا حساب له بعد
        ]);
    }

    /**
     * لوحة متصدّري السفراء (7.6.1 · 21.1-ج) — عامّة ليكون اللقب **مكانةً**
     * تُرى، ويقفلها الأدمن بإعداد واحد إن شاء.
     */
    public function ambassadors(Request $request): View
    {
        abort_unless(
            $this->ambassadors->enabled() && setting('ambassadors.leaderboard.public', true),
            404,
        );

        // مزامنة الألقاب قبل العرض — فاللوحة تعكس الواقع لحظةَ فتحها
        $this->ambassadors->syncAll();

        $user = $request->user();
        $celebration = $user ? $this->ambassadors->sync($user) : null;
        $invites = $user ? $this->ambassadors->activatedInvites($user) : 0;

        return view('home.ambassadors', [
            'tiers' => $this->ambassadors->tiers(),
            'leaders' => $this->ambassadors->leaderboard(),
            'me' => $user,
            'myInvites' => $invites,
            'myTitle' => $user ? $this->ambassadors->titleOf($user) : null,
            'nextTier' => $this->ambassadors->nextTier($invites),
            'celebration' => $celebration,
            'surprise' => $this->positive->shouldShowIcon()
                ? $this->positive->forContext((string) setting('engagement.positive.surprise_context', 'surprise'))
                : null,
            'offerTicket' => (bool) $user
                && $this->positive->shouldOfferTicket()
                && $this->positive->canClaimToday($user),
        ]);
    }

    /**
     * زرّ «استلام تذكرة» (2.6-ب) — قيمته الحقيقيّة مكتوبة قبل الضغط،
     * وله حدّ يوميّ فلا يتحوّل لمصدر دخل ولا لإدمان (2.9 · 21.1-د).
     */
    public function claimTicket(Request $request): RedirectResponse
    {
        $granted = $this->positive->grantTicket($request->user());

        return back()->with('status', $granted
            ? str_replace(':count', (string) $this->positive->ticketAmount(),
                (string) setting('engagement.positive.ticket_granted_text', 'وصلتك :count تذكرة 🎟️'))
            : (string) setting('engagement.positive.ticket_denied_text', 'خدت تذكرة المفاجأة النهارده — نشوفك بكرة.'));
    }
}
