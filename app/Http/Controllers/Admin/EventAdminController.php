<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Event;
use App\Models\EventAgendaItem;
use App\Models\EventRegistration;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\SettingsWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الفعاليّات (12.11 · 13.3 · 24.3).
 *
 * إنشاء ثنائيّ اللغة · النوع (أوفلاين/أونلاين/هجين) · **كود الحضور (OTP رقميّ)** ·
 * المكافأة المتدرّجة زمنيًّا والشهادة · رابط التسجيل الخارجيّ · الأجندة والمتحدّثون ·
 * السعة · المسجّلون والحضور.
 */
class EventAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: (string) setting('events.default_tab', 'upcoming');

        $events = Event::query()
            ->withCount('registrations')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('title_ar', 'like', '%'.$term.'%'))
            ->when($request->string('mode')->toString(), fn ($q, $mode) => $q->where('mode', $mode))
            ->when($status === 'upcoming', fn ($q) => $q->where('starts_at', '>=', now()))
            ->when($status === 'past', fn ($q) => $q->where('starts_at', '<', now()))
            ->when($status === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->orderBy('starts_at', $status === 'past' ? 'desc' : 'asc')
            ->limit(50)
            ->get();

        return view('admin.events.index', [
            'events' => $events,
            'modes' => (array) setting('events.modes', []),
            'settings' => SettingsWriter::groupRows('events'),
            'certificateTypes' => CertificateType::query()->where('is_active', true)->get(),
            'defaultTiers' => (array) setting('events.reward_tiers_default', []),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'mode' => $request->string('mode')->toString(),
                'status' => $status,
            ],
            'view' => $request->string('view')->toString() ?: (string) setting('events.default_view', 'table'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:events,id'],
            'title_ar' => ['required', 'string', 'max:180'],
            'title_en' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'description_en' => ['nullable', 'string', 'max:4000'],
            'mode' => ['required', 'string', 'in:online,offline,hybrid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'join_link' => ['nullable', 'url', 'max:255'],
            'registration_link' => ['nullable', 'url', 'max:255'],
            'recording_link' => ['nullable', 'url', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'price_coins' => ['nullable', 'numeric', 'min:0'],
            'price_tickets' => ['nullable', 'numeric', 'min:0'],
            'attendance_code' => ['nullable', 'string', 'max:32'],
            'certificate_type_id' => ['nullable', 'integer', 'exists:certificate_types,id'],
            'status' => ['required', 'string', 'in:draft,published,cancelled'],
            'reward_tiers' => ['nullable', 'array'],
            'agenda' => ['nullable', 'array'],
            'agenda.*.title' => ['nullable', 'string', 'max:180'],
            'agenda.*.speaker' => ['nullable', 'string', 'max:120'],
            'agenda.*.starts_at' => ['nullable', 'date'],
        ]);

        $event = isset($data['id']) ? Event::findOrFail($data['id']) : new Event;
        $old = $event->exists ? $event->only(['title_ar', 'starts_at', 'capacity', 'status']) : [];

        $tiers = collect($data['reward_tiers'] ?? [])
            ->filter(fn ($row) => ($row['hours'] ?? null) !== null && $row['hours'] !== '')
            ->map(fn ($row) => [
                'hours' => (int) $row['hours'],
                'xp' => (int) ($row['xp'] ?? 0),
                'tickets' => (int) ($row['tickets'] ?? 0),
            ])
            ->values()
            ->all();

        $event->fill([
            'slug' => $event->slug ?: str()->slug($data['title_en'] ?: 'event').'-'.str()->lower(str()->random(6)),
            'title_ar' => $data['title_ar'],
            'title_en' => $data['title_en'] ?? null,
            'description' => $data['description'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'mode' => $data['mode'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'join_link' => $data['join_link'] ?? null,
            'registration_link' => $data['registration_link'] ?? null,
            'recording_link' => $data['recording_link'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'price_coins' => $data['price_coins'] ?? 0,
            'price_tickets' => $data['price_tickets'] ?? 0,
            // كود الحضور OTP رقميّ — مستمرّ لا يقفل، والمكافأة وحدها تتناقص (13.3)
            'attendance_code' => $data['attendance_code'] ?: $this->generateCode(),
            'certificate_type_id' => $data['certificate_type_id'] ?? null,
            'status' => $data['status'],
            'xp_reward' => (int) ($tiers[0]['xp'] ?? 0),
            'ticket_reward' => (int) ($tiers[0]['tickets'] ?? 0),
            'reward_tiers' => json_encode($tiers ?: (array) setting('events.reward_tiers_default', []), JSON_UNESCAPED_UNICODE),
        ])->save();

        if (array_key_exists('agenda', $data)) {
            EventAgendaItem::query()->where('event_id', $event->id)->delete();

            foreach (array_values($data['agenda'] ?? []) as $index => $row) {
                if (empty($row['title'])) {
                    continue;
                }

                EventAgendaItem::create([
                    'event_id' => $event->id,
                    'title' => $row['title'],
                    'speaker' => $row['speaker'] ?? null,
                    'starts_at' => $row['starts_at'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        AuditTrail::log($request->user(), 'event.save', $event, $old, $event->only(['title_ar', 'starts_at', 'capacity', 'status']));

        return back()->with('status', 'اتحفظ ✓');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $event->forceFill(['status' => 'cancelled'])->save();
        AuditTrail::log($request->user(), 'event.cancel', $event);

        return back()->with('status', 'اتلغت الفعاليّة ✓ — بلّغ المسجّلين من زرّ الإشعار.');
    }

    /** المسجّلون والحضور: عدّادات + تشيك-إن يدويّ + درجة المكافأة المستحقّة */
    public function registrations(Request $request, Event $event): View
    {
        $registrations = EventRegistration::query()
            ->with('user:id,name,code')
            ->where('event_id', $event->id)
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('code', mb_strtoupper($term))->orWhere('name', 'like', '%'.$term.'%')))
            ->when($request->string('attended')->toString() === 'yes', fn ($q) => $q->where('attended', true))
            ->when($request->string('attended')->toString() === 'no', fn ($q) => $q->where('attended', false))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.events.registrations', [
            'event' => $event,
            'registrations' => $registrations,
            'tiers' => $this->tiers($event),
            'counts' => [
                'registered' => EventRegistration::where('event_id', $event->id)->count(),
                'attended' => EventRegistration::where('event_id', $event->id)->where('attended', true)->count(),
                'absent' => EventRegistration::where('event_id', $event->id)->where('attended', false)->count(),
            ],
            'filters' => ['q' => $request->string('q')->toString(), 'attended' => $request->string('attended')->toString()],
        ]);
    }

    /** تشيك-إن بكود الحضور — المكافأة المتدرّجة تُصرَف بحسب زمن الإثبات */
    public function checkIn(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'attendance_code' => ['required', 'string', 'max:32'],
        ]);

        if (! hash_equals((string) $event->attendance_code, trim($data['attendance_code']))) {
            return back()->with('status', 'كود الحضور غلط — راجعه مع صاحب الفعاليّة وجرّب تاني.');
        }

        $registration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->whereHas('user', fn ($q) => $q->where('code', mb_strtoupper($data['code'])))
            ->first();

        if (! $registration) {
            return back()->with('status', 'الكود ده مش مسجّل في الفعاليّة دي.');
        }

        if ($registration->attended) {
            return back()->with('status', 'الحضور متسجّل قبل كده — ومفيش صرف مكرّر.');
        }

        $registration->forceFill(['attended' => true, 'attended_at' => now()])->save();

        $tier = $this->tierFor($event, now());
        $user = $registration->user;

        if ($user && $tier) {
            if (($tier['xp'] ?? 0) > 0) {
                Integrations::post($user, 'xp', (float) $tier['xp'], 'event', 'حضور فعاليّة: '.$event->title_ar, $request->user(), $event);
            }

            if (($tier['tickets'] ?? 0) > 0) {
                Integrations::post($user, 'tickets', (float) $tier['tickets'], 'event', 'حضور فعاليّة: '.$event->title_ar, $request->user(), $event);
            }
        }

        AuditTrail::log($request->user(), 'event.check_in', $registration, [], ['tier' => $tier]);

        return back()->with('status', 'اتسجّل الحضور ✓ — والدرجة المصروفة: '.($tier['xp'] ?? 0).' XP.');
    }

    public function toggleAttendance(Request $request, EventRegistration $registration): RedirectResponse
    {
        $registration->forceFill([
            'attended' => ! $registration->attended,
            'attended_at' => $registration->attended ? null : now(),
        ])->save();

        AuditTrail::log($request->user(), 'event.attendance_toggle', $registration);

        return back()->with('status', 'اتحدّثت حالة الحضور ✓');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        SettingsWriter::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    // ------------------------------------------------------------ داخليّ

    private function tiers(Event $event): array
    {
        $raw = $event->getAttribute('reward_tiers');
        $tiers = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($tiers) && $tiers ? $tiers : (array) setting('events.reward_tiers_default', []);
    }

    /** أوّل درجة تشمل الساعات المنقضية منذ الفعاليّة */
    private function tierFor(Event $event, $moment): ?array
    {
        $hours = $event->starts_at ? $event->starts_at->diffInHours($moment) : 0;

        foreach ($this->tiers($event) as $tier) {
            if ($hours <= (int) ($tier['hours'] ?? 0)) {
                return $tier;
            }
        }

        return null;
    }

    private function generateCode(): string
    {
        $length = max(4, (int) setting('events.attendance_code_length', 6));

        return (string) random_int((int) str_pad('1', $length, '0'), (int) str_repeat('9', $length));
    }
}
