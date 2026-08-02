<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * مركز الإشعارات — الصفحة الكاملة (الدستور 2.8).
 *
 * ثلاثة تابات: الكلّ · المنصّة · التطوّع — وتاب التطوّع لا يظهر إلّا للمتطوّعين،
 * لا بالإخفاء البصريّ وحده بل بمنع الوصول إليه من الخادم كذلك.
 * والصفحة شخصيّة: لا يقرأ أحدٌ إشعارات غيره — فالملكيّة هي الحارس.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $this->resolveTab($request, $user->isVolunteer());

        $query = $user->notificationsFeed()->latest();

        if ($tab !== 'all') {
            $query->where('layer', $tab);
        }

        // المدى الافتراضيّ آخر 30 يومًا مع زرّ «وسّع المدى» (2.15-ب)
        $rangeDays = (int) setting('ux.lists.default_range_days', 30);
        $expanded = $request->string('range')->toString() === 'all';

        if (! $expanded) {
            $query->where('created_at', '>=', now()->subDays($rangeDays));
        }

        $perPage = max(1, (int) setting('notifications.per_page', 20));
        $take = $perPage * max(1, (int) $request->integer('more', 1));

        $total = (clone $query)->count();
        $items = $query->limit($take)->get();

        return view('notifications.index', [
            'tab' => $tab,
            'tabs' => $this->tabItems($user, $expanded),
            'groups' => $this->groupByDay($items),
            'total' => $total,
            'hasMore' => $total > $items->count(),
            'nextMore' => ((int) $request->integer('more', 1)) + 1,
            'expanded' => $expanded,
            'rangeDays' => $rangeDays,
            'unread' => Notifier::unreadCount($user, $tab),
            'actionLabel' => (string) setting('notifications.action.default_label', 'نفّذ الآن'),
        ]);
    }

    /** تعليم إشعار واحد كمقروء (POST). */
    public function read(Request $request, AppNotification $notification): JsonResponse|RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        Notifier::markRead($notification);

        return $this->respond($request, [
            'unread' => Notifier::unreadCount($request->user()),
        ], 'اتقرا ✓');
    }

    /** تعليم الكلّ كمقروء (POST) — داخل التاب المفتوح حتى لا يُفاجَأ المستخدم. */
    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $tab = $this->resolveTab($request, $user->isVolunteer());

        Notifier::markAllRead($user, $tab);

        return $this->respond($request, [
            'unread' => Notifier::unreadCount($user),
        ], 'اتعلّمت كلّها كمقروءة ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** تاب التطوّع لغير المتطوّع = غير موجود أصلًا، فيرجع للكلّ (2.8 · 2.15-أ-7). */
    private function resolveTab(Request $request, bool $isVolunteer): string
    {
        $tab = $request->string('tab')->toString() ?: 'all';
        $allowed = array_keys($this->tabs($isVolunteer));

        return in_array($tab, $allowed, true) ? $tab : 'all';
    }

    /**
     * تابات جاهزة للعرض بعدّاد غير المقروء لكلّ تاب (2.8).
     *
     * @return array<int, array{key: string, label: string, url: string, count: int}>
     */
    private function tabItems(User $user, bool $expanded): array
    {
        $items = [];

        foreach ($this->tabs($user->isVolunteer()) as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => route('notifications.index', array_filter([
                    'tab' => $key === 'all' ? null : $key,
                    'range' => $expanded ? 'all' : null,
                ])),
                'count' => Notifier::unreadCount($user, $key),
            ];
        }

        return $items;
    }

    /** @return array<string, string> */
    private function tabs(bool $isVolunteer): array
    {
        $tabs = ['all' => 'الكلّ', 'platform' => 'المنصّة'];

        if ($isVolunteer) {
            $tabs['volunteer'] = 'التطوّع';
        }

        return $tabs;
    }

    /**
     * تجميع زمنيّ: اليوم · أمس · أقدم — أوضح من قائمة طويلة بتواريخ مبعثرة.
     *
     * @return array<string, Collection<int, AppNotification>>
     */
    private function groupByDay(Collection $items): array
    {
        $groups = ['today' => 'اليوم', 'yesterday' => 'أمس', 'older' => 'أقدم'];
        $out = [];

        foreach ($groups as $key => $label) {
            $bucket = $items->filter(fn (AppNotification $n) => match ($key) {
                'today' => $n->created_at?->isToday(),
                'yesterday' => $n->created_at?->isYesterday(),
                default => $n->created_at && ! $n->created_at->isToday() && ! $n->created_at->isYesterday(),
            })->values();

            if ($bucket->isNotEmpty()) {
                $out[$label] = $bucket;
            }
        }

        return $out;
    }

    private function respond(Request $request, array $payload, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload + ['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
