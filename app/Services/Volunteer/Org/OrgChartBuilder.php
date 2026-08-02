<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\RepScore;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * كانفاس الهيكل التنظيميّ (13.4-م-3 · 24.4-7).
 *
 * الخادم يبني **العقد والروابط** فقط؛ والتخطيط والسحب والتكبير في JS خام
 * — **بلا أيّ مكتبة رسم أو كانفاس خارجيّة** (2.16-ج).
 *
 * ⭐ «أخوكم» عقدة في أعلى الشجرة فوق مشرف عام التطوّع، **بلا أيّ مؤشّر تشغيليّ**
 * (بلا Rep/VXP/حِمل/إشغال) و**لا تُحتسَب في أيّ عدّاد** (13.4-ص).
 */
final class OrgChartBuilder
{
    public const HONORARY_ID = 'honorary';

    public function __construct(private readonly DepartmentScope $scope) {}

    /**
     * @param  Collection<int, Membership>  $memberships
     * @return array{
     *     nodes: list<array<string, mixed>>,
     *     me: string|null,
     *     network_total: int,
     *     entity_members: int,
     *     collapse_threshold: int,
     *     default_depth: int
     * }
     */
    public function build(Entity $root, Collection $memberships, User $viewer): array
    {
        $reps = RepScore::query()
            ->whereIn('user_id', $memberships->pluck('user_id')->all())
            ->pluck('score', 'user_id');

        $absences = MembershipAbsence::query()
            ->whereIn('membership_id', $memberships->keys()->all())
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->with('delegate_membership.user')
            ->get()
            ->keyBy('membership_id');

        $children = $memberships->groupBy('upline_id');
        $mine = $memberships->firstWhere('user_id', $viewer->id);

        $nodes = [];

        foreach ($memberships as $m) {
            $score = isset($reps[$m->user_id]) ? (float) $reps[$m->user_id] : null;
            $load = $children->get($m->id, collect())->count();
            $absence = $absences->get($m->id);
            $parent = $m->upline_id && $memberships->has($m->upline_id) ? (string) $m->upline_id : null;

            $nodes[] = [
                'id' => (string) $m->id,
                'parent' => $parent,
                'name' => $m->user?->shortName() ?? '',
                'code' => $m->user?->code ?? '',
                'initials' => $this->initials($m->user),
                'avatar' => $m->user?->avatar_path ? Storage::url($m->user->avatar_path) : null,
                'position' => $m->position?->name_ar ?? '',
                'entity' => $m->entity?->name_ar ?? '',
                'profile_url' => '/u/'.($m->user?->code ?? ''),
                'rep_label' => RepBadge::label($score),
                'rep_state' => RepBadge::state($score),
                'is_club' => RepBadge::isClubMember($score),
                'load' => $load,
                'occupancy' => $this->occupancy($m, $load),
                'occupancy_state' => RepBadge::occupancyState($this->occupancy($m, $load)),
                'absent_until' => $absence?->to_date?->translatedFormat((string) setting('volunteer.org.date_format', 'j F')),
                'delegate' => $absence?->delegate_membership?->user?->shortName(),
                'acting' => (bool) $m->is_acting,
                'is_me' => $viewer->id === (int) $m->user_id,
                'honorary' => false,
            ];
        }

        $honorary = $this->honoraryNode($nodes);

        if ($honorary) {
            array_unshift($nodes, $honorary);
        }

        return [
            'nodes' => $nodes,
            'me' => $mine ? (string) $mine->id : null,
            // ⭐ العدّاد لا يشمل العنصر الشرفيّ ولا صاحب الكارت نفسه — الشبكة هي مَن تحتك
            'network_total' => $mine ? $this->networkTotal($mine, $children) : 0,
            'entity_members' => $memberships->count(),
            'collapse_threshold' => (int) setting('volunteer.org.collapse_threshold', 30),
            'default_depth' => (int) setting('volunteer.org.default_depth', 2),
            'entity_name' => $root->name_ar,
        ];
    }

    /**
     * شجرة الموبايل: قائمة شجريّة قابلة للطيّ بدل السحب (2.15-ج · 13.4-م).
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function tree(array $nodes): array
    {
        $byParent = [];

        foreach ($nodes as $node) {
            $byParent[$node['parent'] ?? '_root'][] = $node;
        }

        $build = function (?string $parent) use (&$build, $byParent): array {
            $out = [];

            foreach ($byParent[$parent ?? '_root'] ?? [] as $node) {
                $node['children'] = $build($node['id']);
                $out[] = $node;
            }

            return $out;
        };

        return $build(null);
    }

    /**
     * عدّاد شبكتك الكاملة بكلّ المستويات — لا المستوى الأوّل فقط (13.4-م).
     *
     * @param  Collection<int|null, Collection<int, Membership>>  $children
     */
    private function networkTotal(Membership $mine, Collection $children): int
    {
        $total = 0;
        $frontier = [$mine->id];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 32) {
            $next = [];

            foreach ($frontier as $id) {
                foreach ($children->get($id, collect()) as $child) {
                    $total++;
                    $next[] = $child->id;
                }
            }

            $frontier = $next;
        }

        return $total;
    }

    /** نسبة إشغال نطاق الإشراف — مؤشّر لا مانع (13.4-ف) */
    private function occupancy(Membership $m, int $load): ?int
    {
        $max = $m->position?->span_max ?? $m->position?->span_default;

        if (! $max) {
            return null;
        }

        return (int) round($load / $max * 100);
    }

    /**
     * عقدة «أخوكم»: تُوضَع فوق جذر الشجرة، وبلا مؤشّرات، ولا تدخل عدّادًا (13.4-ص).
     *
     * @param  list<array<string, mixed>>  $nodes  تُعدَّل ليصير جذرها تحت العقدة الشرفيّة
     */
    private function honoraryNode(array &$nodes): ?array
    {
        $honorary = $this->scope->honorary();

        if (! $honorary) {
            return null;
        }

        foreach ($nodes as $i => $node) {
            if ($node['parent'] === null) {
                $nodes[$i]['parent'] = self::HONORARY_ID;
            }
        }

        /** @var User $user */
        $user = $honorary['user'];

        return [
            'id' => self::HONORARY_ID,
            'parent' => null,
            'name' => $user->shortName(),
            'code' => '',
            'initials' => $this->initials($user),
            'avatar' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
            'position' => $honorary['label'],
            'entity' => '',
            'profile_url' => null,
            // بلا Rep · بلا VXP · بلا حِمل · بلا إشغال — شرفيّ بحت بلا أثر تشغيليّ
            'rep_label' => null,
            'rep_state' => 'honor',
            'is_club' => false,
            'load' => null,
            'occupancy' => null,
            'occupancy_state' => 'honor',
            'absent_until' => null,
            'delegate' => null,
            'acting' => false,
            'is_me' => false,
            'honorary' => true,
            // شكل الإطار إعدادٌ لمالك المنصّة وحده (13.4-ص-د)
            'frame' => $honorary['frame'] ?? 'soft',
        ];
    }

    private function initials(?User $user): string
    {
        $words = preg_split('/\s+/u', trim((string) ($user?->name ?? ''))) ?: [];

        return collect($words)->take((int) setting('ux.avatar.initials_count', 2))->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }
}
