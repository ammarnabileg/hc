<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Membership;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Volunteer\Org\AbsenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * **عقد إنشاء المهمّة** — مصدرٌ واحد لقواعده وحرّاسه وحقوله (23-3.1).
 *
 * لماذا خدمة لا كودٌ داخل المتحكّم؟ لأنّ المهمّة صار لها أكثر من بابٍ بشريّ
 * واحد: «مهمّة جديدة» من لوحة المهام، **وتوليد مهمّة «تنفيذ» من بند محضر**
 * (23-0.3). والبابان لو كتب كلٌّ منهما تحقّقه وحده لاختلفا بعد أوّل تعديل —
 * فيمرّ من أحدهما ما يُرَدّ من الآخر: مهمّة بلا بند، أو إسنادٌ لغائبٍ معذور،
 * أو تجاوزٌ لسقف الانشغال. فالعقد هنا واحد، والبابان يمرّان به حرفيًّا.
 *
 * ولا يقرّر هذا الصنف شيئًا جديدًا: هو **نقلٌ حرفيّ** لما كان في
 * `TaskController::store` بترتيبه نفسه — الفريق، ثمّ السقف، ثمّ التحقّق،
 * ثمّ الغياب المعذور، ثمّ الإنشاء.
 */
class TaskCreation
{
    public function __construct(private readonly TaskLoadCap $cap) {}

    /**
     * قواعد التحقّق — والربط ببندٍ **إلزاميّ** لكلّ مهمّة جديدة (23-3.1).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'brief' => ['nullable', 'string'],
            'deliverable_spec' => ['required', 'string'],
            'deadline_at' => ['required', 'date'],
            'vxp_value' => ['nullable', 'numeric', 'min:0'],
            'work_item_id' => ['required', 'integer', 'exists:work_items,id'],
            'blocked_by_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'deliverable_spec' => (string) setting('workflow.tasks.store_msg_2', 'شكل المخرجات'),
            'work_item_id' => (string) setting('workflow.tasks.store_msg_3', 'البند التابع للمشروع'),
        ];
    }

    /**
     * حارسا ما قبل التحقّق: «الإنشاء لمن له فريق» وسقف الانشغال — ويُشرَح
     * كسرهما لحظته لا كتحذير ثابت (2.15-د).
     */
    public function guard(User $creator, ?Membership $membership): void
    {
        if (! $this->hasTeam($creator, $membership)) {
            throw ValidationException::withMessages([
                'title' => (string) setting('workflow.tasks.store_msg', 'إنشاء المهامّ لمن له فريق — تقدر تعمل صب-تاسك على مهمّتك أو تدعو مساهمًا.'),
            ]);
        }

        if ($reason = $this->cap->blockReason($creator, $membership)) {
            throw ValidationException::withMessages(['title' => $reason]);
        }
    }

    /**
     * «لا تُسنَد إليه مهامّ جديدة» طول غيابه المعذور — ويُقترَح بديله (23-6).
     *
     * @param  array<string, mixed>  $data
     */
    public function guardAssignee(array $data, ?Membership $membership): void
    {
        $target = ! empty($data['owner_id']) ? User::query()->find($data['owner_id']) : null;

        if (! $target || ! app(AbsenceService::class)->isAbsent($target, $membership?->entity_id)) {
            return;
        }

        $delegate = app(AbsenceService::class)->delegateFor($target, $membership?->entity_id);

        throw ValidationException::withMessages([
            'owner_id' => strtr((string) setting('workflow.tasks.store_msg_4', ':a1 في وضع «غائب» دلوقتي:a2. اختار حدًّا تاني.'), [
                ':a1' => (string) $target->shortName(),
                ':a2' => $delegate
                    ? strtr((string) setting('workflow.tasks.store_delegate', ' — البديل: :name'), [':name' => (string) $delegate->shortName()])
                    : '',
            ]),
        ]);
    }

    /**
     * الإنشاء نفسه — و`$extra` لا يزيد إلّا **أصل المهمّة** (مثل اجتماع المحضر)
     * فوق الحقول الموحّدة، فلا يفترق بابٌ عن باب في شيءٍ يخصّ دورة الحياة.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    public function create(array $data, User $creator, ?Membership $membership, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title' => $data['title'],
            'task_type_id' => $data['task_type_id'] ?? null,
            'brief' => $data['brief'] ?? null,
            'deliverable_spec' => $data['deliverable_spec'],
            'deadline_at' => Carbon::parse($data['deadline_at']),
            'vxp_value' => $data['vxp_value'] ?? 0,
            'work_item_id' => $data['work_item_id'],
            'blocked_by_task_id' => $data['blocked_by_task_id'] ?? null,
            'entity_id' => $membership?->entity_id,
            'owner_id' => $data['owner_id'] ?? $creator->id,
            'reviewer_id' => $creator->id,
            'created_by' => $creator->id,
            'status' => TaskStatus::IN_PROGRESS,
            'source' => 'assigned',
        ], $extra));
    }

    /** هل تحته أفراد؟ — «الإنشاء لمن له فريق» (23-3.1) */
    public function hasTeam(User $user, ?Membership $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return Membership::query()
            ->where('upline_id', $membership->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * أعضاء فريق مَن ينشئ المهمّة (داونلاينه المباشر) وعدد مهامّه الحاليّة —
     * للسلكت بوكس عند الإسناد (23-3.1): «فريقه في سلكت بوكس وجنب كلّ واحد عدد
     * المهامّ اللي بينفّذها». والحمل والسقف لكلّ عضوٍ يُحسَبان على عضويّته **هو**
     * — دوره وكيانه — لا على عضويّة مَن ينشئ.
     *
     * @return Collection<int, array{user: User, load: int, cap: ?int}>
     */
    public function teamMembersFor(User $user, ?Membership $membership): Collection
    {
        if (! $membership) {
            return collect();
        }

        return Membership::query()
            ->where('upline_id', $membership->id)
            ->where('status', 'active')
            ->with(['user', 'position'])
            ->get()
            ->map(fn (Membership $member) => [
                'user' => $member->user,
                'load' => $this->cap->loadFor($member->user, $member),
                'cap' => $this->cap->capFor($member),
            ]);
    }

    /**
     * بنود الكيان — الربط إلزاميّ لكلّ مهمّة جديدة، وطول قائمة الاختيار
     * إعداد لا رقم محروق (2.13).
     *
     * @return Collection<int, WorkItem>
     */
    public function workItemsFor(?Membership $membership): Collection
    {
        if (! $membership) {
            return collect();
        }

        return WorkItem::query()
            ->latest('id')
            ->limit((int) setting('workflow.work_items.picker_limit', 50))
            ->get();
    }
}
