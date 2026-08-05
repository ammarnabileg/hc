<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\Position;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Tasks\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ رحلة بناء الهدف — المرحلة صفر (الدستور 23 — 1.1 … 1.4).
 *
 * كلّ قاعدةٍ صلبة في الرحلة تُفرَض **هنا** لا في الفورم، لأنّ الفورم زينة أوّل
 * `curl`:
 *  - «هدف بلا معيار تحقّق **لا يُحفَظ**» (1.1) ⟵ `assertCriteria`.
 *  - «الهدف عند إنشائه **لا يراه أحد**؛ يظهر لحظة ربطه بمسار» (1.1) ⟵ الربط
 *    صفٌّ في `goal_tracks`، والإشعار **لمشرفي تلك المسارات وحدهم**.
 *  - «مشرف المسار … يربط كلّ حزمة بكيان **من مساره**» (1.2) ⟵ `EntityScope`.
 *  - «الدايركتور يضيف مهامًّا لنفسه **بلا حدّ أقصى**» (1.3) ⟵ لا سقف، وكلّ
 *    مهمّة تُربَط ببندٍ إجباريًّا كما يوجب القاموس.
 *  - «حفظ تلقائيّ فوريّ لكلّ إنبوت» + سجلّ «تمّ التعديل» (1.4) ⟵ `saveField`،
 *    **وتكتب في الصفّ الحقيقيّ** لا في مسودّةٍ جانبيّة: المسودّة التي لا تعود
 *    إلى الحقل عند إعادة الفتح تُضيّع العمل وتوهم صاحبه أنّه محفوظ.
 */
class GoalBuildService
{
    /** أسماء الحقول القابلة للتحرير — مفاتيح داخليّة، لا نصّ (2.13-ب) */
    public const EDITABLE_FIELD_KEYS = [
        'goal' => ['name', 'description', 'reason'],
        'milestone' => ['name', 'verification_criteria'],
        'package' => ['name'],
        'task' => ['title', 'brief', 'deliverable_spec', 'vxp_value'],
    ];

    /**
     * الحقول القابلة للتحرير المباشر بعناوينها — وما ليس هنا لا يُكتَب مهما
     * جاء في الطلب. العناوين من `setting()` لا محروقة (2.13).
     *
     * @return array<string, array<string, string>>
     */
    public static function editableFields(): array
    {
        return [
            'goal' => [
                'name' => (string) setting('goals.editable.goal_name', 'اسم الهدف'),
                'description' => (string) setting('goals.editable.goal_description', 'وصف الهدف'),
                'reason' => (string) setting('goals.editable.goal_reason', 'سبب الهدف'),
            ],
            'milestone' => [
                'name' => (string) setting('goals.editable.milestone_name', 'اسم المَعلَم'),
                'verification_criteria' => (string) setting('goals.editable.milestone_verification_criteria', 'معيار تحقّق المَعلَم'),
            ],
            'package' => [
                'name' => (string) setting('goals.editable.package_name', 'اسم الحزمة'),
            ],
            'task' => [
                'title' => (string) setting('goals.editable.task_title', 'اسم المهمّة'),
                'brief' => (string) setting('goals.editable.task_brief', 'بريف المهمّة'),
                'deliverable_spec' => (string) setting('goals.editable.task_deliverable_spec', 'شكل المخرجات'),
                'vxp_value' => (string) setting('goals.editable.task_vxp_value', 'قيمة نقاط الإنتاج (VXP)'),
            ],
        ];
    }

    public function __construct(
        private readonly EntityScope $scope,
        private readonly BuildAccess $access,
        private readonly FileDrafts $fileDrafts,
    ) {}

    // ------------------------------------------------------------ 1.1 إنشاء الهدف

    /**
     * «هدف بلا معيار تحقّق لا يُحفَظ» — والمعيار صورتان لا ثالثة لهما:
     * **رقم من X إلى Y**، أو **حالة قابلة للفحص بنعم/لا** بنصٍّ مكتوب.
     *
     * @throws ValidationException
     */
    public function assertCriteria(array $data): void
    {
        $message = (string) setting(
            'goals.build.error.criteria_required',
            'الهدف مش هيتحفظ من غير معيار تحقّق: حدّد مدى رقميًّا من X إلى Y، أو اكتب حالة تتفحص بنعم/لا.',
        );

        $type = $data['verification_type'] ?? null;

        if ($type === 'numeric') {
            $from = $data['target_from'] ?? null;
            $to = $data['target_to'] ?? null;

            if (! is_numeric($from) || ! is_numeric($to) || (float) $to <= (float) $from) {
                throw ValidationException::withMessages(['verification_type' => $message]);
            }

            return;
        }

        if ($type === 'boolean') {
            $statement = trim((string) ($data['verification_statement'] ?? ''));

            if (mb_strlen($statement) < (int) setting('goals.build.min_statement_chars', 5)) {
                throw ValidationException::withMessages(['verification_type' => $message]);
            }

            return;
        }

        throw ValidationException::withMessages(['verification_type' => $message]);
    }

    /** إنشاء الهدف — ويولَد **مسودّةً لا يراها أحد** حتى يُربَط بمسار */
    public function createGoal(array $data, User $actor): Goal
    {
        $this->assertCriteria($data);

        $numeric = $data['verification_type'] === 'numeric';

        return Goal::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'reason' => $data['reason'] ?? null,
            'verification_type' => $data['verification_type'],
            'target_from' => $numeric ? $data['target_from'] : null,
            'target_to' => $numeric ? $data['target_to'] : null,
            'verification_statement' => $numeric ? null : trim((string) $data['verification_statement']),
            'end_date' => $data['end_date'] ?? null,
            'priority' => $data['priority'] ?? 2,
            'status' => 'draft',
            'build_stage' => 'draft',
            'edit_holder' => BuildAccess::LAYER_TOP,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * ربط الهدف بمسار أو أكثر — **وهي لحظة ظهوره**. والإشعار يذهب لمشرفي
     * المسارات المعنيّين وحدهم: «لا لغيرهم».
     *
     * @param  list<int>  $trackIds
     * @return array{linked: list<int>, notified: int}
     */
    public function linkTracks(Goal $goal, array $trackIds, User $actor): array
    {
        $trackIds = collect($trackIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($trackIds === []) {
            throw ValidationException::withMessages([
                'tracks' => (string) setting('goals.build.error.tracks_required', 'اختار مسارًا واحدًا على الأقلّ — الهدف مايظهرش لحدّ قبل الربط.'),
            ]);
        }

        $fresh = [];

        foreach ($trackIds as $trackId) {
            $exists = DB::table('goal_tracks')->where('goal_id', $goal->id)->where('track_id', $trackId)->exists();

            if ($exists) {
                continue;
            }

            DB::table('goal_tracks')->insert([
                'goal_id' => $goal->id,
                'track_id' => $trackId,
                'linked_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $fresh[] = $trackId;
        }

        $goal->forceFill([
            'build_stage' => 'linked',
            // ⭐ القفل الطبقيّ يبدأ هنا: الحيازة تنتقل لطبقة مشرفي المسارات (1.2)
            'edit_holder' => BuildAccess::LAYER_TRACK,
        ])->save();

        return ['linked' => $trackIds, 'notified' => $this->notifyTrackSupervisors($goal, $fresh ?: $trackIds)];
    }

    /** مشرفو المسارات المعنيّون وحدهم — لا كلّ مشرفي المسارات */
    private function notifyTrackSupervisors(Goal $goal, array $trackIds): int
    {
        if ($trackIds === []) {
            return 0;
        }

        $positionId = Position::query()->where('key', 'track_supervisor')->value('id');

        $userIds = Membership::query()
            ->where('memberships.status', 'active')
            ->when($positionId, fn ($q) => $q->where('memberships.position_id', $positionId))
            ->join('entities', 'entities.id', '=', 'memberships.entity_id')
            ->whereIn('entities.track_id', $trackIds)
            ->pluck('memberships.user_id')
            ->unique();

        $count = 0;

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            Integrations::notify(
                user: $user,
                category: 'goal',
                title: strtr(setting('goals.goal_build_service.notify_track_supervisors_1', 'هدف جديد على مسارك: :p1'), [':p1' => (string) ($goal->name)]),
                body: setting('goals.goal_build_service.notify_track_supervisors_2', 'فكّكه مَعالِمَ وحزمًا واربط كلّ حزمة بكيان من مسارك.'),
                url: route('volunteer.goals.build.breakdown', $goal),
                requiresAction: true,
            );

            $count++;
        }

        return $count;
    }

    // ------------------------------------------------------------ 1.2 التفكيك

    public function createMilestone(Goal $goal, array $data, User $actor): Milestone
    {
        $milestone = Milestone::create([
            'goal_id' => $goal->id,
            'name' => $data['name'],
            'verification_criteria' => $data['verification_criteria'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'sort_order' => (int) Milestone::query()->where('goal_id', $goal->id)->max('sort_order') + 1,
            'created_by' => $actor->id,
        ]);

        $goal->forceFill(['build_stage' => 'breakdown'])->save();

        return $milestone;
    }

    /** الاسم الافتراضيّ «Work Package N» — قالبه إعداد لا نصّ محروق (2.13) */
    public function defaultPackageName(int $index): string
    {
        $template = (string) setting('goals.build.package_default_name', 'Work Package :n');

        return str_replace(':n', (string) $index, $template);
    }

    /**
     * ربط حزمة بكيان — **بالكيان نفسه لا بشخص الدايركتور**، ومن مسار المشرف وحده.
     *
     * @param  list<int>  $entityIds  كيانٌ واحد، أو كلّ كيانات المسار في «الربط الجماعيّ»
     * @return Collection<int,WorkPackage>
     */
    public function attachPackages(Milestone $milestone, array $entityIds, User $actor, ?string $name = null): Collection
    {
        $entities = Entity::query()->whereIn('id', $entityIds)->with('track')->get();

        if ($entities->isEmpty()) {
            throw ValidationException::withMessages([
                'entities' => (string) setting('goals.build.error.entities_required', 'اختار كيانًا واحدًا على الأقلّ من كيانات مسارك.'),
            ]);
        }

        /*
         * ⛔ الحصر الصارم: كيانٌ خارج مسار المشرف يُرفَض على الخادم.
         *
         * والاستثناء الوحيد **مسودّة ملفٍّ من مسودّات هذا الهدف** (23 — 1.2):
         * `canLinkEntity` تشترط `status = active` بحقّ — فالكيان المؤرشف خارج
         * الاختيار — لكنّ المسودّة ليست مؤرشفةً ولا مفتوحة، بل موجودةٌ للبناء
         * وحده. وحصرُها بـ`draft_goal_id` يمنع أن تُربَط مسودّةُ هدفٍ بحزمة
         * هدفٍ آخر، فتُفعَّل لاحقًا بإطلاقٍ لم يكن لها.
         */
        $goal = Goal::query()->find($milestone->goal_id);

        foreach ($entities as $entity) {
            $isOwnDraft = $goal !== null && $this->fileDrafts->isDraftOf($entity, $goal);

            if (! $isOwnDraft && ! $this->scope->canLinkEntity($actor, $entity)) {
                throw ValidationException::withMessages([
                    'entities' => (string) setting(
                        'goals.build.error.entity_out_of_track',
                        'الكيان ده مش من كيانات مسارك — مينفعش تربط عليه حزمة.',
                    ),
                ]);
            }
        }

        $created = collect();
        $next = (int) WorkPackage::query()->where('milestone_id', $milestone->id)->max('sort_order');

        foreach ($entities as $entity) {
            $exists = WorkPackage::query()
                ->where('milestone_id', $milestone->id)
                ->where('entity_id', $entity->id)
                ->first();

            if ($exists) {
                $created->push($exists);

                continue;
            }

            $next++;

            $created->push(WorkPackage::create([
                'milestone_id' => $milestone->id,
                'entity_id' => $entity->id,
                'name' => $name !== null && trim($name) !== ''
                    ? trim($name).($entities->count() > 1 ? ' — '.$entity->name_ar : '')
                    : $this->defaultPackageName($next),
                'build_status' => 'filling',
                'sort_order' => $next,
            ]));
        }

        $milestone->goal?->forceFill(['build_stage' => 'filling'])->save();

        $this->notifyDirectors($milestone, $created);

        return $created;
    }

    /** إشعار دايركتور كلّ كيان مربوط — «يصله إشعار ⟵ يفتح فيرى حزمه» (1.3) */
    private function notifyDirectors(Milestone $milestone, Collection $packages): void
    {
        $entityIds = $packages->pluck('entity_id')->filter()->unique()->all();

        if ($entityIds === []) {
            return;
        }

        $userIds = Membership::query()
            ->where('memberships.status', 'active')
            ->whereIn('memberships.entity_id', $entityIds)
            ->join('positions', 'positions.id', '=', 'memberships.position_id')
            ->where('positions.rank', '>=', $this->access->directorMinRank())
            ->pluck('memberships.user_id')
            ->unique();

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            Integrations::notify(
                user: $user,
                category: 'goal',
                title: strtr(setting('goals.goal_build_service.notify_directors_1', 'حزمة عمل في انتظار مهامّك: :p1'), [':p1' => (string) ($milestone->name)]),
                body: setting('goals.goal_build_service.notify_directors_2', 'املا حزم كيانك بمهامّك ثمّ ارفعها للمراجعة.'),
                url: route('volunteer.goals.build.fill', $milestone->goal_id),
                requiresAction: true,
            );
        }
    }

    // ------------------------------------------------------------ 1.3 ملء الحزم

    /**
     * مهمّة يضيفها الدايركتور «لنفسه» داخل حزمة كيانه — **بلا حدّ أقصى**.
     *
     * ولماذا يُخلَق بندٌ مع كلّ مهمّة؟ لأنّ القاموس يوجب: «كلّ مهمّة جديدة تُربَط
     * ببند إجباريًّا». فالبند وعاء المهمّة ووعاء VXP لها، ويُنشأ باسمها فلا يطلب
     * من الدايركتور إدخالًا زائدًا لا معنى له في هذه الشاشة (2.15).
     */
    public function addDirectorTask(WorkPackage $package, array $data, User $owner): Task
    {
        return DB::transaction(function () use ($package, $data, $owner) {
            $item = WorkItem::create([
                'work_package_id' => $package->id,
                'name' => $data['title'],
                'brief' => $data['brief'] ?? null,
                'deliverable_spec' => $data['deliverable_spec'] ?? null,
                'vxp_pool' => 0,
            ]);

            return Task::create([
                'title' => $data['title'],
                'work_item_id' => $item->id,
                'entity_id' => $package->entity_id,
                'owner_id' => $owner->id,
                'created_by' => $owner->id,
                'brief' => $data['brief'] ?? null,
                'deliverable_spec' => $data['deliverable_spec'] ?? null,
                'deadline_at' => $data['deadline_at'] ?? null,
                'vxp_value' => 0,
                'status' => TaskStatus::IN_PROGRESS,
                'source' => 'assigned',
            ]);
        });
    }

    /**
     * مهمّة يضيفها **مشرف المسار** (أو القمّة) في طريق الرجوع: «إضافة مهامّ جديدة
     * مربوطة بالكيانات» (23 — 1.4) — ومالكها **دايركتور الكيان المربوط** لا مَن
     * كتبها، فالمهمّة تنزل في مكانها الصحيح لحظة «إرسال للتنفيذ».
     */
    public function addTaskForEntity(WorkPackage $package, array $data, User $actor): Task
    {
        /*
         | الأدنى رتبةً ممّن يبلغون رتبة الدايركتور هو **دايركتور الكيان** نفسه:
         | فمشرف المسار والقمّة يظهران في عضويّات الكيان أحيانًا برتبٍ أعلى، ولو
         | أخذنا أوّل صفٍّ بلا ترتيب لأسندنا المهمّة للقمّة بدل صاحب الكيان.
         */
        $ownerId = Membership::query()
            ->where('memberships.entity_id', $package->entity_id)
            ->where('memberships.status', 'active')
            ->join('positions', 'positions.id', '=', 'memberships.position_id')
            ->where('positions.rank', '>=', $this->access->directorMinRank())
            ->orderBy('positions.rank')
            ->value('memberships.user_id');

        $owner = $ownerId ? User::query()->find($ownerId) : null;

        return $this->addDirectorTask($package, $data, $owner ?? $actor);
    }

    /** «رفع للمراجعة» — الحزمة تغادر يد الدايركتور إلى مشرف مساره */
    public function submitPackage(WorkPackage $package, User $actor): void
    {
        $package->forceFill([
            'build_status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $actor->id,
            'submitted_snapshot' => json_encode($this->snapshot($package), JSON_UNESCAPED_UNICODE),
        ])->save();

        $goal = $package->milestone?->goal;

        if ($goal && $this->allPackagesSubmitted($goal)) {
            $goal->forceFill(['build_stage' => 'aggregation'])->save();
        }
    }

    public function allPackagesSubmitted(Goal $goal): bool
    {
        $packages = WorkPackage::query()
            ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
            ->get();

        return $packages->isNotEmpty() && $packages->every(fn (WorkPackage $p) => $p->build_status === 'submitted');
    }

    /** لقطة الحزمة كما رفعها الدايركتور — أساس «فرق النسخة» في 1.6 */
    public function snapshot(WorkPackage $package): array
    {
        $tasks = Task::query()
            ->whereIn('work_item_id', WorkItem::query()->where('work_package_id', $package->id)->select('id'))
            ->orderBy('id')
            ->get(['title', 'vxp_value']);

        return [
            (string) setting('goals.goal_build_service.snapshot_1', 'الاسم') => $package->name,
            (string) setting('goals.goal_build_service.snapshot_2', 'المهامّ') => $tasks->map(fn (Task $t) => $t->title.' ('.rtrim(rtrim(number_format((float) $t->vxp_value, 2), '0'), '.').')')->all(),
        ];
    }

    // ------------------------------------------------- 1.4 التعديل المباشر والتسعير

    /**
     * حفظ إنبوت واحد — **في صفّه الحقيقيّ**، ومعه صفّ في سجلّ «تمّ التعديل».
     *
     * @return array{value: string, label: string, edits: int}
     */
    public function saveField(Goal $goal, string $subjectType, int $subjectId, string $field, mixed $value, User $actor): array
    {
        $label = self::editableFields()[$subjectType][$field] ?? null;

        abort_if($label === null, 422, setting('goals.goal_build_service.save_field_1', 'حقل غير قابل للتعديل.'));

        $subject = $this->resolveSubject($goal, $subjectType, $subjectId);

        abort_if($subject === null, 404, setting('goals.goal_build_service.save_field_2', 'العنصر ده مش تابع للهدف ده.'));

        $value = $this->castValue($field, $value);
        $old = (string) ($subject->getAttribute($field) ?? '');

        if ((string) $value !== $old) {
            $subject->forceFill([$field => $value])->save();

            DB::table('goal_field_revisions')->insert([
                'goal_id' => $goal->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'field' => $field,
                'label' => $label,
                'old_value' => $old,
                'new_value' => (string) $value,
                'user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'value' => (string) $value,
            'label' => $label,
            'edits' => $this->revisionCount($subjectType, $subjectId, $field),
        ];
    }

    private function castValue(string $field, mixed $value): string|float
    {
        if ($field === 'vxp_value') {
            $number = is_numeric($value) ? (float) $value : 0.0;

            abort_if($number < 0, 422, setting('goals.goal_build_service.cast_value_1', 'قيمة النقاط لا تكون بالسالب.'));

            return $number;
        }

        $text = trim((string) $value);

        abort_if($text === '' && in_array($field, ['name', 'title'], true), 422, setting('goals.goal_build_service.cast_value_2', 'الاسم مايفضاش.'));

        return mb_substr($text, 0, (int) setting('goals.build.max_field_chars', 2000));
    }

    private function resolveSubject(Goal $goal, string $type, int $id): ?Model
    {
        return match ($type) {
            'goal' => (int) $goal->id === $id ? $goal : null,
            'milestone' => Milestone::query()->where('goal_id', $goal->id)->find($id),
            'package' => WorkPackage::query()
                ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
                ->find($id),
            'task' => Task::query()
                ->whereIn('work_item_id', WorkItem::query()->whereIn(
                    'work_package_id',
                    WorkPackage::query()->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))->select('id'),
                )->select('id'))
                ->find($id),
            default => null,
        };
    }

    public function revisionCount(string $type, int $id, string $field): int
    {
        return (int) DB::table('goal_field_revisions')
            ->where('subject_type', $type)->where('subject_id', $id)->where('field', $field)
            ->count();
    }

    /**
     * كلّ تعديلات **هذا الحقل بعينه**: مَن · متى · ماذا كان (بوب-أب «تمّ التعديل»).
     *
     * @return list<array{by: string, at: string, was: string, now: string}>
     */
    public function revisions(string $type, int $id, string $field): array
    {
        return DB::table('goal_field_revisions')
            ->leftJoin('users', 'users.id', '=', 'goal_field_revisions.user_id')
            ->where('subject_type', $type)->where('subject_id', $id)->where('field', $field)
            ->orderByDesc('goal_field_revisions.id')
            ->limit((int) setting('goals.build.revisions_rows', 20))
            ->get(['users.name as by', 'goal_field_revisions.created_at as at', 'old_value', 'new_value'])
            ->map(fn ($row) => [
                'by' => $row->by ?: setting('goals.goal_build_service.revisions_1', 'غير معروف'),
                'at' => (string) $row->at,
                'was' => (string) ($row->old_value ?? ''),
                'now' => (string) ($row->new_value ?? ''),
            ])
            ->all();
    }

    /** الحقول التي عليها تعديل — لتظهر تحتها كلمة «تمّ التعديل» */
    public function editedFields(Goal $goal): array
    {
        return DB::table('goal_field_revisions')
            ->where('goal_id', $goal->id)
            ->selectRaw('subject_type, subject_id, field, count(*) as total')
            ->groupBy('subject_type', 'subject_id', 'field')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->subject_type.':'.$row->subject_id.':'.$row->field => (int) $row->total])
            ->all();
    }

    /**
     * «رفع معاينة للهدف» ⟵ **القفل الطبقيّ**: الحيازة تنتقل للقمّة، ورافعها
     * يصير قارئًا فقط — ولا يُحسَم ذلك بإخفاء زرّ بل بالعمود نفسه.
     */
    public function raisePreview(Goal $goal, User $actor): void
    {
        $goal->forceFill([
            'build_stage' => 'preview',
            'edit_holder' => BuildAccess::LAYER_TOP,
            'preview_raised_at' => now(),
            'preview_raised_by' => $actor->id,
        ])->save();

        if ($goal->created_by && ($top = User::query()->find($goal->created_by))) {
            Integrations::notify(
                user: $top,
                category: 'goal',
                title: strtr(setting('goals.goal_build_service.raise_preview_1', 'معاينة جاهزة: :p1'), [':p1' => (string) ($goal->name)]),
                body: setting('goals.goal_build_service.raise_preview_2', 'مشرف المسار رفع الهدف للمعاينة النهائيّة.'),
                url: route('volunteer.goals.launch'),
                requiresAction: true,
            );
        }
    }
}
