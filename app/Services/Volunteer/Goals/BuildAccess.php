<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ مَن يرى رحلة بناء الهدف، ومَن **يملك التحرير** فيها (الدستور 23 — القسم 1).
 *
 * قاعدتان لا ثالثة لهما، وكلتاهما تُفرَض هنا على الخادم لا في الواجهة:
 *
 * **1) الرحلة كلّها غير مرئيّة للداونلاينز:** «البناء يجري بين ثلاث طبقات فقط:
 * مشرف عام التطوّع ⟵ مشرفو المسارات ⟵ الدايركتورات»، و«حتى هذه اللحظة لا يرى
 * أحد من الداونلاينز شيئًا». فإخفاء زرٍّ لا يكفي: كوردنيتور يعرف الرابط يفتحه.
 * ولذلك الطبقة تُقاس ببوزشن العضويّة (رتبة الدايركتور فما فوق) **مع** الصلاحيّة،
 * لا بالصلاحيّة وحدها — فمنحةٌ واسعة بالخطأ لا تفتح الرحلة لمَن هو تحتها.
 *
 * **2) القفل الطبقيّ:** «حيازة التحرير أثناء بناء الهدف لطبقة واحدة (مشرف المسار
 * ⟵ ثمّ القمّة بعد رفع معاينة) — ومَن سلَّم صار قارئًا فقط». فالحيازة عمودٌ على
 * الهدف (`edit_holder`) يُقارَن بطبقة الفاعل، ويُرفَض غير الحائز **بـ403** مهما
 * كانت صلاحيّته — وإلّا صار «القفل» إخفاءَ زرٍّ يلتفّ عليه أوّل POST مباشر.
 */
class BuildAccess
{
    public const LAYER_TOP = 'top';

    public const LAYER_TRACK = 'track';

    public const LAYER_ENTITY = 'entity';

    public function __construct(private readonly EntityScope $scope) {}

    /** رتبة البوزشن التي تبدأ منها طبقة الدايركتور — إعداد لا رقم محروق (2.13) */
    public function directorMinRank(): int
    {
        return (int) setting('goals.build.director_min_rank', 4);
    }

    /**
     * طبقة الفاعل في الرحلة — null لمن هو خارج الطبقات الثلاث.
     *
     * والقياس مزدوج: **صلاحيّة** بنطاقها، و**بوزشن** بعضويّة نشطة — فلا يكفي
     * أحدهما وحده. (والقمّة قد لا تكون لها عضويّة بكيان بعينه، فنطاق ALL يكفيها.)
     */
    public function layerOf(User $user): ?string
    {
        if ($user->widestScope('goals.approve') === 'ALL' || $user->widestScope('milestones.edit') === 'ALL') {
            return self::LAYER_TOP;
        }

        if ($user->widestScope('milestones.create') === 'TRACK' || $user->widestScope('work_packages.create') === 'TRACK') {
            return self::LAYER_TRACK;
        }

        if ($this->directorEntityIds($user) !== []) {
            return self::LAYER_ENTITY;
        }

        return null;
    }

    /**
     * كيانات يقودها المستخدم بوزشنًا (دايركتور فما فوق) — وهي مربط الحصر في 1.3:
     * «يفتح فيرى الهدف والمَعلَم و**حزمه هو** فقط».
     *
     * @return list<int>
     */
    public function directorEntityIds(User $user): array
    {
        return Membership::query()
            ->where('memberships.user_id', $user->id)
            ->where('memberships.status', 'active')
            ->join('positions', 'positions.id', '=', 'memberships.position_id')
            ->where('positions.rank', '>=', $this->directorMinRank())
            ->pluck('memberships.entity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isDirectorOf(User $user, int $entityId): bool
    {
        return in_array($entityId, $this->directorEntityIds($user), true);
    }

    /** مسارات الهدف — «يظهر فقط لحظة ربطه بمسار أو أكثر» (23 — 1.1) */
    public function goalTrackIds(Goal $goal): array
    {
        return DB::table('goal_tracks')
            ->where('goal_id', $goal->id)
            ->pluck('track_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isLinked(Goal $goal): bool
    {
        return $this->goalTrackIds($goal) !== [];
    }

    /** الهدف ما زال في مرحلة البناء — بعد «إرسال للتنفيذ» تحكمه شاشات التنفيذ */
    public function isBuilding(Goal $goal): bool
    {
        return $goal->sent_to_execution_at === null;
    }

    /**
     * هل يرى هذا المستخدم هذا الهدف **وهو في البناء**؟
     *
     *  - القمّة: تراه دائمًا (هي منشئته).
     *  - مشرف المسار: بعد الربط، وفي **مساره هو** فقط.
     *  - الدايركتور: بعد أن تُربَط حزمةٌ بكيانٍ يقوده.
     *  - أيّ أحدٍ غيرهم: **لا** — وهذا هو حارس «لا يرى الداونلاينز شيئًا».
     */
    public function canSeeBuild(User $user, Goal $goal): bool
    {
        $layer = $this->layerOf($user);

        if ($layer === self::LAYER_TOP) {
            return true;
        }

        if ($layer === self::LAYER_TRACK) {
            $mine = $this->scope->trackIds($user, 'milestones.create');

            if ($mine === null) {
                return true;
            }

            return array_intersect($this->goalTrackIds($goal), $mine) !== [];
        }

        if ($layer === self::LAYER_ENTITY) {
            return $this->packagesFor($user, $goal)->isNotEmpty();
        }

        return false;
    }

    /**
     * حزم هذا الهدف داخل كيانات يقودها المستخدم — وهي كلّ ما يراه في 1.3.
     *
     * @return Collection<int,WorkPackage>
     */
    public function packagesFor(User $user, Goal $goal)
    {
        $entityIds = $this->directorEntityIds($user);

        if ($entityIds === []) {
            return collect();
        }

        return WorkPackage::query()
            ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
            ->whereIn('entity_id', $entityIds)
            ->with('entity', 'milestone')
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** الطبقة الحائزة للتحرير الآن — والافتراضيّ القمّة، فالهدف وليدها (1.1) */
    public function holderOf(Goal $goal): string
    {
        $holder = (string) ($goal->getAttribute('edit_holder') ?? '');

        return $holder !== '' ? $holder : self::LAYER_TOP;
    }

    public function holds(User $user, Goal $goal): bool
    {
        return $this->isBuilding($goal) && $this->layerOf($user) === $this->holderOf($goal);
    }

    /** رسالة الرفض من الإعدادات — فالنصّ يُضبَط من اللوحة لا من الكود (2.13) */
    public function lockMessage(Goal $goal): string
    {
        return (string) setting(
            'goals.build.error.locked',
            'الهدف اترفع معاينة — التحرير بقى عند الطبقة الأعلى وإنت قارئ بس.',
        );
    }

    /** ⛔ الحارس نفسه: غير الحائز يُرفَض على الخادم لا بإخفاء زرّ */
    public function assertHolds(User $user, Goal $goal): void
    {
        abort_unless($this->holds($user, $goal), 403, $this->lockMessage($goal));
    }

    /** أيقونة الكيان: أوّل حرف من كلّ كلمة في اسم القسم، أو اسم المحافظة (23 — 1.3) */
    public function entityIcon(?Entity $entity): string
    {
        if (! $entity) {
            return '—';
        }

        $trackKey = $entity->track?->key ?? Entity::query()->find($entity->id)?->track?->key;

        if ($trackKey === 'governorate') {
            return (string) $entity->name_ar;
        }

        $letters = collect(preg_split('/\s+/u', trim((string) $entity->name_ar)) ?: [])
            ->filter()
            ->map(fn (string $word) => mb_substr($word, 0, 1))
            ->all();

        return $letters === [] ? (string) $entity->name_ar : implode('', $letters);
    }
}
