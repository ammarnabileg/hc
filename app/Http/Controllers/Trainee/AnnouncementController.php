<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Services\Notifications\AnnouncementAcknowledger;
use App\Services\Notifications\AnnouncementFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * التعليمات (الدستور 13.2 · 24.5): قناة بثّ إداريّة اتّجاه واحد — بلا ردود.
 *
 * الصفحة شخصيّة بطبعها: كلّ مستخدم يرى ما استُهدف به هو (الكلّ/مسار/تدريب/دور/شخص)
 * ويقرأ سجلّه هو — فالحارس الحقيقيّ هنا هو **الاستهداف والملكيّة**، لا صلاحيّة إداريّة.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementFeed $feed) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $items = $this->feed->for($user);
        $reads = $this->feed->readsFor($user, $items);

        $unread = $items->reject(fn ($a) => (bool) ($reads[$a->id]->read_at ?? null))->count();

        // ثلاثة فلاتر ظاهرة فقط: غير المقروء · النوع · بحث (2.15-أ-4)
        $filters = [
            'unread' => $request->boolean('unread'),
            'type' => $request->string('type')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $visible = $items
            ->when($filters['unread'], fn ($c) => $c->reject(fn ($a) => (bool) ($reads[$a->id]->read_at ?? null)))
            ->when($filters['type'] !== '', fn ($c) => $c->filter(fn ($a) => AnnouncementFeed::typeOf($a) === $filters['type']))
            ->when($filters['q'] !== '', fn ($c) => $c->filter(
                fn ($a) => str_contains(mb_strtolower($a->title.' '.$a->body), mb_strtolower($filters['q']))
            ))
            ->values();

        // تمرير تدريجيّ لا ترقيم صفحات (2.15 — الترقيم مرفوض صراحةً)
        $perPage = max(1, (int) setting('announcements.feed.per_page', 10));
        $take = $perPage * max(1, (int) $request->integer('more', 1));
        $shown = $visible->take($take);

        return view('announcements.index', [
            'items' => $shown,
            'reads' => $reads,
            'reactionCounts' => $this->reactionCounts($shown->pluck('id')->all()),
            'unread' => $unread,
            'total' => $visible->count(),
            'hasMore' => $visible->count() > $shown->count(),
            'nextMore' => ((int) $request->integer('more', 1)) + 1,
            'filters' => $filters,
            'types' => AnnouncementFeed::types(),
            'reactions' => $this->allowedReactions(),
            // بوب-أب الإقرار الإلزاميّ يفتح تلقائيًّا لأوّل منشور حرج لم يُقَرّ (13.2)
            'pendingAck' => $this->feed->pendingAcknowledge($user),
        ]);
    }

    /** تعليم منشور كمقروء — ردّ فوريّ للواجهة المتفائلة (2.17-ب). */
    public function read(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->feed->isVisibleTo($announcement, $user), 404);

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return $this->respond($request, ['unread' => $this->feed->unreadCount($user)], 'اتقرا ✓');
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $now = now();

        foreach ($this->feed->for($user) as $announcement) {
            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $user->id],
                ['read_at' => $now],
            );
        }

        return $this->respond($request, ['unread' => 0], 'اتعلّمت كلّها كمقروءة ✓');
    }

    /**
     * الإقرار الإلزاميّ «قرأتُ وفهمت» — والمكافأة مرّة واحدة يتحقّق منها الخادم.
     */
    public function acknowledge(Request $request, Announcement $announcement, AnnouncementAcknowledger $acknowledger): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->feed->isVisibleTo($announcement, $user), 404);
        abort_unless((bool) $announcement->requires_acknowledge, 422, 'هذا المنشور لا يحتاج إقرارًا.');

        $xp = $acknowledger->acknowledge($announcement, $user);
        $tickets = $acknowledger->lastTickets();

        // المكسب يُقال كما وقع: XP وتذاكر معًا لو الاثنان ممنوحان (12.6-أ · 2.17)
        $gains = array_filter([
            $xp > 0 ? $xp.' XP' : null,
            $tickets > 0 ? $tickets.' تذكرة' : null,
        ]);

        $message = $gains !== []
            ? 'شكرًا ليك — كسبت '.implode(' و', $gains).' على إقرارك ✓'
            : 'تمّ الإقرار ✓';

        return $this->respond(
            $request,
            ['xp' => $xp, 'tickets' => $tickets, 'unread' => $this->feed->unreadCount($user)],
            $message,
        );
    }

    /**
     * تفاعل إيموجي — ولا يُقبَل إلّا لو الأدمن سمح به **لهذا المنشور** (13.2).
     * والضغط على نفس الإيموجي يلغيه (تبديل).
     */
    public function react(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->feed->isVisibleTo($announcement, $user), 404);
        abort_unless((bool) $announcement->reactions_enabled, 403, 'التفاعل مقفول على هذا المنشور.');

        $data = $request->validate([
            'reaction' => ['required', 'string', 'in:'.implode(',', $this->allowedReactions())],
        ]);

        $read = AnnouncementRead::firstOrNew([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
        ]);

        $read->reaction = $read->reaction === $data['reaction'] ? null : $data['reaction'];
        $read->read_at ??= now();
        $read->save();

        return $this->respond($request, [
            'reaction' => $read->reaction,
            'counts' => $this->reactionCounts([$announcement->id])[$announcement->id] ?? [],
        ], 'اتسجّل تفاعلك ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** الإيموجي المسموح — قائمة من الإعدادات لا من الكود (2.13). */
    private function allowedReactions(): array
    {
        $reactions = setting('announcements.reactions.allowed', ['👍', '❤️', '🎉', '👏', '🙏']);

        return is_array($reactions) && $reactions !== [] ? array_values($reactions) : ['👍'];
    }

    /** @return array<int, array<string, int>> */
    private function reactionCounts(array $announcementIds): array
    {
        if ($announcementIds === []) {
            return [];
        }

        return AnnouncementRead::query()
            ->select('announcement_id', 'reaction', DB::raw('count(*) as total'))
            ->whereIn('announcement_id', $announcementIds)
            ->whereNotNull('reaction')
            ->groupBy('announcement_id', 'reaction')
            ->get()
            ->groupBy('announcement_id')
            ->map(fn ($rows) => $rows->pluck('total', 'reaction')->map(fn ($n) => (int) $n)->all())
            ->all();
    }

    private function respond(Request $request, array $payload, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload + ['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
