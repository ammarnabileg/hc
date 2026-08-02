<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Kudos;
use App\Models\ThanksWallPost;
use App\Models\User;
use App\Services\Volunteer\People\KudosService;
use App\Services\Volunteer\People\ThanksWall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التقدير (13.4-ي · 24.4-11): Kudos + حائط الشكر (نادي التميّز).
 *
 * **السبب المكتوب هو بطل الشاشة** — والعدّاد مجرّد حارس على الحدود.
 */
class KudosController extends Controller
{
    public function __construct(
        private readonly KudosService $kudos,
        private readonly ThanksWall $wall,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString() ?: 'received';

        $filters = [
            'days' => $request->integer('days') ?: (int) setting('ux.lists.default_range_days', 30),
            'q' => $request->string('q')->toString(),
        ];

        return view('volunteer.people.kudos.index', [
            'tab' => $tab,
            'items' => $tab === 'sent' ? $this->kudos->sent($user, $filters) : $this->kudos->received($user, $filters),
            'kudos' => $this->kudos,
            'filters' => $filters,
            'sentToday' => $this->kudos->sentToday($user),
            'peopleThisWeek' => $this->kudos->peopleThisWeek($user),
            'dailyLimit' => $this->kudos->dailyLimit(),
            'weeklyLimit' => $this->kudos->weeklyPeopleLimit(),
            'totalReceived' => Kudos::query()->where('receiver_id', $user->id)->count(),
            'canSend' => $user->allows('kudos.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], [
            'receiver_id' => 'الزميل',
            'reason' => 'سبب الشكر',
        ]);

        try {
            $this->kudos->send($request->user(), User::findOrFail($data['receiver_id']), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage())->withInput();
        }

        return back()->with('status', 'اتبعت ✓ وصلت لزميلك.');
    }

    /** بحث حيّ عن الزميل بالكود/الاسم داخل بوب-أب الشكر */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $results = $q === '' ? collect() : User::query()
            ->whereKeyNot($request->user()->id)
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
            ->limit(8)
            ->get(['id', 'name', 'code']);

        return response()->json(['results' => $results]);
    }

    // ------------------------------------------------------------ حائط الشكر

    public function wall(Request $request): View
    {
        $user = $request->user();
        $progress = $this->wall->personalProgress($user);

        return view('volunteer.people.kudos.wall', [
            'tab' => $request->string('tab')->toString() ?: 'wall',
            'members' => $this->wall->members(),
            'approaching' => $this->wall->approaching(),
            'progress' => $progress,
            'threshold' => $this->wall->threshold(),
            'daysToReset' => $this->wall->daysToReset(),
            'posts' => $this->wall->discussion(),
            // دخول النادي ⟵ احتفال ذروة، ومرّة واحدة بحكم نظام الاحتفالات (2.14)
            'celebration' => $this->wall->celebrateEntry($user),
            'canPost' => $user->allows('thanks_wall.create'),
        ]);
    }

    public function post(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [], ['body' => 'النصّ']);

        try {
            $this->wall->post($request->user(), $data['body']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'اتنشر ✓');
    }

    public function vote(Request $request, ThanksWallPost $post): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1'],
        ]);

        $total = $this->wall->vote($post, $request->user(), (int) $data['value']);

        if ($request->expectsJson()) {
            return response()->json(['votes' => $total]);
        }

        return back();
    }
}
