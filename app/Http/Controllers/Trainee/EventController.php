<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\AttendanceService;
use App\Services\Events\CheckinQr;
use App\Services\Events\EventPresenter;
use App\Services\Events\EventQuery;
use App\Services\Events\IcsGenerator;
use App\Services\Events\RegistrationService;
use App\Services\Events\ShareCardRenderer;
use App\Services\Events\Tracker;
use App\Services\Referral\ReferralService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الفعاليّات (13.3 · 24.5): تقويم/كروت · تفاصيل بأجندة وعدّاد · تسجيل وتذكرة ·
 * حضور بكود OTP أو تشيك-إن ⟵ شهادة ومكافأة تُصرَف **بالكود فقط ومرّة واحدة**.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly EventQuery $query,
        private readonly EventPresenter $presenter,
        private readonly RegistrationService $registrations,
        private readonly AttendanceService $attendance,
        private readonly ReferralService $referrals,
        private readonly Tracker $tracker,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $view = in_array($request->query('view'), ['calendar', 'cards'], true)
            ? (string) $request->query('view')
            : (string) setting('events.default_view', 'cards');

        $month = $view === 'calendar'
            ? $this->month((string) $request->query('month', ''))
            : null;

        $filters = [
            'mode' => $this->pick($request->query('mode'), array_keys(EventQuery::MODES)),
            'period' => $this->pick($request->query('period'), array_keys(EventQuery::PERIODS)) ?? 'upcoming',
            'category' => $request->query('category') ?: null,
            'q' => (string) $request->query('q', ''),
            'price' => $this->pick($request->query('price'), ['free', 'paid']),
            'mine' => $request->boolean('mine'),
            'month' => $month,
        ];

        $builder = $this->query->build($filters, $user);

        $events = $view === 'calendar'
            ? $builder->get()
            : $builder->paginate((int) setting('events.list.per_page', 12))->withQueryString();

        $mine = EventRegistration::query()
            ->where('user_id', $user->id)
            ->pluck('attend_mode', 'event_id');

        return view('events.index', [
            'events' => $events,
            'view' => $view,
            'filters' => $filters,
            'categories' => $this->query->categories(),
            'calendar' => $view === 'calendar'
                ? $this->query->calendar($month, collect($events), $user)
                : null,
            'weekdays' => $this->query->weekdays(),
            'month' => $month,
            'presenter' => $this->presenter,
            'myRegistrations' => $mine,
            'myTicketsCount' => $mine->count(),
        ]);
    }

    public function show(Request $request, Event $event, CheckinQr $qr): View
    {
        abort_unless($this->visible($request, $event), 404);

        $user = $request->user();
        $event->loadCount('registrations')->load(['agenda', 'certificate_type']);
        $registration = $this->registrations->registrationFor($event, $user);

        $this->tracker->record('event_view', $event, $user->id);

        return view('events.show', [
            'event' => $event,
            'registration' => $registration,
            'presenter' => $this->presenter,
            'attendance' => $this->attendance,
            // الرابط لا يُمرَّر للواجهة أصلًا قبل وقته — القرار خادميّ (13.3)
            'joinLink' => $this->presenter->joinLinkVisible($event, $registration) ? $event->join_link : null,
            'reward' => $this->attendance->reward($event),
            // ⭐ الـQR للأوفلاين والهجين فقط، ولمن سجّل فقط (13.3 · 12.11)
            'qrEnabled' => $registration !== null && $qr->enabled($event),
            'qrRefreshSeconds' => $qr->refreshSeconds(),
            'inviteLink' => $this->referrals->deepLinkFor($user, 'event', $event->id),
            'commissionPercent' => $this->referrals->commissionPercent(),
            'speakers' => $event->agenda->pluck('speaker')->filter()->unique()->values(),
        ]);
    }

    public function register(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->visible($request, $event), 404);

        $data = $request->validate([
            'attend_mode' => ['nullable', 'in:online,offline'],
        ]);

        $result = $this->registrations->register($event, $request->user(), $data['attend_mode'] ?? null);

        return redirect()
            ->route('events.show', $event->slug)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function checkin(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->visible($request, $event), 404);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ], [], ['code' => (string) setting('events.screen.checkin_msg', 'كود الحضور')]);

        $result = $this->attendance->checkIn($event, $request->user(), $data['code']);

        $message = $result['message'];

        if ($result['ok'] && ($result['reward']['xp'] > 0 || $result['reward']['tickets'] > 0)) {
            $message .= strtr((string) setting('events.screen.checkin_msg_2', ' (+:a1 XP · +:a2 تذكرة)'), [':a1' => (string) ($result['reward']['xp']), ':a2' => (string) ($result['reward']['tickets'])]);
        }

        return redirect()
            ->route('events.show', $event->slug)
            ->with($result['ok'] ? 'status' : 'error', $message);
    }

    /** التذكرة القابلة للنشر (13.3) */
    public function ticket(Request $request, Event $event): View
    {
        abort_unless($this->visible($request, $event), 404);

        $registration = $this->registrations->registrationFor($event, $request->user());
        abort_unless($registration !== null, 404);

        return view('events.ticket', [
            'event' => $event,
            'registration' => $registration,
            'presenter' => $this->presenter,
        ]);
    }

    /** «أضِف لتقويمي»: ملفّ ICS نولّده بأنفسنا (13.3) */
    public function ics(Request $request, Event $event, IcsGenerator $generator): StreamedResponse
    {
        abort_unless($this->visible($request, $event), 404);

        $body = $generator->forEvent($event, $request->user());

        return response()->streamDownload(
            fn () => print ($body),
            $generator->filename($event),
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }

    /**
     * ⭐ **رمز تشيك-إن QR ديناميكيّ** (13.3: «وللأوفلاين يتوفّر تشيك-إن QR
     * كذلك» · 12.11: «QR ديناميكيّ للتشيك-إن يمنع استخدام كود شخص لآخر»).
     *
     * الرمز يخصّ **تسجيل صاحب الطلب وحده** — لا يُطلَب لغيره ولا يُمرَّر معرّفٌ
     * في الرابط، فلا سبيل لأن يستخرج أحدٌ رمزَ أحد. و`no-store` شرطُ حياته:
     * رمزٌ مكيَّشٌ رمزٌ ثابت، والثابت هو بالضبط ما ينهاه النصّ عنه.
     */
    public function qr(Request $request, Event $event, CheckinQr $qr): Response
    {
        abort_unless($this->visible($request, $event), 404);
        abort_unless($qr->enabled($event), 404);

        $registration = $this->registrations->registrationFor($event, $request->user());
        abort_unless($registration !== null, 404, 'no registration — no qr');

        return response($qr->svg($registration), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** صورة OG مرسومة SVG لكلّ رابط فعاليّة (21.1-أ) — عامّة ليقرأها المشاركون */
    public function og(Event $event, ShareCardRenderer $renderer): Response
    {
        abort_unless($event->status === (string) setting('events.published_status', 'published'), 404);

        return response($renderer->eventCard($event), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.(int) setting('events.og.cache_seconds', 3600),
        ]);
    }

    /** المسوّدة لا يراها إلّا من يملك إدارة الفعاليّات — وغير المملوك يُخفى لا يُعطَّل (2.15-أ-7) */
    private function visible(Request $request, Event $event): bool
    {
        return $event->status === (string) setting('events.published_status', 'published')
            || $request->user()?->allows('events.manage') === true;
    }

    private function pick(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private function month(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $value) === 1
            ? $value
            : CarbonImmutable::now()->format('Y-m');
    }
}
