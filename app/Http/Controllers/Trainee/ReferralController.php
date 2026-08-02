<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\Tracker;
use App\Services\Referral\DeepLink;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «ادعُ أصدقاءك» (7.6 · 24.5) — كارت واحد بارز فيه رابط الدعوة والعمولة،
 * وتحته جدول المدعوّين. ومعه **بوّابة الروابط العميقة** `/i/{code}` (21.1-ج).
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly DeepLink $deepLink,
        private readonly Tracker $tracker,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // تذكرة الترحيب تُمنَح عند التفعيل ومرّة واحدة — والنداء آمن للتكرار
        $this->referrals->grantWelcomeTicket($user);
        $this->referrals->claimLanding(
            $user,
            $request->session()->pull('referral.pending_id'),
        );

        $days = $this->days($request->query('days'));
        $invited = $this->referrals->invitedBy($user, $days);

        return view('referral.index', [
            'link' => $this->referrals->link($user),
            'stats' => $this->referrals->stats($invited),
            'invited' => $invited,
            'service' => $this->referrals,
            'days' => $days,
            'periods' => $this->periods(),
            // روابط الدعوة لكلّ محتوى: فعاليّاتي القادمة أوّلًا
            'deepLinks' => $this->eventDeepLinks($user),
            'landingUrl' => $this->referrals->landingUrlFor($user),
            'landingLabel' => $this->referrals->landingLabelFor($user),
        ]);
    }

    /**
     * بوّابة الرابط العميق: تحفظ الوجهة ثمّ تودّع الزائر عند التسجيل،
     * وتفتحها فورًا لمن هو داخل بالفعل.
     */
    public function invite(Request $request, string $code): RedirectResponse
    {
        $referrer = User::query()->where('code', $code)->first();
        $type = (string) $request->query('type', '');
        $id = $request->query('id');
        $landingUrl = $this->deepLink->url($type, $id);

        if (! $referrer) {
            return redirect()->route('register');
        }

        $this->tracker->record('referral_link_open', $referrer, $request->user()?->id);

        if ($request->user()) {
            return redirect()->to($landingUrl ?? route('events.index'));
        }

        if ($pending = $this->referrals->rememberLanding($referrer, $type ?: null, $id)) {
            $request->session()->put('referral.pending_id', $pending->id);
        }

        // العودة لنفس الصفحة بعد إتمام الدخول (21.1-ج)
        if ($landingUrl) {
            redirect()->setIntendedUrl($landingUrl);
        }

        return redirect()->route('register', [
            (string) setting('referral.link.param', 'offer') => $referrer->code,
        ]);
    }

    /**
     * @return array<int,array{label: string, url: string}>
     */
    private function eventDeepLinks(User $user): array
    {
        $eventIds = EventRegistration::query()
            ->where('user_id', $user->id)
            ->pluck('event_id');

        $events = Event::query()
            ->where('status', (string) setting('events.published_status', 'published'))
            ->where('starts_at', '>=', now())
            ->when($eventIds->isNotEmpty(), fn ($q) => $q->orderByRaw(
                'CASE WHEN id IN ('.$eventIds->map(fn ($id) => (int) $id)->implode(',').') THEN 0 ELSE 1 END'
            ))
            ->orderBy('starts_at')
            ->limit((int) setting('referral.deep_links.limit', 3))
            ->get();

        return $events->map(fn (Event $event) => [
            'label' => (string) $event->title_ar,
            'url' => $this->referrals->deepLinkFor($user, 'event', $event->id),
        ])->all();
    }

    /** المدى الافتراضيّ آخر 30 يومًا (2.15-د) — و«من البداية» تعني بلا حدّ */
    private function days(mixed $value): ?int
    {
        $allowed = array_keys($this->periods());

        if ($value === null || ! in_array((int) $value, $allowed, true)) {
            return (int) setting('ux.lists.default_range_days', 30);
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return array<int,string>
     */
    private function periods(): array
    {
        return [
            (int) setting('ux.lists.default_range_days', 30) => 'آخر 30 يومًا',
            90 => 'آخر 3 شهور',
            365 => 'آخر سنة',
            0 => 'من البداية',
        ];
    }
}
