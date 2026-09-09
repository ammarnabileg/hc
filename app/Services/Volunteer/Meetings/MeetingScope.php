<?php

namespace App\Services\Volunteer\Meetings;

use App\Models\Entity;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\User;
use App\Support\Access\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * نطاق الاجتماع: مَن جمهوره، ومَن يملك إدارته (13.4-ح · 13.4-ن-ب).
 *
 * لماذا خدمة مستقلّة؟ لأنّ سؤال «مَن يرى هذا الاجتماع؟» يتكرّر في القائمة
 * وفي الصفحة وفي تسوية الغياب وفي الإشعارات — ولو تكرّر الجواب اختلف.
 */
class MeetingScope
{
    public function __construct(private readonly ScopeResolver $scopes) {}

    /** كيانات المستخدم النشطة */
    public function entityIds(User $user): array
    {
        return $user->memberships()
            ->where('status', 'active')
            ->pluck('entity_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** كيانات المستخدم مع كلّ ما فوقها في الشجرة — فعضو الفرعيّ يرى اجتماع القسم */
    public function entityIdsWithAncestors(User $user): array
    {
        $ids = $this->entityIds($user);
        $frontier = $ids;

        while ($frontier !== []) {
            $parents = Entity::query()
                ->whereIn('id', $frontier)
                ->pluck('parent_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->diff($ids)
                ->values()
                ->all();

            if ($parents === []) {
                break;
            }

            $ids = array_merge($ids, $parents);
            $frontier = $parents;
        }

        return array_values(array_unique($ids));
    }

    /**
     * الاجتماعات التي يراها المستخدم:
     * «الكلّ» للجميع · اجتماع الكيان لأعضائه وأعضاء فرعيّاته · وما يملكه هو.
     */
    public function visibleQuery(User $user): Builder
    {
        $mine = $this->entityIdsWithAncestors($user);
        $own = $this->entityIds($user);

        return Meeting::query()->where(function (Builder $q) use ($mine, $own, $user) {
            $q->where('audience', 'all')
                ->orWhere('owner_id', $user->id)
                // اجتماع القسم يصل لأعضاء فرعيّاته أيضًا
                ->orWhere(fn (Builder $s) => $s->where('audience', 'entity')->whereIn('entity_id', $mine))
                // اجتماع الفرعيّ لأعضائه وحدهم
                ->orWhere(fn (Builder $s) => $s->where('audience', 'sub_entity')->whereIn('entity_id', $own))
                // ⭐ جمهورٌ اسميّ محدَّد — لا كيان له (لجنة التحقيق: 23-0.2-4-5)
                ->orWhere(fn (Builder $s) => $s->where('audience', 'specific')
                    ->whereHas('invitees', fn (Builder $i) => $i->where('users.id', $user->id)));
        });
    }

    public function isAudience(User $user, Meeting $meeting): bool
    {
        if ($meeting->audience === 'all' || (int) $meeting->owner_id === (int) $user->id) {
            return true;
        }

        if ($meeting->audience === 'specific') {
            return $meeting->invitees()->where('users.id', $user->id)->exists();
        }

        $ids = $meeting->audience === 'sub_entity'
            ? $this->entityIds($user)
            : $this->entityIdsWithAncestors($user);

        return in_array((int) $meeting->entity_id, array_map('intval', $ids), true);
    }

    /** كلّ مَن يشمله الاجتماع — الأساس الذي تُقاس عليه نسبة الحضور وتسوية الغياب */
    public function audienceUserIds(Meeting $meeting): array
    {
        if ($meeting->audience === 'specific') {
            return $meeting->invitees()->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->push((int) $meeting->owner_id)
                ->unique()
                ->values()
                ->all();
        }

        $query = Membership::query()->where('status', 'active');

        if ($meeting->audience !== 'all' && $meeting->entity_id) {
            $ids = [(int) $meeting->entity_id];

            if ($meeting->audience === 'entity') {
                $ids = array_merge($ids, $this->scopes->entityDescendantIds((int) $meeting->entity_id));
            }

            $query->whereIn('entity_id', $ids);
        }

        return $query->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->push((int) $meeting->owner_id)
            ->unique()
            ->values()
            ->all();
    }

    /** سلسلة الأبلاين لأعلى حتى السقف — يقرأها سلّم التصعيد المرئيّ وصلاحيّة الإدارة */
    public function uplineUsers(User $user): Collection
    {
        $membership = $user->memberships()->where('status', 'active')
            ->orderByDesc('is_primary')->first();

        $chain = collect();
        $seen = [];

        while ($membership && $membership->upline_id && ! in_array((int) $membership->upline_id, $seen, true)) {
            $seen[] = (int) $membership->upline_id;
            $membership = Membership::query()->with('user', 'position')->find($membership->upline_id);

            if (! $membership || ! $membership->user) {
                break;
            }

            $chain->push($membership->user);
        }

        return $chain;
    }

    /** المسؤول المباشر — إليه يذهب الاعتراض (13.4-ط) لا إلى مَن أضاف المعاملة */
    public function directManager(User $user): ?User
    {
        return $this->uplineUsers($user)->first();
    }

    /**
     * ⭐ الأسئلة والـOTP والإنهاء والتثبيت: لصاحب الاجتماع أو أيّ أبلاين فوقه
     * حتى السقف (13.4-ن-ب) — أو لمن يملك صلاحيّة إدارة الاجتماعات على هذا الاجتماع.
     */
    public function canManage(User $user, Meeting $meeting): bool
    {
        if ((int) $meeting->owner_id === (int) $user->id) {
            return true;
        }

        $owner = $meeting->owner;

        if ($owner && $this->uplineUsers($owner)->contains(fn (User $u) => (int) $u->id === (int) $user->id)) {
            return true;
        }

        return $user->allows('meetings.manage', $meeting) || $user->allows('meetings.edit', $meeting);
    }

    /** قائمة الحضور كاملةً للمخوَّل — ولغيره تُعرَض حالته هو فقط (24.4) */
    public function canSeeFullAttendance(User $user, Meeting $meeting): bool
    {
        return $this->canManage($user, $meeting)
            || $user->allows('meeting_attendance.list', $meeting)
            || $user->allows('meeting_attendance.view', $meeting);
    }
}
