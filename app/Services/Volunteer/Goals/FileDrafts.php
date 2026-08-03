<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **مسودّات الملفّات** (الدستور 23 — 1.2 سيناريو مشرف عام الملفّات · 1.6).
 *
 * النصّ حرفيًّا: «إن وُجد ملفٌّ مناسب شغّال ربط به الحزمة مباشرةً؛ وإن لم يوجد
 * **أنشأ أثناء البناء «مسودّات ملفات» جديدة وربطها بالحزم** — **ولا تتفعّل
 * رسميًّا (عضويّات ودعوات) إلّا لحظة ضغط مشرف عام التطوّع «إرسال للتنفيذ»** —
 * فيظلّ الفتح حصريًّا للقمّة **بصفر خطوة إضافيّة**، ويظلّ الإنهاء له وحده».
 *
 * وهذه الجملة تحمل ثلاثة قيود لا واحدًا، وكلٌّ منها مفروضٌ هنا على الخادم:
 *
 * 1) **«لا تتفعّل رسميًّا»** — المسودّة كيانٌ بحالة `draft`: خارج كلّ قوائم
 *    الكيانات والهيكل والسعة، وعضويّاتها صفوفٌ بحالة `invited` **بلا أثر**
 *    (فكلّ استعلامات العضويّة في المنصّة مشروطة بـ`active`). فهي موجودةٌ
 *    للبناء، معدومةٌ للتشغيل.
 *
 * 2) **«الفتح حصريًّا للقمّة بصفر خطوة إضافيّة»** — فلا مسار «افتح الملفّ»
 *    أصلًا في هذا الملفّ ولا في مساراته. التفعيل **دالّةٌ واحدة** لا يستدعيها
 *    إلّا `GoalLaunchService::launch()`، أي ضغطة «إرسال للتنفيذ» نفسها. ولو
 *    وُجد بابٌ ثانٍ للفتح لصار «حصريًّا» كلامًا: مشرف الملفّات يفتح ما يشاء
 *    ثمّ يُخبِر القمّة.
 *
 * 3) **«تتفعّل مسودّات الملفّات المربوطة»** — مسودّات **هذا الهدف** وحده،
 *    ولذلك `draft_goal_id`. وبلا هذا القيد يفتح إطلاقُ هدفٍ ملفّاتِ هدفٍ آخر
 *    ما زال قيد البناء — فتحٌ لم تضغطه القمّة.
 */
class FileDrafts
{
    public function __construct(private readonly EntityScope $scope) {}

    /** مفتاح مسار الملفّات — إعداد لا نصٌّ محروق (2.13) */
    public function trackKey(): string
    {
        return (string) setting('goals.build.file_track_key', 'case_file');
    }

    public function track(): ?Track
    {
        return Track::query()->where('key', $this->trackKey())->first();
    }

    /**
     * مَن يُنشئ مسودّة ملفّ؟ **مشرف عام مسار الملفّات وحده** — والقياس مزدوج:
     * صلاحيّة `work_packages.create` بنطاق TRACK أو ALL، **و**أن يكون مسار
     * الملفّات ضمن مساراته فعلًا.
     *
     * فمشرف عام الأقسام لا يفتح ملفًّا، وصاحب النطاق ALL (القمّة) يفتحه لأنّ
     * الملفّ كيانه أصلًا: «كيانٌ مؤقّت **يفتحه مشرف عام التطوّع** بالدعوة».
     */
    public function canCreate(User $user): bool
    {
        $track = $this->track();

        if (! $track) {
            return false;
        }

        $trackIds = $this->scope->trackIds($user);

        // null = بلا قيدٍ على المسارات (نطاق ALL — القمّة)
        return $trackIds === null || in_array((int) $track->id, $trackIds, true);
    }

    /**
     * إنشاء مسودّة ملفٍّ داخل بناء هدفٍ بعينه، ومعها **دعواتٌ مكتوبة لا مُرسَلة**.
     *
     * @param  array<int, array{user_id: int|string, position_id: int|string}>  $invitations
     */
    public function create(User $actor, Goal $goal, string $name, array $invitations = []): Entity
    {
        abort_unless($this->canCreate($actor), 403, (string) setting(
            'goals.build.error.file_draft_forbidden',
            'فتح الملفّات لمشرف عام مسار الملفّات — مش من صلاحيّتك.',
        ));

        $track = $this->track();

        abort_unless($track !== null, 422, (string) setting(
            'goals.build.error.file_track_missing',
            'مسار الملفّات مش معرَّف في المنصّة — اضبطه من الإعدادات الأوّل.',
        ));

        /*
         * ⛔ `upline_id` يشير إلى **عضويّة** لا إلى مستخدم — والفرق ليس شكليًّا:
         * سلسلة الإشراف كلّها مبنيّة على العضويّة لأنّ الشخص الواحد قد يكون في
         * كيانين ببوزشنين مختلفين، فأبلاينه يختلف باختلاف عضويّته لا باسمه.
         * ووضع `$actor->id` هنا كان يكسر المفتاح الأجنبيّ في قاعدةٍ حقيقيّة،
         * ويمرّ صامتًا في أيّ قاعدةٍ لا تفرضه ليبني سلسلةَ إشرافٍ تشير إلى صفٍّ
         * لا علاقة له بالفاعل.
         */
        $uplineId = Membership::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->value('id');

        return DB::transaction(function () use ($goal, $name, $invitations, $track, $uplineId) {
            $entity = Entity::create([
                'track_id' => $track->id,
                'parent_id' => null,
                'draft_goal_id' => $goal->id,
                'name_ar' => $name,
                // ⛔ لا `opened_at` هنا: الملفّ لم يُفتَح بعد، وختمُ الفتح ضغطةُ القمّة
                'status' => 'draft',
            ]);

            foreach ($invitations as $invitation) {
                $userId = (int) ($invitation['user_id'] ?? 0);
                $positionId = (int) ($invitation['position_id'] ?? 0);

                if ($userId === 0 || $positionId === 0) {
                    continue;
                }

                Membership::create([
                    'user_id' => $userId,
                    'entity_id' => $entity->id,
                    'position_id' => $positionId,
                    'upline_id' => $uplineId,
                    'is_primary' => false,
                    // الصفّ مكتوبٌ ولا يعمل: كلّ استعلامات المنصّة تشترط `active`
                    'status' => 'invited',
                    'invited_at' => now(),
                    'started_at' => null,
                ]);
            }

            return $entity;
        });
    }

    /**
     * مسودّات هذا الهدف — تُعرَض في شاشة التفكيك لتُربَط بها الحزم.
     *
     * @return Collection<int, Entity>
     */
    public function draftsFor(Goal $goal): Collection
    {
        return Entity::query()
            ->where('draft_goal_id', $goal->id)
            ->where('status', 'draft')
            ->with('track')
            ->orderBy('name_ar')
            ->get();
    }

    /** هل هذا الكيان مسودّةَ ملفٍّ لهذا الهدف؟ — شرط الربط الاستثنائيّ في 1.2 */
    public function isDraftOf(Entity $entity, Goal $goal): bool
    {
        return $entity->status === 'draft' && (int) $entity->draft_goal_id === (int) $goal->id;
    }

    /**
     * ⭐ **التفعيل** — لا يُستدعى إلّا من ضغطة «إرسال للتنفيذ» (1.6).
     *
     * ولا يمسّ إلّا مسودّات هذا الهدف: `draft_goal_id` هو الرابط، فلا يفتح
     * إطلاقُ هدفٍ ملفّاتِ هدفٍ آخر.
     *
     * @return array{files: int, memberships: int}
     */
    public function activate(Goal $goal): array
    {
        $drafts = $this->draftsFor($goal);

        if ($drafts->isEmpty()) {
            return ['files' => 0, 'memberships' => 0];
        }

        $now = now();
        $ids = $drafts->pluck('id')->all();

        $activated = DB::transaction(function () use ($ids, $now) {
            Entity::query()->whereIn('id', $ids)->update([
                'status' => 'active',
                'opened_at' => $now,
                'draft_goal_id' => null,
                'updated_at' => $now,
            ]);

            return Membership::query()
                ->whereIn('entity_id', $ids)
                ->where('status', 'invited')
                ->update([
                    'status' => 'active',
                    'started_at' => $now,
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]);
        });

        $this->notifyInvitees($goal, $ids);

        return ['files' => count($ids), 'memberships' => $activated];
    }

    /**
     * الدعوة تصل **لحظة التفعيل** لا لحظة الكتابة — فلا يُدعى أحدٌ إلى ملفٍّ
     * قد لا يُفتَح أصلًا لو لم تضغط القمّة «إرسال للتنفيذ».
     *
     * @param  list<int>  $entityIds
     */
    private function notifyInvitees(Goal $goal, array $entityIds): void
    {
        $rows = Membership::query()
            ->whereIn('memberships.entity_id', $entityIds)
            ->where('memberships.status', 'active')
            ->join('entities', 'entities.id', '=', 'memberships.entity_id')
            ->join('positions', 'positions.id', '=', 'memberships.position_id')
            ->select('memberships.user_id', 'entities.name_ar as entity_name', 'positions.name_ar as position_name')
            ->get();

        foreach ($rows as $row) {
            $user = User::query()->find($row->user_id);

            if (! $user) {
                continue;
            }

            Notifier::send(
                $user,
                (string) setting('goals.build.file_draft.notify_category', 'volunteer'),
                (string) setting('goals.build.file_draft.notify_title', 'اتضمّيت لملفّ جديد'),
                str_replace(
                    ['{file}', '{position}', '{goal}'],
                    [$row->entity_name, $row->position_name, $goal->name],
                    (string) setting(
                        'goals.build.file_draft.notify_body',
                        'اتفتح ملفّ «{file}» وإنت فيه {position} — ضمن هدف «{goal}».',
                    ),
                ),
                route('volunteer.department'),
            );
        }
    }

    /** بوزشنات الدعوة المعروضة في الفورم — كلّها ما عدا الفخريّ والمعطَّل */
    public function invitablePositions(): Collection
    {
        return Position::query()
            ->where('is_active', true)
            ->where('is_honorary', false)
            ->orderBy('rank')
            ->get();
    }
}
