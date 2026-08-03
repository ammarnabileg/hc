<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Volunteer\Goals\BuildAccess;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\FileDrafts;
use App\Services\Volunteer\Goals\GoalBuildService;
use App\Services\Volunteer\Goals\GoalLaunchService;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RollupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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
        private readonly GoalLaunchService $launcher,
        private readonly BuildAccess $access,
        private readonly GoalBuildService $build,
        private readonly FileDrafts $fileDrafts,
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
        ]);
    }

    /**
     * ⭐ شاشة إطلاق الهدف — المعاينة النهائيّة (23 — 1.5).
     *
     * سؤالها الواحد: «إيه الأهداف الجاهزة للإرسال للتنفيذ، وإيه اللي ناقصها؟»
     * وفعلها الرئيسيّ واحد: **«إرسال للتنفيذ»** — ومعه فحص التغطية الآليّ،
     * فلا تُرفَع للقمّة قائمة نواقص يكتشفها الناس بعد الإطلاق.
     */
    public function launch(Request $request): View
    {
        $goals = Goal::query()
            // ما لم يُطلَق بعدُ وحده — وما أُطلِق مكانه شاشة «الأهداف والمَعالِم»
            ->whereNull('sent_to_execution_at')
            ->orderBy('end_date')
            ->orderByDesc('id')
            ->limit((int) setting('goals.launch.rows', 20))
            ->get();

        return view('volunteer.goals.launch', [
            'rows' => $goals->map(fn (Goal $goal) => [
                'goal' => $goal,
                'gaps' => $this->launcher->gaps($goal),
            ]),
            'windowHours' => $this->launcher->windowHours(),
        ]);
    }

    /**
     * «إرسال للتنفيذ» — وبه **تبدأ نافذة التفكيك** فيُختَم `breakdown_due_at`
     * على مهامّ الهدف (23-3.9-١). والزرّ يرفض الضغط بقائمة النواقص إن وُجدت.
     */
    public function send(Request $request, Goal $goal): RedirectResponse
    {
        $result = $this->launcher->launch($goal, $request->user());

        if (! $result['ok']) {
            return back()->with('status', 'مش هيتبعت: '.implode(' · ', $result['gaps']));
        }

        AuditTrail::log($request->user(), 'goal.sent_to_execution', $goal, [], [
            'breakdown_due_at' => $result['due_at']?->toDateTimeString(),
            'tasks_stamped' => $result['stamped'],
        ]);

        return redirect()->route('volunteer.goals')->with(
            'status',
            'اتبعت للتنفيذ ✓ — نافذة التفكيك بدأت لـ'.$result['stamped'].' مهمّة، وبتقفل '
                .$result['due_at']?->format('Y-m-d H:i').'.',
        );
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

    // ============================================================================
    //  ⭐ رحلة بناء الهدف — المرحلة صفر (23 — 1.1 … 1.4)
    // ============================================================================

    /**
     * لوحة الرحلة: ما زال في البناء **ولا يراه إلّا مَن له فيه دور**.
     *
     * الفلترة بـ`canSeeBuild` لا بالصلاحيّة وحدها — فالكوردنيتور والتيم ليدر
     * داخل الكيان نفسه يفتحان الصفحة فيجدانها فارغة، ولا تسرّب لهم اسم هدف.
     */
    public function build(Request $request): View
    {
        $user = $request->user();

        $goals = Goal::query()
            ->whereNull('sent_to_execution_at')
            ->orderByDesc('id')
            ->limit((int) setting('goals.build.rows', 30))
            ->get()
            ->filter(fn (Goal $goal) => $this->access->canSeeBuild($user, $goal))
            ->values();

        return view('volunteer.goals.build.index', [
            'goals' => $goals,
            'stages' => $this->stageLabels(),
            'tracksOf' => $goals->mapWithKeys(fn (Goal $goal) => [
                $goal->id => Track::query()->whereIn('id', $this->access->goalTrackIds($goal))->pluck('name_ar')->all(),
            ])->all(),
            'layer' => $this->access->layerOf($user),
            'tracks' => Track::query()->where('is_active', true)->orderBy('id')->get(),
            'canCreate' => $user->allows('goals.create'),
            'canBreakdown' => $user->allows('milestones.create'),
            'canFill' => $user->allows('wp_items.create'),
            'canAggregate' => $user->allows('milestones.edit'),
        ]);
    }

    /** 1.1 — فورم «هدف جديد» (تسعة حقول ⟵ Stepper بحفظ تلقائيّ — 2.15-ب) */
    public function create(Request $request): View
    {
        return view('volunteer.goals.build.create', [
            'priorities' => $this->priorityLabels(),
            'tracks' => Track::query()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    /**
     * حفظ الهدف — و**معيار التحقّق شرط الحفظ** يُفرَض في الخدمة على الخادم:
     * «هدف بلا معيار تحقّق لا يُحفَظ» (23 — 1.1).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'integer', 'in:1,2,3'],
            'end_date' => ['required', 'date'],
            'verification_type' => ['required', 'in:numeric,boolean'],
            'target_from' => ['nullable', 'numeric'],
            'target_to' => ['nullable', 'numeric'],
            'verification_statement' => ['nullable', 'string', 'max:255'],
            'tracks' => ['nullable', 'array'],
            'tracks.*' => ['integer', 'exists:tracks,id'],
        ], [], [
            'name' => 'اسم الهدف',
            'reason' => 'سبب الهدف',
            'end_date' => 'تاريخ النهاية',
        ]);

        try {
            $goal = $this->build->createGoal($data, $request->user());
        } catch (ValidationException $e) {
            // المُدخَل يبقى كما هو والقيد يُشرَح لحظة كسره (2.15-د · 2.17-ب)
            return back()->withInput()->withErrors($e->errors());
        }

        AuditTrail::log($request->user(), 'goal.created', $goal, [], ['verification_type' => $goal->verification_type]);

        if (($data['tracks'] ?? []) !== []) {
            $result = $this->build->linkTracks($goal, $data['tracks'], $request->user());

            return redirect()->route('volunteer.goals.build')->with(
                'status',
                'اتحفظ ✓ — واتربط بـ'.count($result['linked']).' مسار، ووصل إشعار لـ'.$result['notified'].' من مشرفي المسارات المعنيّين.',
            );
        }

        return redirect()->route('volunteer.goals.build')->with(
            'status',
            'اتحفظ ✓ — ولسّه محدّش شايفه: اربطه بمسار عشان يوصل لمشرفيه.',
        );
    }

    /** الربط بمسار أو أكثر — **لحظة ظهور الهدف** وإشعار مشرفيه وحدهم */
    public function linkTracks(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');

        $data = $request->validate([
            'tracks' => ['required', 'array', 'min:1'],
            'tracks.*' => ['integer', 'exists:tracks,id'],
        ]);

        try {
            $result = $this->build->linkTracks($goal, $data['tracks'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'اتربط بـ'.count($result['linked']).' مسار ✓ — وصل الإشعار لـ'.$result['notified'].' من مشرفي المسارات المعنيّين.');
    }

    /** 1.2 — شاشة التفكيك: مَعالِم وحزم مربوطة بكيانات **مسار المشرف وحده** */
    public function breakdown(Request $request, Goal $goal): View
    {
        $user = $request->user();

        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');
        abort_unless($this->access->canSeeBuild($user, $goal), 403);

        $milestones = Milestone::query()->where('goal_id', $goal->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        $packages = WorkPackage::query()
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->with('entity')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('milestone_id');

        return view('volunteer.goals.build.breakdown', [
            'goal' => $goal,
            'milestones' => $milestones,
            'packages' => $packages,
            'entities' => $this->scope->linkableEntities($user),
            // مسودّات هذا الهدف تُعرَض بجانب الكيانات القائمة، **موسومةً** بأنّها
            // لم تُفتَح بعد — فلا يظنّها أحدٌ ملفًّا شغّالًا (2.16-ج: اللون لا يكفي)
            'fileDrafts' => $this->fileDrafts->draftsFor($goal),
            'canOpenFiles' => $user->allows('work_packages.create')
                && $this->fileDrafts->canCreate($user)
                && $this->access->holds($user, $goal),
            'invitablePositions' => $this->fileDrafts->invitablePositions(),
            'canWrite' => $user->allows('milestones.create') && $this->access->holds($user, $goal),
            'canLinkPackages' => $user->allows('work_packages.create') && $this->access->holds($user, $goal),
            'lockMessage' => $this->access->lockMessage($goal),
            'nextPackageName' => $this->build->defaultPackageName(1),
        ]);
    }

    public function storeMilestone(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);
        $this->access->assertHolds($request->user(), $goal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'verification_criteria' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ], [], ['name' => 'اسم المَعلَم']);

        abort_if(
            Milestone::query()->where('goal_id', $goal->id)->count() >= (int) setting('goals.build.max_milestones', 50),
            422,
            'وصلت السقف المسموح للمَعالِم في الهدف الواحد.',
        );

        $this->build->createMilestone($goal, $data, $request->user());

        return back()->with('status', 'اتضاف المَعلَم ✓ — دلوقتي اربط حزمه بكياناتك.');
    }

    /**
     * إنشاء الحزم وربطها بالكيانات — كيانًا واحدًا أو **كلّ كيانات المسار بضغطة**
     * (حزمة لكلّ كيان)، وهو حلّ العمل المشترك داخل مَعلَم واحد (23 — 1.2).
     */
    public function storePackages(Request $request, Milestone $milestone): RedirectResponse
    {
        $goal = Goal::query()->findOrFail($milestone->goal_id);

        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);
        $this->access->assertHolds($request->user(), $goal);

        $data = $request->validate([
            'mode' => ['required', 'in:single,bulk'],
            'entity_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $entityIds = $data['mode'] === 'bulk'
            ? $this->scope->linkableEntities($request->user())->pluck('id')->all()
            : array_filter([$data['entity_id'] ?? null]);

        try {
            $created = $this->build->attachPackages($milestone, $entityIds, $request->user(), $data['name'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'اترَبطت '.$created->count().' حزمة بكيانات مسارك ✓ — دايركتور كلّ كيان وصله إشعار.');
    }

    /**
     * 1.2 — **مسودّة ملفّ** جديدة أثناء البناء (سيناريو مشرف عام الملفّات).
     *
     * تُنشأ ولا تُفتَح: الدعوات صفوفٌ بلا أثر، ولا إشعار يصل لأحدٍ الآن — فلا
     * يُدعى أحدٌ إلى ملفٍّ قد لا يُفتَح أصلًا لو لم تضغط القمّة «إرسال للتنفيذ».
     */
    public function storeFileDraft(Request $request, Goal $goal): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');
        abort_unless($this->access->canSeeBuild($user, $goal), 403);
        $this->access->assertHolds($user, $goal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'invitations' => ['nullable', 'array', 'max:'.(int) setting('goals.build.file_draft.max_invitations', 20)],
            'invitations.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'invitations.*.position_id' => ['required', 'integer', 'exists:positions,id'],
        ], [], ['name' => 'اسم الملفّ']);

        $entity = $this->fileDrafts->create($user, $goal, $data['name'], $data['invitations'] ?? []);

        return back()->with('status', 'اتعملت مسودّة ملفّ «'.$entity->name_ar.'» ✓ — اربط بيها حزمك، وهتتفعّل بدعواتها لحظة «إرسال للتنفيذ» مش قبلها.');
    }

    /** 1.4 — التجميع والتسعير: تعديل مباشر بحفظ تلقائيّ + سجلّ «تمّ التعديل» */
    public function aggregate(Request $request, Goal $goal): View
    {
        $user = $request->user();

        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');
        abort_unless($this->access->canSeeBuild($user, $goal), 403);

        $milestones = Milestone::query()->where('goal_id', $goal->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        $packages = WorkPackage::query()
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->with('entity.track')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $tasks = Task::query()
            ->whereIn('work_item_id', WorkItem::query()->whereIn('work_package_id', $packages->pluck('id'))->select('id'))
            ->with('work_item')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => (int) ($task->work_item?->work_package_id ?? 0));

        $holds = $this->access->holds($user, $goal);

        return view('volunteer.goals.build.aggregate', [
            'goal' => $goal,
            'milestones' => $milestones,
            'packages' => $packages->groupBy('milestone_id'),
            'tasks' => $tasks,
            'icons' => $packages->mapWithKeys(fn (WorkPackage $p) => [$p->id => $this->access->entityIcon($p->entity)])->all(),
            'edited' => $this->build->editedFields($goal),
            'canEdit' => $holds && $user->allows('milestones.edit'),
            'canPrice' => $holds && $this->access->layerOf($user) === BuildAccess::LAYER_TRACK,
            'canRaisePreview' => $holds && $this->access->layerOf($user) === BuildAccess::LAYER_TRACK
                && $this->build->allPackagesSubmitted($goal),
            'canDeleteMilestone' => $holds && $user->allows('milestones.delete'),
            'canDeletePackage' => $holds && $user->allows('work_packages.delete'),
            'canDeleteTask' => $holds && $user->allows('wp_items.delete'),
            'lockMessage' => $this->access->lockMessage($goal),
            'holder' => $this->access->holderOf($goal),
            'debounce' => (int) setting('goals.build.autosave_debounce_ms', 600),
        ]);
    }

    /**
     * ⭐ الحفظ التلقائيّ لكلّ إنبوت — **في صفّه الحقيقيّ** لا في مسودّة جانبيّة،
     * فما يُحفَظ يعود إلى الحقل نفسه عند إعادة الفتح.
     */
    public function saveField(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');
        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);

        // ⛔ القفل الطبقيّ يُفحَص هنا — لا بإخفاء الزرّ في الواجهة
        $this->access->assertHolds($request->user(), $goal);

        $data = $request->validate([
            'subject' => ['required', 'in:goal,milestone,package,task'],
            'id' => ['required', 'integer'],
            'field' => ['required', 'string', 'max:48'],
            'value' => ['nullable'],
        ]);

        // التسعير لمشرف المسار وحده: «هو من يضيف قيمة VXP لكلّ مهمّة» (23 — 1.4)
        if ($data['field'] === 'vxp_value') {
            abort_unless(
                in_array($this->access->layerOf($request->user()), [BuildAccess::LAYER_TRACK, BuildAccess::LAYER_TOP], true),
                403,
                'التسعير لمشرف المسار.',
            );
        }

        $result = $this->build->saveField(
            $goal, $data['subject'], (int) $data['id'], $data['field'], $data['value'] ?? '', $request->user(),
        );

        return response()->json([
            'ok' => true,
            'message' => 'اتحفظ ✓',
            'edited_label' => 'تمّ التعديل',
        ] + $result);
    }

    /** بوب-أب «تمّ التعديل» — كلّ تعديلات هذا الحقل بعينه: مَن · متى · ماذا كان */
    public function fieldRevisions(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);

        $data = $request->validate([
            'subject' => ['required', 'in:goal,milestone,package,task'],
            'id' => ['required', 'integer'],
            'field' => ['required', 'string', 'max:48'],
        ]);

        return response()->json([
            'label' => GoalBuildService::EDITABLE[$data['subject']][$data['field']] ?? $data['field'],
            'rows' => $this->build->revisions($data['subject'], (int) $data['id'], $data['field']),
        ]);
    }

    /**
     * «إضافة مهامّ جديدة **مربوطة بالكيانات**» في طريق الرجوع (23 — 1.4)،
     * ومالكها دايركتور الكيان المربوط لا كاتبها.
     */
    public function storeAggregateTask(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($this->access->isBuilding($goal), 409, 'الهدف اتبعت للتنفيذ خلاص.');
        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);
        $this->access->assertHolds($request->user(), $goal);

        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'deliverable_spec' => ['required', 'string', 'max:2000'],
            'brief' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'title' => 'اسم المهمّة',
            'deliverable_spec' => 'شكل المخرجات',
        ]);

        $package = WorkPackage::query()
            ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
            ->find($data['package_id']);

        abort_if($package === null, 404, 'الحزمة دي مش تابعة للهدف ده.');

        $this->build->addTaskForEntity($package, $data, $request->user());

        return back()->with('status', 'اتضافت المهمّة على «'.$package->name.'» ✓ — مربوطة بكيانها ومالكها دايركتوره.');
    }

    /** «رفع معاينة للهدف» ⟵ القفل الطبقيّ: الحيازة للقمّة، والرافع قارئ فقط */
    public function raisePreview(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($this->access->canSeeBuild($request->user(), $goal), 403);
        $this->access->assertHolds($request->user(), $goal);

        abort_unless(
            $this->access->layerOf($request->user()) === BuildAccess::LAYER_TRACK,
            403,
            'الرفع للمعاينة من مشرف المسار.',
        );

        abort_unless(
            $this->build->allPackagesSubmitted($goal),
            409,
            'في حزم لسّه ما رفعهاش دايركتورها للمراجعة.',
        );

        $this->build->raisePreview($goal, $request->user());

        AuditTrail::log($request->user(), 'goal.preview_raised', $goal, [], ['edit_holder' => BuildAccess::LAYER_TOP]);

        return redirect()->route('volunteer.goals.build')->with(
            'status',
            'اترفعت المعاينة ✓ — التحرير بقى عند القمّة، وإنت قارئ بس دلوقتي.',
        );
    }

    /** الحذف في المعاينة النهائيّة — للقمّة وحدها، وبتأكيد «لا» فيه أكبر من «نعم» */
    public function destroyMilestone(Request $request, Milestone $milestone): RedirectResponse
    {
        $goal = Goal::query()->findOrFail($milestone->goal_id);
        $this->access->assertHolds($request->user(), $goal);

        $name = $milestone->name;
        $milestone->delete();

        AuditTrail::log($request->user(), 'goal.milestone_deleted', $goal, ['name' => $name], []);

        return back()->with('status', 'اتمسح المَعلَم «'.$name.'» ✓');
    }

    public function destroyPackage(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $goal = Goal::query()->findOrFail(Milestone::query()->whereKey($workPackage->milestone_id)->value('goal_id'));
        $this->access->assertHolds($request->user(), $goal);

        $name = $workPackage->name;
        $workPackage->delete();

        AuditTrail::log($request->user(), 'goal.package_deleted', $goal, ['name' => $name], []);

        return back()->with('status', 'اتمسحت الحزمة «'.$name.'» ✓');
    }

    public function destroyTask(Request $request, Task $task): RedirectResponse
    {
        $package = WorkPackage::query()->find(WorkItem::query()->whereKey($task->work_item_id)->value('work_package_id'));
        $goal = Goal::query()->find(Milestone::query()->whereKey($package?->milestone_id)->value('goal_id'));

        abort_if($goal === null, 404);
        $this->access->assertHolds($request->user(), $goal);

        $title = $task->title;
        $task->delete();

        AuditTrail::log($request->user(), 'goal.task_deleted', $goal, ['title' => $title], []);

        return back()->with('status', 'اتمسحت المهمّة «'.$title.'» ✓');
    }

    /** تسميات مراحل الرحلة — نصوص من الإعدادات لا محروقة (2.13) */
    private function stageLabels(): array
    {
        $labels = setting('goals.build.stage_labels', [
            'draft' => 'مسودّة — لسّه محدّش شايفه',
            'linked' => 'اتربط بمسار',
            'breakdown' => 'تفكيك',
            'filling' => 'ملء الحزم',
            'aggregation' => 'تجميع وتسعير',
            'preview' => 'معاينة عند القمّة',
            'executed' => 'اتبعت للتنفيذ',
        ]);

        return is_array($labels) ? $labels : [];
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
                deadlineAt: $milestone->approval_due_at ? Carbon::parse($milestone->approval_due_at) : null,
                requiresAction: true,
            );
        }
    }
}
