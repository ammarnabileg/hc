<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\RecurringGenerator;
use App\Services\Volunteer\Goals\RollupService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * المشروع التشغيليّ للكيان وبنوده المتكرّرة (الدستور 24.4 · 23 — 1.8).
 *
 * المشروع **وعاء دائم لا يُغلَق** يُنشأ مع الكيان — وهو ما يحقّق شرط
 * «كلّ مهمّة مربوطة ببند» دون خنق العمل اليوميّ.
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly EntityScope $scope,
        private readonly RollupService $rollup,
        private readonly RecurringGenerator $generator,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $entityId = $this->scope->currentEntityId($user, $request->integer('entity') ?: null);

        $project = $entityId
            ? Project::query()->where('entity_id', $entityId)->where('type', 'operational')->with('entity')->first()
            : null;

        $filters = [
            'recurrence' => $request->string('recurrence')->toString(),
            'audience' => $request->string('audience')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $items = $this->recurringItems($project, $filters);

        return view('volunteer.goals.project', [
            'project' => $project,
            'entityId' => $entityId,
            'items' => $items,
            'filters' => $filters,
            'recurrences' => $this->recurrenceLabels(),
            'audiences' => $this->audienceLabels(),
            'memberships' => $this->scope->memberships($user),
            'history' => $this->historyFor($items),
        ]);
    }

    /**
     * «نوبتي من البنود المتكرّرة» — تايم-لاين أسبوعيّ لما يصل إليّ شخصيًّا،
     * وعليه ملاحظة **«وُجِّه إليك لأنّك الأقلّ حملًا حاليًّا»** حين يكون من الموازن.
     */
    public function shift(Request $request): View
    {
        $user = $request->user();

        $weekOffset = (int) $request->integer('week');
        $start = Carbon::now()->startOfWeek(Carbon::SATURDAY)->addWeeks($weekOffset);
        $end = $start->copy()->addDays(7);

        $filters = [
            'status' => $request->string('status')->toString(),
            'item' => $request->integer('item') ?: null,
        ];

        $tasks = Task::query()
            ->where('owner_id', $user->id)
            ->where('source', 'recurring')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['item'], fn ($q, $item) => $q->where('work_item_id', $item))
            ->with('work_item', 'entity')
            ->orderBy('deadline_at')
            ->get();

        $days = [];
        $cursor = $start->copy();

        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->toDateString();

            $days[] = [
                'date' => $cursor->copy(),
                'is_today' => $cursor->isToday(),
                'tasks' => $tasks->filter(fn (Task $t) => Carbon::parse($t->created_at)->toDateString() === $key)->values(),
            ];

            $cursor->addDay();
        }

        return view('volunteer.goals.recurring', [
            'days' => $days,
            'tasks' => $tasks,
            'week' => $start,
            'weekOffset' => $weekOffset,
            'filters' => $filters,
            'statuses' => $this->statusLabels(),
            'items' => WorkItem::query()->whereIn('id', $tasks->pluck('work_item_id')->filter())->get(),
            'rollup' => $this->rollup,
            'load' => $this->generator->loadOf($user),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int,WorkItem> */
    private function recurringItems(?Project $project, array $filters): Collection
    {
        if (! $project) {
            return collect();
        }

        $packageIds = WorkPackage::query()->where('project_id', $project->id)->pluck('id');

        return WorkItem::query()
            ->whereIn('work_package_id', $packageIds)
            ->where('is_recurring', true)
            ->where('is_archived', false)
            ->when($filters['recurrence'] !== '', fn ($q) => $q->where('recurrence', $filters['recurrence']))
            ->when($filters['audience'] !== '', fn ($q) => $q->where('audience_mode', $filters['audience']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->with('assigned_user')
            ->orderBy('id')
            ->get();
    }

    /**
     * سجلّ التوليدات وما آل إليه كلّ توليد (مُسلَّم · متأخّر · **فائتة**).
     *
     * @return array<int,Collection<int,Task>>
     */
    private function historyFor(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $limit = (int) setting('recurring.history.rows', 10);

        return Task::query()
            ->whereIn('work_item_id', $items->pluck('id'))
            ->where('source', 'recurring')
            ->with('owner')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('work_item_id')
            ->map(fn (Collection $rows) => $rows->take($limit))
            ->all();
    }

    private function recurrenceLabels(): array
    {
        return [
            'daily' => (string) setting('workflow.projects.recurrence_labels_msg', 'يوميّ'),
            'weekly' => (string) setting('workflow.projects.recurrence_labels_msg_2', 'أسبوعيّ'),
            'biweekly' => (string) setting('workflow.projects.recurrence_labels_msg_3', 'كلّ أسبوعين'),
            'monthly' => (string) setting('workflow.projects.recurrence_labels_msg_4', 'شهريّ'),
        ];
    }

    private function audienceLabels(): array
    {
        return [
            'individual' => (string) setting('workflow.projects.audience_labels_msg', 'فرد بعينه'),
            'rotation' => (string) setting('workflow.projects.audience_labels_msg_2', 'تناوب موزون بالموازن'),
            'public_board' => (string) setting('workflow.projects.audience_labels_msg_3', 'مرشَّح ليكون عامًّا'),
        ];
    }

    private function statusLabels(): array
    {
        return [
            'in_progress' => (string) setting('workflow.projects.status_labels_msg', 'قيد التنفيذ'),
            'in_review' => (string) setting('workflow.projects.status_labels_msg_2', 'قيد المراجعة'),
            'approved' => (string) setting('workflow.projects.status_labels_msg_3', 'معتمدة'),
            'no_delivery' => (string) setting('workflow.projects.status_labels_msg_4', 'فائتة'),
            'closed' => (string) setting('workflow.projects.status_labels_msg_5', 'مُغلَقة'),
        ];
    }
}
