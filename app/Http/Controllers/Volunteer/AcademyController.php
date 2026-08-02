<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\VolunteerRecording;
use App\Services\Volunteer\People\AcademyService;
use App\Services\Volunteer\People\RecordingRewards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الأكاديمية (13.4-ل · 24.4-9): التدريبات والتسجيلات.
 *
 * **بلا أيّ شارة تسويقيّة على الكروت** — والقيمة تظهر في المحتوى لا في الملصقات.
 */
class AcademyController extends Controller
{
    public function __construct(
        private readonly AcademyService $academy,
        private readonly RecordingRewards $recordings,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $paths = $this->academy->paths($user);

        $progress = $paths->mapWithKeys(fn (LearningPath $p) => [$p->id => $this->academy->progress($user, $p)]);

        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        $visible = $paths
            ->when($q !== '', fn ($c) => $c->filter(fn ($p) => str_contains((string) $p->name_ar, $q)))
            ->when($status !== '', fn ($c) => $c->filter(function ($p) use ($progress, $status) {
                $percent = $progress[$p->id]['percent'];

                return match ($status) {
                    'not_started' => $percent === 0,
                    'in_progress' => $percent > 0 && $percent < 100,
                    'done' => $percent >= 100,
                    default => true,
                };
            }))
            ->values();

        return view('volunteer.academy.index', [
            'paths' => $visible,
            'progress' => $progress,
            'academy' => $this->academy,
            'filters' => ['status' => $status, 'q' => $q],
            'completedPaths' => $progress->filter(fn ($p) => $p['complete'])->count(),
        ]);
    }

    /** داخل المسار: Roadmap رأسيّ بمحطّات الكورسات — **نفس علامة الإكمال بلا تمييز** */
    public function path(Request $request, LearningPath $path): View
    {
        abort_unless((bool) $path->is_academy, 404);

        $progress = $this->academy->progress($request->user(), $path);

        return view('volunteer.academy.path', [
            'path' => $path,
            'progress' => $progress,
            'academy' => $this->academy,
            // ⭐ الزرّ بعد 100% فقط ولمسار مربوط — وقبلها بار صامت بلا CTA
            'showCta' => $this->academy->showCertificateCta($progress),
            'completionMessage' => $this->academy->completionMessage($progress),
        ]);
    }

    // ------------------------------------------------------------ التسجيلات

    public function recordings(Request $request): View
    {
        $user = $request->user();
        $entityIds = $this->academy->myEntityIds($user);

        $filters = [
            'entity' => $request->integer('entity') ?: null,
            'has_otp' => $request->boolean('has_otp'),
            'sort' => $request->string('sort')->toString() ?: 'newest',
            'q' => $request->string('q')->toString(),
        ];

        $items = $this->recordings->listFor($user, $entityIds, $filters);

        return view('volunteer.academy.recordings', [
            'recordings' => $items,
            'claimed' => $this->recordings->claimedIds($user, $items),
            'rewards' => $this->recordings,
            'badge' => $this->recordings->rewardBadge(),
            'filters' => $filters,
        ]);
    }

    /** بوب-أب OTP — **التحقّق Server-side** ومنع تكرار الكسب */
    public function claim(Request $request, VolunteerRecording $recording): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'max:32'],
        ], [], ['otp' => 'الرمز']);

        $result = $this->recordings->claim($recording, $request->user(), $data['otp']);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', $result['message']);
    }

    public function reportBroken(Request $request, VolunteerRecording $recording): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->recordings->reportBroken($recording, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'وصلنا البلاغ ✓ هنراجعه مع اللي رفع التسجيل.');
    }
}
