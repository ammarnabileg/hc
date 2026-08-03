<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventNotice;
use App\Models\EventRegistration;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Events\AttendanceService;
use App\Services\Events\CheckinQr;
use App\Services\Events\ReminderScheduler;
use App\Support\Scope\ScopeFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 🖥️ **المسجّلون والحضور** — الشاشة الجامعة (12.11 · 24.3).
 *
 * لماذا شاشةٌ جامعة أصلًا؟ لأنّ **خريطة 12.0** تنصّ حرفيًّا:
 * «📅 الفعاليّات ▾ (12.11) — الفعاليّات · **المسجّلون والحضور**» — بندان تحت
 * القسم، أي **صفحتان يُدخَل إليهما من السايد بار**. والمبنيّ كان مسارًا واحدًا
 * `admin/events/{event}/registrations` يلزمه معرّف فعاليّةٍ بعينها، فلا يصلح
 * بندًا في سايد بار — فبقي بند الخريطة **بلا شاشة**.
 *
 * ⚠️ **وما يسكت عنه النصّ لا نملؤه بالظنّ:** وصف 24.3 لهذه الشاشة مكتوبٌ
 * **لفعاليّةٍ بعينها** («الهيدر: اسم الفعاليّة + تاريخها»)، ولم يصف الشكل
 * الجامع. فالمبنيّ: **فلتر فعاليّة** أوّل الفلاتر — بلا اختيار يعرض كلّ
 * المسجّلين عبر الفعاليّات، وباختيارٍ يعرض **حرفيًّا** ما وصفه 24.3 (اسم
 * الفعاليّة وتاريخها في الهيدر · التشيك-إن اليدويّ · إشعار المسجّلين).
 * وهذا مسجَّلٌ في التقرير بوصفه **قرار سدّ فراغ**، لا نصًّا دستوريًّا.
 */
class EventRegistrationsController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly CheckinQr $qr,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $event = $filters['event_id'] ? Event::find($filters['event_id']) : null;

        $registrations = $this->query($request, $filters)
            ->with(['user:id,name,code', 'event:id,slug,title_ar,starts_at,mode'])
            ->latest('id')
            ->limit((int) setting('events.admin.registrations_limit', 100))
            ->get();

        $counts = $this->counts($request, $filters);

        return view('admin.events.registrations-index', [
            'registrations' => $registrations,
            'counts' => $counts,
            'filters' => $filters,
            'event' => $event,
            'events' => Event::query()
                ->orderByDesc('starts_at')
                ->limit((int) setting('events.admin.list_limit', 50))
                ->get(['id', 'title_ar', 'starts_at']),
            'attendance' => $this->attendance,
            'qr' => $this->qr,
        ]);
    }

    /** تصدير CSV (12.11: «وتصدير CSV») — بنفس فلاتر الشاشة لا بالجدول كلّه */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $rows = $this->query($request, $filters)
            ->with(['user:id,name,code', 'event:id,title_ar,starts_at'])
            ->latest('id')
            ->limit((int) setting('events.admin.export_limit', 5000))
            ->get();

        $headers = (array) setting('events.registrations.csv_headers', [
            'الفعاليّة', 'التاريخ', 'الاسم', 'الكود', 'نمط الحضور', 'حالة الحضور', 'وقت التشيك-إن',
        ]);

        $filename = (string) setting('events.registrations.csv_filename', 'event-registrations').'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'wb');
            // BOM حتى تفتح إكسل العربيّة صحيحةً بلا خطوةٍ يدويّة
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->event?->title_ar,
                    $row->event?->starts_at?->format('Y-m-d H:i'),
                    $row->user?->name,
                    $row->user?->code,
                    $row->attend_mode ?? '',
                    (string) setting(
                        $row->attended ? 'events.registrations.attended_label' : 'events.registrations.absent_label',
                        $row->attended ? 'حضر' : 'غاب',
                    ),
                    $row->attended_at?->format('Y-m-d H:i') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * **مسح QR للتشيك-إن** — الرابط الذي تفتحه كاميرا المنظِّم مباشرةً (24.3).
     * والنتيجة فوريّة: صحيح · خاطئ · مستخدَم من قبل — كما ينصّ وصف البوب-أب.
     */
    public function scan(Request $request, string $token): RedirectResponse
    {
        return $this->resolveScan($request, $token);
    }

    /** نفس المسار بلصق الرمز يدويًّا — طوارئ الكاميرا (24.3: «خطأ في المسح») */
    public function scanSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:120'],
        ]);

        return $this->resolveScan($request, $data['token']);
    }

    /** «إشعار المسجّلين»: نصّ + قناة + إرسال الآن/مجدول (24.3) */
    public function notify(Request $request, Event $event, ReminderScheduler $scheduler): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) setting('events.notice.max_chars', 2000)],
            'channel' => ['required', 'in:bell,email'],
            'send_at' => ['nullable', 'date'],
        ]);

        $sendAt = ! empty($data['send_at'])
            ? CarbonImmutable::parse($data['send_at'])
            : CarbonImmutable::now();

        $notice = EventNotice::create([
            'event_id' => $event->id,
            'created_by' => $request->user()?->id,
            'body' => $data['body'],
            'channel' => $data['channel'],
            'send_at' => $sendAt,
            'status' => 'pending',
        ]);

        // «الآن» يُبَثّ في نفس الطلب؛ و«المجدول» يلتقطه `events:remind` — ولا
        // خيارَ ثالث يبقى معلّقًا بلا مُلتقِط
        if (! $sendAt->isFuture()) {
            EventNotice::query()->whereKey($notice->id)->where('status', 'pending')->update(['status' => 'sending']);
            $notice->refresh();
            $scheduler->deliverNotice($notice);
        }

        AuditTrail::log($request->user(), 'event.notify_registrants', $notice);

        return back()->with('status', (string) setting(
            $sendAt->isFuture() ? 'events.notice.scheduled_message' : 'events.notice.sent_message',
            $sendAt->isFuture() ? 'الإشعار اتجدول ✓ — هيوصل في معاده.' : 'الإشعار اتبعت للمسجّلين ✓',
        ));
    }

    // ------------------------------------------------------------ داخليّ

    private function resolveScan(Request $request, string $token): RedirectResponse
    {
        $result = $this->qr->resolve($token);
        $back = redirect()->route('admin.events.registrations.index', array_filter([
            'event_id' => $result['registration']?->event_id,
        ]));

        // ⚠️ مفتاح الفلاش في ليَاوت الإدارة **`problem`** لا `error` — والمفتاح
        // الخطأ يعني رسالةً تُكتَب ولا تُعرَض، والمنظِّم يقف أمام رمزٍ مرفوض بلا سبب.
        if ($result['reason'] !== CheckinQr::OK) {
            return $back->with('problem', $this->qr->reasonMessage($result['reason']));
        }

        $registration = $result['registration'];
        $outcome = $this->attendance->checkInByToken($registration);

        AuditTrail::log($request->user(), 'event.check_in_qr', $registration, [], [
            'ok' => $outcome['ok'],
            'reward' => $outcome['reward'],
        ]);

        $message = $outcome['message'];

        if ($outcome['ok'] && ($outcome['reward']['xp'] > 0 || $outcome['reward']['tickets'] > 0)) {
            $message .= ' (+'.$outcome['reward']['xp'].' XP · +'.$outcome['reward']['tickets'].' '
                .setting('events.registrations.ticket_word', 'تذكرة').')';
        }

        return $back->with($outcome['ok'] ? 'status' : 'problem', $message);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'event_id' => (int) $request->integer('event_id'),
            'attended' => in_array($request->string('attended')->toString(), ['yes', 'no'], true)
                ? $request->string('attended')->toString()
                : '',
            'attend_mode' => in_array($request->string('attend_mode')->toString(), ['online', 'offline'], true)
                ? $request->string('attend_mode')->toString()
                : '',
            'checked_in' => in_array($request->string('checked_in')->toString(), ['today', 'week', 'month'], true)
                ? $request->string('checked_in')->toString()
                : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(Request $request, array $filters)
    {
        $days = (array) setting('events.registrations.checkin_ranges', ['today' => 1, 'week' => 7, 'month' => 30]);

        return EventRegistration::query()
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — داخل نطاق صاحب اللوحة وحده
            ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'event_registrations.list'))
            ->when($filters['event_id'], fn ($q, $id) => $q->where('event_id', $id))
            ->when($filters['q'], fn ($q, $term) => $q->whereHas(
                'user',
                fn ($u) => $u->where('code', mb_strtoupper($term))->orWhere('name', 'like', '%'.$term.'%'),
            ))
            ->when($filters['attended'] === 'yes', fn ($q) => $q->where('attended', true))
            ->when($filters['attended'] === 'no', fn ($q) => $q->where('attended', false))
            ->when($filters['attend_mode'], fn ($q, $mode) => $q->where('attend_mode', $mode))
            ->when($filters['checked_in'], fn ($q, $range) => $q
                ->whereNotNull('attended_at')
                ->where('attended_at', '>=', now()->subDays((int) ($days[$range] ?? 1))));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function counts(Request $request, array $filters): array
    {
        // العدّادات تتبع فلتر الفعاليّة وحده — فلا تتغيّر بفلتر الحضور فتفقد معناها
        $scoped = ['q' => '', 'event_id' => $filters['event_id'], 'attended' => '', 'attend_mode' => '', 'checked_in' => ''];

        $registered = (clone $this->query($request, $scoped))->count();
        $attended = (clone $this->query($request, $scoped))->where('attended', true)->count();

        return [
            'registered' => $registered,
            'attended' => $attended,
            'absent' => max(0, $registered - $attended),
        ];
    }
}
