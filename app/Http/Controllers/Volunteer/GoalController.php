<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RollupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * الأهداف والمَعالِم (الدستور 24.4 · 23 — 1.7).
 *
 * سؤال الشاشة الواحد: «أين يقف عملي من الأهداف المعتمَدة للتنفيذ؟»
 *
 * ⭐ القاعدة الحاكمة: **لا شيء يظهر قبل «إرسال للتنفيذ»** — رحلة البناء كلّها
 * غير مرئيّة للمنفّذين، فنفلتر على حالة الهدف قبل أيّ شيء آخر (23 — القسم 1).
 */
class GoalController extends Controller
{
    public function __construct(
        private readonly RollupService $rollup,
        private readonly EntityScope $scope,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $entityIds = $this->scope->visibleEntityIds($user, 'goals.view');

        // ثلاثة فلاتر ظاهرة + بحث (2.15-أ-4)
        $filters = [
            'status' => $request->string('status')->toString(),
            'priority' => $request->string('priority')->toString(),
            'entity' => $request->integer('entity') ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $goals = $this->visibleGoals($entityIds, $filters);
        $tree = $this->treeFor($goals, $entityIds, $filters['entity']);

        return view('volunteer.goals.index', [
            'goals' => $goals,
            'tree' => $tree,
            'filters' => $filters,
            'statuses' => $this->statusLabels(),
            'priorities' => $this->priorityLabels(),
            'memberships' => $this->scope->memberships($user),
            'kpis' => $this->kpis($goals, $tree),
            'canDeclare' => $user->allows('wp_items.edit'),
            'canApprove' => $user->allows('milestones.edit'),
            'rollup' => $this->rollup,
        ]);
    }

    /**
     * إعلان تحقّق المعيار بدليل مرفق (دايركتور الكيان) ⟵ يعتمده مشرف المسار
     * خلال نافذة `workflow.escalation.window_hours` (افتراضي 24 ساعة).
     */
    public function declare(Request $request, Milestone $milestone): RedirectResponse
    {
        $data = $request->validate([
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
        ], [], [
            'evidence' => 'الدليل',
        ]);

        abort_unless($this->milestoneIsVisible($request->user(), $milestone), 403);

        $hours = (int) setting('goals.verification.approval_hours', setting('workflow.escalation.window_hours', 24));

        $milestone->forceFill([
            'verification_status' => 'declared',
            'declared_by' => $request->user()->id,
            'declared_at' => now(),
            'evidence_note' => $data['evidence'],
            'evidence_path' => $data['evidence_path'] ?? $milestone->evidence_path,
            'approval_due_at' => now()->addHours($hours),
        ])->save();

        $this->notifyTrackSupervisors($milestone);

        return back()->with('status', 'اتسجّل إعلان التحقّق ✓ — مشرف المسار عنده '.$hours.' ساعة للاعتماد.');
    }

    /** اعتماد مشرف المسار للإعلان — وبه يُعلَّم المَعلَم متحقّقًا ✅ */
    public function approve(Request $request, Milestone $milestone): RedirectResponse
    {
        $decision = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($milestone->verification_status === 'declared', 409, 'مفيش إعلان تحقّق مفتوح على هذا المَعلَم.');

        $approved = $decision['decision'] === 'approve';

        $milestone->forceFill([
            'verification_status' => $approved ? 'approved' : 'rejected',
            'is_verified' => $approved,
            'verified_by' => $request->user()->id,
            'verified_at' => $approved ? now() : null,
        ])->save();

        if ($milestone->declared_by && ($director = User::query()->find($milestone->declared_by))) {
            Integrations::notify(
                user: $director,
                category: 'goal',
                title: $approved ? 'اتعتمد تحقّق «'.$milestone->name.'»' : 'مراجعة على إعلان «'.$milestone->name.'»',
                body: $decision['note'] ?? null,
                url: route('volunteer.goals'),
            );
        }

        return back()->with('status', $approved ? 'اتعتمد التحقّق ✓' : 'اترجّع الإعلان مع الملاحظة.');
    }

    // ------------------------------------------------------------------ داخليّ

    /** الحالات التي تُعرَض — «إرسال للتنفيذ» فما بعد، والباقي غير مرئيّ أصلًا */
    private function visibleStatuses(): array
    {
        $value = setting('goals.visible_statuses', ['sent_to_execution', 'active', 'completed', 'closed']);

        return is_array($value) && $value !== [] ? array_values($value) : ['sent_to_execution', 'active', 'completed', 'closed'];
    }

    /** @return Collection<int,Goal> */
    private function visibleGoals(?array $entityIds, array $filters): Collection
    {
        return Goal::query()
            // ⭐ الحاجز الأوّل: لا شيء قبل «إرسال للتنفيذ»
            ->whereIn('status', $this->visibleStatuses())
            ->whereNotNull('sent_to_execution_at')
            ->when($entityIds !== null, fn ($q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('milestones')
                    ->join('work_packages', 'work_packages.milestone_id', '=', 'milestones.id')
                    ->whereColumn('milestones.goal_id', 'goals.id')
                    ->whereIn('work_packages.entity_id', $entityIds ?: [0])
            ))
            ->when($filters['entity'], fn ($q, $entity) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('milestones')
                    ->join('work_packages', 'work_packages.milestone_id', '=', 'milestones.id')
                    ->whereColumn('milestones.goal_id', 'goals.id')
                    ->where('work_packages.entity_id', $entity)
            ))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['priority'] !== '', fn ($q) => $q->where('priority', (int) $filters['priority']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->orderByDesc('sent_to_execution_at')
            ->get();
    }

    /**
     * شجرة العرض: لكلّ هدف مَعالِمه، ولكلّ مَعلَم حزمه ونِسَبها
     * وعدد بنوده **المُغلَقة** موسومةً صراحةً (مستبعَدة من المقام).
     */
    private function treeFor(Collection $goals, ?array $entityIds, ?int $entityFilter): array
    {
        if ($goals->isEmpty()) {
            return [];
        }

        $milestones = Milestone::query()
            ->whereIn('goal_id', $goals->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $packages = WorkPackage::query()
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->when($entityIds !== null, fn ($q) => $q->whereIn('entity_id', $entityIds ?: [0]))
            ->when($entityFilter, fn ($q, $entity) => $q->where('entity_id', $entity))
            ->with('entity')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('milestone_id');

        $tree = [];

        foreach ($goals as $goal) {
            $rows = [];

            foreach ($milestones->where('goal_id', $goal->id) as $milestone) {
                $ownPackages = $packages->get($milestone->id, collect());

                $rows[] = [
                    'milestone' => $milestone,
                    'packages' => $ownPackages->map(fn (WorkPackage $package) => [
                        'package' => $package,
                        'counts' => $this->rollup->packageTaskCounts($package),
                    ])->all(),
                ];
            }

            $tree[$goal->id] = $rows;
        }

        return $tree;
    }

    /** أربعة كروت KPI بحدّ أقصى (2.15-أ-3) */
    private function kpis(Collection $goals, array $tree): array
    {
        $milestoneCount = $verified = $closedTasks = 0;

        foreach ($tree as $rows) {
            foreach ($rows as $row) {
                $milestoneCount++;
                $verified += $row['milestone']->is_verified ? 1 : 0;

                foreach ($row['packages'] as $package) {
                    $closedTasks += $package['counts']['closed'];
                }
            }
        }

        return [
            'goals' => $goals->count(),
            'progress' => $goals->isEmpty() ? 0 : round($goals->avg('progress_percent'), 1),
            'milestones' => $milestoneCount ? $verified.' / '.$milestoneCount : '0',
            'closed' => $closedTasks,
        ];
    }

    private function statusLabels(): array
    {
        return [
            'sent_to_execution' => 'أُرسِل للتنفيذ',
            'active' => 'نشط',
            'completed' => 'مكتمل',
            'closed' => 'مُغلَق',
        ];
    }

    private function priorityLabels(): array
    {
        return [1 => 'عالية', 2 => 'متوسّطة', 3 => 'منخفضة'];
    }

    /** لا يُعلِن التحقّق إلّا مَن يرى المَعلَم داخل كيانه */
    private function milestoneIsVisible(User $user, Milestone $milestone): bool
    {
        $entityIds = $this->scope->visibleEntityIds($user, 'goals.view');

        if ($entityIds === null) {
            return true;
        }

        return WorkPackage::query()
            ->where('milestone_id', $milestone->id)
            ->whereIn('entity_id', $entityIds ?: [0])
            ->exists();
    }

    private function notifyTrackSupervisors(Milestone $milestone): void
    {
        $goal = Goal::query()->find($milestone->goal_id);

        if ($goal?->created_by && ($supervisor = User::query()->find($goal->created_by))) {
            Integrations::notify(
                user: $supervisor,
                category: 'goal',
                title: 'إعلان تحقّق معيار: '.$milestone->name,
                body: 'محتاج اعتمادك خلال نافذة القرار.',
                url: route('volunteer.goals'),
                deadlineAt: $milestone->approval_due_at ? \Illuminate\Support\Carbon::parse($milestone->approval_due_at) : null,
                requiresAction: true,
            );
        }
    }
}
