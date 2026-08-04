<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * السعة والأحمال (13.4-ف · 24.4-7).
 *
 * > **قاعدة حاكمة:** السعة **غير مانعة إطلاقًا** — مؤشّرات وتنبيهات فقط،
 * > لا تُوقِف تسكينًا ولا ترقيةً ولا نقلًا. وهذه الخدمة **تقرأ ولا تكتب**،
 * > ولا تُصدِر قرارًا ولا تمنع فعلًا في أيّ موضع.
 *
 * وللتفريق: **السعة = عدد أشخاص** · **سقف الانشغال (23-3.1) = عدد مهامّ**.
 */
final class CapacityReport
{
    public function __construct(private readonly DepartmentScope $scope) {}

    /** نصّ البانر الثابت — من الإعدادات لا محروقًا (2.13) */
    public function banner(): string
    {
        return (string) setting(
            'volunteer.capacity.banner',
            'السعة غير مانعة — لا توقف تسكينًا ولا ترقيةً ولا نقلًا',
        );
    }

    public function differenceLine(): string
    {
        return (string) setting(
            'volunteer.capacity.difference_line',
            'السعة = عدد أشخاص · سقف الانشغال = عدد مهامّ',
        );
    }

    /**
     * جدول نطاق الإشراف: البوزشن · الأدنى · الافتراضيّ · الأقصى · الفعليّ لكلّ مسؤول.
     * القيم من جدول `positions` — ولا شاشة ضبط هنا، الأرقام **تُقرأ فقط**.
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function spanTable(Collection $memberships): Collection
    {
        $direct = $memberships->groupBy('upline_id')->map->count();

        return Position::query()
            ->where('is_active', true)
            ->where('is_honorary', false)   // «أخوكم» بلا نطاق إشراف ولا داونلاين (13.4-ص)
            ->orderByDesc('rank')
            ->get()
            ->map(function (Position $position) use ($memberships, $direct) {
                $holders = $memberships->where('position_id', $position->id);

                if ($holders->isEmpty()) {
                    return null;
                }

                return [
                    'position' => $position->name_ar,
                    'min' => $position->span_min,
                    'default' => $position->span_default,
                    'max' => $position->span_max,
                    'holders' => $holders->map(function (Membership $m) use ($position, $direct) {
                        $actual = (int) ($direct[$m->id] ?? 0);

                        return [
                            'name' => $m->user?->shortName() ?? '',
                            'entity' => $m->entity?->name_ar ?? '',
                            'actual' => $actual,
                            'state' => $this->spanState($position, $actual),
                            'state_label' => $this->spanLabel($position, $actual),
                        ];
                    })->sortByDesc('actual')->values(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * بارات إشغال الفرعيّات — بنسبة مئويّة بألوان هادئة (13.4-ف-د).
     *
     * سقف أعضاء الكيان **يُحسَب تلقائيًّا** من الهيكل ويظلّ قابلًا للتعديل يدويًّا
     * (`entities.member_cap`) — فلا يُطلَب من الأدمن رقم عشوائيّ (13.4-ف-أ-2).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function occupancy(Entity $root, Collection $memberships): Collection
    {
        $byEntity = $memberships->groupBy('entity_id');
        $entities = $this->scope->subEntities($root)->prepend($root, $root->id);

        return $entities
            ->map(function (Entity $entity) use ($byEntity) {
                /** @var Collection<int, Membership> $rows */
                $rows = $byEntity->get($entity->id, collect());
                $cap = $this->capOf($entity, $rows);
                $percent = $cap > 0 ? (int) round($rows->count() / $cap * 100) : null;

                return [
                    'entity_id' => $entity->id,
                    'entity' => $entity->name_ar,
                    'members' => $rows->count(),
                    'cap' => $cap,
                    'percent' => $percent,
                    'state' => RepBadge::occupancyState($percent),
                ];
            })
            ->values();
    }

    /**
     * «كيانات غير صحّيّة» (13.4-ف-ج): تحت الحدّ الأدنى ⟵ اقتراح **دمج** أو **إلغاء طبقة**
     * — اقتراحٌ لا إلزام ولا منع.
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function unhealthy(Collection $memberships): Collection
    {
        $direct = $memberships->groupBy('upline_id')->map->count();

        return $memberships
            ->filter(fn (Membership $m) => $m->position?->span_min
                && ($direct[$m->id] ?? 0) < $m->position->span_min)
            ->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'position' => $m->position?->name_ar ?? '',
                'entity' => $m->entity?->name_ar ?? '',
                'actual' => (int) ($direct[$m->id] ?? 0),
                'min' => (int) $m->position->span_min,
                'suggestion' => (int) ($direct[$m->id] ?? 0) === 0
                    ? (string) setting('volunteer.capacity.suggestion.drop_layer', 'اقتراح: إلغاء الطبقة')
                    : (string) setting('volunteer.capacity.suggestion.merge', 'اقتراح: دمج مع كيان مجاور'),
            ])
            ->sortBy('actual')
            ->values();
    }

    /**
     * الأحمال: **الأقلّ حملًا أوّلًا** (منطق الموازن) بوسم «مقترَح للتسكين الجديد — اقتراح لا إلزام».
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function loads(Collection $memberships): Collection
    {
        $direct = $memberships->groupBy('upline_id')->map->count();

        $activeTasks = Task::query()
            ->whereIn('owner_id', $memberships->pluck('user_id')->all())
            ->whereIn('status', ['in_progress', 'blocked', 'in_review'])
            ->selectRaw('owner_id, count(*) as c')
            ->groupBy('owner_id')
            ->pluck('c', 'owner_id');

        $rows = $memberships
            ->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'position' => $m->position?->name_ar ?? '',
                'entity' => $m->entity?->name_ar ?? '',
                'team' => (int) ($direct[$m->id] ?? 0),
                'tasks' => (int) ($activeTasks[$m->user_id] ?? 0),
            ])
            ->sortBy([['team', 'asc'], ['tasks', 'asc']])
            ->values();

        $suggestLimit = (int) setting('volunteer.capacity.suggest_count', 3);

        return $rows->map(fn (array $row, int $i) => $row + [
            'suggested' => $i < $suggestLimit,
            'suggestion_note' => (string) setting(
                'volunteer.capacity.load_suggestion',
                'مقترَح للتسكين الجديد — اقتراح لا إلزام',
            ),
        ]);
    }

    /**
     * بوب-أب الكيان: أعضاؤه · نسبة إشغاله · تجاوزاته — وأثر إجراء مقترح **تنبيهًا فقط**.
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function entityDetail(Entity $entity, Collection $memberships): array
    {
        $rows = $memberships->where('entity_id', $entity->id);
        $direct = $memberships->groupBy('upline_id')->map->count();
        $cap = $this->capOf($entity, $rows);
        $percent = $cap > 0 ? (int) round($rows->count() / $cap * 100) : null;

        return [
            'entity' => $entity->name_ar,
            'members' => $rows->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'position' => $m->position?->name_ar ?? '',
                'team' => (int) ($direct[$m->id] ?? 0),
            ])->values(),
            'percent' => $percent,
            'state' => RepBadge::occupancyState($percent),
            'breaches' => $rows
                ->filter(fn (Membership $m) => $m->position?->span_max && ($direct[$m->id] ?? 0) > $m->position->span_max)
                ->map(fn (Membership $m) => [
                    'name' => $m->user?->shortName() ?? '',
                    'actual' => (int) ($direct[$m->id] ?? 0),
                    'max' => (int) $m->position->span_max,
                ])->values(),
            'notice' => (string) setting('volunteer.capacity.breach_notice', 'تنبيه فقط — لا يمنع الإجراء'),
        ];
    }

    /** السقف: اليدويّ إن وُجد، وإلّا مجموع نطاقات الإشراف الافتراضيّة داخل الكيان */
    private function capOf(Entity $entity, Collection $rows): int
    {
        if ($entity->member_cap) {
            return (int) $entity->member_cap;
        }

        $computed = (int) $rows->sum(fn (Membership $m) => (int) ($m->position?->span_default ?? 0));

        return max($computed, (int) setting('volunteer.capacity.min_entity_cap', 5));
    }

    private function spanState(Position $position, int $actual): string
    {
        if ($position->span_max && $actual > $position->span_max) {
            return 'danger';
        }

        if ($position->span_min && $actual < $position->span_min) {
            return 'warn';
        }

        return 'ok';
    }

    private function spanLabel(Position $position, int $actual): string
    {
        return match ($this->spanState($position, $actual)) {
            'danger' => setting('volunteer_org.capacity_report.span_label_1', 'تجاوز'),
            'warn' => setting('volunteer_org.capacity_report.span_label_2', 'غير صحّيّ'),
            default => setting('volunteer_org.capacity_report.span_label_3', 'متوازن'),
        };
    }
}
