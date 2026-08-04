<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LearningPath;
use App\Models\User;
use App\Services\Engagement\AmbassadorService;
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
        private readonly AmbassadorService $ambassadors,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // تذكرة الترحيب تُمنَح عند التفعيل ومرّة واحدة — والنداء آمن للتكرار
        $this->referrals->settleRewards($user);
        // ⭐ وتذاكر دعواته الناجحة كداعٍ (7.6) — لكلٍّ حارسه فلا تتكرّر
        $this->referrals->settlePendingFor($user);
        $this->referrals->claimLanding(
            $user,
            $request->session()->pull('referral.pending_id'),
        );

        /*
         | ⭐ مزامنة عدّاد السفير هنا أيضًا (7.6.1).
         | كانت المزامنة في الصفحة الرئيسيّة وحدها، فبعد دعوة ناجحة تعرض `/`
         | الرقم الجديد بينما `/referral` — وهي **صفحة الدعوات نفسها** — ما زالت
         | تعرض القديم. ورقمان لنفس المعنى في شاشتين يهدم الثقة في العدّاد كلّه.
         */
        $celebration = $this->ambassadors->sync($user);

        $days = $this->days($request->query('days'));
        $invited = $this->referrals->invitedBy($user, $days);
        $status = $this->status($request->query('status'));

        return view('referral.index', [
            'link' => $this->referrals->link($user),
            'stats' => $this->referrals->stats($invited),
            // فلاتر القائمة: الكلّ / مكتمل / انتظار (7.6.2)
            'invited' => $this->referrals->filterByStatus($invited, $status),
            'status' => $status,
            'statuses' => $this->statuses(),
            'service' => $this->referrals,
            'days' => $days,
            'periods' => $this->periods(),
            // ⭐ الآلة الحاسبة التفاعليّة «قلب التفاعل» (7.6.2)
            'calculator' => $this->referrals->calculator(),
            // «شبكتي» عرضًا بصريًّا + لقب السفير وتقدّمه للعتبة التالية (7.6.1)
            'ambassador' => $this->ambassadorProgress($user),
            'celebration' => $celebration,
            // ⭐ روابط الدعوة لكلّ محتوى: فعاليّات **وتدريبات ومسارات** (21.1-ج)
            'deepLinks' => $this->contentDeepLinks($user),
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

        /*
         | ⭐ الداعي يُحفَظ في السيشن **دائمًا** (7.6) — لا بشرط أن تكون الوجهة
         | من الأنواع المدعومة. كان الحفظ معلّقًا على `rememberLanding()` التي
         | تُرجِع null لأيّ نوعٍ غير مدعوم أو بلا معرّف، فتضيع الدعوة كلّها لأنّ
         | **الوجهة** لم تكن معروفة — والوجهة تفصيل، أمّا الداعي فهو الأصل.
         */
        $this->referrals->rememberReferrerCode($referrer->code);

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
     * ⭐ «ادعُ صديقك **لهذا التدريب تحديدًا**» (21.1-ج): الفعاليّات القادمة أوّلًا،
     * ثمّ التدريبات والمسارات المنشورة — لأنّ الرابط العامّ الواحد لا يقول شيئًا.
     *
     * @return array<int,array{label: string, type: string, url: string}>
     */
    private function contentDeepLinks(User $user): array
    {
        $published = (string) setting('learning.course.published_status', 'published');
        $limit = (int) setting('referral.deep_links.limit', 3);

        $courses = Course::query()
            ->where('status', $published)
            ->latest('published_at')
            ->limit($limit)
            ->get(['id', 'name_ar'])
            ->map(fn (Course $course) => [
                'label' => (string) $course->name_ar,
                'type' => (string) setting('referral.screen.content_deep_links_msg', 'تدريب'),
                'url' => $this->referrals->deepLinkFor($user, 'course', $course->id),
            ])->all();

        $paths = LearningPath::query()
            ->where('status', $published)
            ->latest('published_at')
            ->limit($limit)
            ->get(['id', 'name_ar'])
            ->map(fn (LearningPath $path) => [
                'label' => (string) $path->name_ar,
                'type' => (string) setting('referral.screen.content_deep_links_msg_2', 'مسار'),
                'url' => $this->referrals->deepLinkFor($user, 'path', $path->id),
            ])->all();

        return [...$this->eventDeepLinks($user), ...$courses, ...$paths];
    }

    /**
     * @return array<int,array{label: string, type: string, url: string}>
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
            'type' => (string) setting('referral.screen.event_deep_links_msg', 'فعاليّة'),
            'url' => $this->referrals->deepLinkFor($user, 'event', $event->id),
        ])->all();
    }

    /**
     * ⭐ بوّابة `/join?ref=CODE` (7.6.2) — والصيغة القديمة `?offer=` مقبولة للتوافق.
     *
     * وظيفتها الوحيدة: **تثبيت الداعي في السيشن** ثمّ إرسال الزائر للتسجيل. ومنذ
     * صارت الدعوة في السيشن لم يعد فقدان الـQuery يُسقِطها.
     */
    public function join(Request $request): RedirectResponse
    {
        $code = (string) $request->query(
            (string) setting('referral.join.param', 'ref'),
            (string) $request->query((string) setting('referral.link.param', 'offer'), ''),
        );

        $referrer = $code !== '' ? User::query()->where('code', $code)->first() : null;

        if ($referrer) {
            $this->referrals->rememberReferrerCode($referrer->code);
            $this->tracker->record('referral_link_open', $referrer, $request->user()?->id);
        }

        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('register');
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
     * ⭐ «شبكتي» ولقب السفير (7.6.1 · 2.9-8): العدد المفعَّل واللقب والعتبة التالية.
     *
     * التقدّم **مُهدى** لا يبدأ من صفر (2.9-2): البار يقيس من العتبة السابقة
     * للتالية، فالمستخدم يرى نفسه دائمًا داخل الطريق لا خارجه.
     *
     * @return array{enabled:bool,count:int,tier:?array,next:?array,percent:int}
     */
    private function ambassadorProgress(User $user): array
    {
        $count = $this->ambassadors->activatedInvites($user);
        $tier = $this->ambassadors->tierFor($count);
        $next = $this->ambassadors->nextTier($count);

        $from = (int) ($tier['threshold'] ?? 0);
        $to = (int) ($next['threshold'] ?? max(1, $count));
        $span = max(1, $to - $from);

        return [
            'enabled' => $this->ambassadors->enabled(),
            'count' => $count,
            'tier' => $tier,
            'next' => $next,
            'percent' => $next ? (int) round(max(0, $count - $from) / $span * 100) : 100,
        ];
    }

    /** فلتر حالة المدعوّ (7.6.2): الكلّ / مكتمل / في الانتظار */
    private function status(mixed $value): string
    {
        return in_array($value, ['completed', 'pending'], true) ? (string) $value : 'all';
    }

    /** @return array<string,string> */
    private function statuses(): array
    {
        return [
            'all' => (string) setting('referral.filter.all', 'الكلّ'),
            'completed' => (string) setting('referral.filter.completed', 'مكتمل'),
            'pending' => (string) setting('referral.filter.pending', 'في الانتظار'),
        ];
    }

    /**
     * @return array<int,string>
     */
    private function periods(): array
    {
        return [
            (int) setting('ux.lists.default_range_days', 30) => (string) setting('referral.screen.periods_msg', 'آخر 30 يومًا'),
            90 => (string) setting('referral.screen.periods_msg_2', 'آخر 3 شهور'),
            365 => (string) setting('referral.screen.periods_msg_3', 'آخر سنة'),
            0 => (string) setting('referral.screen.periods_msg_4', 'من البداية'),
        ];
    }
}
