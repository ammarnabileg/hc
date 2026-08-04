<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Kudos;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\RepScore;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * «الأعضاء والبوزشنز» (24.4-7): كروت مضغوطة لأعضاء القسم كلّه.
 *
 * قاعدة حاكمة: **بلا أرقام أداء تفصيليّة لغير المخوَّل** — مستوى «زميل» يرى
 * الاسم والبوزشن والفرعيّ وشارة Rep فقط (13.4-م: مستويات المشاهدة الأربعة).
 */
final class MemberDirectory
{
    public function __construct(
        private readonly DepartmentScope $scope,
        private readonly ContactVisibility $contacts,
    ) {}

    /**
     * كروت الأعضاء جاهزةً للعرض.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(Collection $memberships, User $viewer): Collection
    {
        $reps = RepScore::query()
            ->whereIn('user_id', $memberships->pluck('user_id')->all())
            ->pluck('score', 'user_id');

        $absences = $this->currentAbsences($memberships->keys()->all());
        $directLoad = $memberships->groupBy('upline_id')->map->count();

        return $memberships
            ->map(function (Membership $m) use ($reps, $absences, $directLoad, $viewer) {
                $score = isset($reps[$m->user_id]) ? (float) $reps[$m->user_id] : null;
                $absence = $absences->get($m->id);

                return [
                    'id' => $m->id,
                    'user_id' => (int) $m->user_id,
                    'user' => $m->user,
                    'name' => $m->user?->name ?? '',
                    'short_name' => $m->user?->shortName() ?? '',
                    'code' => $m->user?->code ?? '',
                    'profile_url' => '/u/'.($m->user?->code ?? ''),
                    'position' => $m->position?->name_ar ?? '',
                    'position_key' => $m->position?->key ?? '',
                    'position_rank' => (int) ($m->position?->rank ?? 0),
                    'entity_id' => (int) $m->entity_id,
                    'entity' => $m->entity?->name_ar ?? '',
                    'upline' => $m->upline?->user?->shortName(),
                    'rep' => $score,
                    'rep_label' => RepBadge::label($score),
                    'rep_state' => RepBadge::state($score),
                    'is_club' => RepBadge::isClubMember($score),
                    'is_acting' => (bool) $m->is_acting,
                    'is_me' => $viewer->id === (int) $m->user_id,
                    'load' => (int) ($directLoad[$m->id] ?? 0),
                    'absent_until' => $absence?->to_date,
                    'delegate' => $absence?->delegate_membership?->user?->shortName(),
                    'status' => $this->statusOf($m, $absence),
                    'started_at' => $m->started_at,
                ];
            })
            ->sortBy([
                fn ($a, $b) => $b['position_rank'] <=> $a['position_rank'],
                fn ($a, $b) => $a['name'] <=> $b['name'],
            ])
            ->values();
    }

    /**
     * فلاتر الشاشة: الفرعيّ · البوزشن · الحالة · بحث بالاسم/الكود (24.4-7).
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    public function filter(Collection $cards, array $filters): Collection
    {
        return $cards
            ->when($filters['entity'] ?? null, fn ($c, $id) => $c->where('entity_id', (int) $id))
            ->when($filters['position'] ?? null, fn ($c, $key) => $c->where('position_key', $key))
            ->when($filters['status'] ?? null, fn ($c, $status) => $c->where('status', $status))
            ->when($filters['q'] ?? null, function ($c, $q) {
                $needle = mb_strtolower(trim((string) $q));

                return $c->filter(fn ($card) => str_contains(mb_strtolower($card['name']), $needle)
                    || str_contains(mb_strtolower((string) $card['code']), $needle));
            })
            ->values();
    }

    /**
     * ملفّ عضو مختصر لبوب-أب الكارت (24.4-7): البوزشن · المدّة · Kudos · الشهادات ·
     * وزرّ واتساب إن سمحت الموافقة أو كنتُ أبلاينه، وإلّا «اطلب إظهار الرقم» — **لا فراغ**.
     *
     * @param  Collection<int, Membership>  $pool
     */
    public function profile(Membership $membership, User $viewer, Collection $pool): array
    {
        $owner = $membership->user;
        $score = $owner ? (float) (RepScore::where('user_id', $owner->id)->value('score') ?? 0) : null;
        $uplineIds = $this->scope->uplineUserIds($membership, $pool);
        $contact = $owner
            ? $this->contacts->forMember($viewer, $owner, $uplineIds, $this->contacts->grantedOwnerIds($viewer))
            : ['visible' => false, 'display' => '', 'whatsapp' => null, 'has_phone' => false];

        return [
            'membership_id' => $membership->id,
            'name' => $owner?->name ?? '',
            'code' => $owner?->code ?? '',
            'avatar' => $owner?->avatar_path,
            'position' => $membership->position?->name_ar ?? '',
            'entity' => $membership->entity?->name_ar ?? '',
            'service_duration' => $this->serviceDuration($membership->started_at),
            'kudos' => $owner ? Kudos::where('receiver_id', $owner->id)->count() : 0,
            'certificates' => $owner ? $owner->certificates()->count() : 0,
            'rep_label' => RepBadge::label($score),
            'rep_state' => RepBadge::state($score),
            'is_club' => RepBadge::isClubMember($score),
            'profile_url' => '/u/'.($owner?->code ?? ''),
            'contact' => $contact,
        ];
    }

    /** مدّة الخدمة منذ التسكين — نصّها من الإعدادات (2.13) */
    public function serviceDuration(?Carbon $since): string
    {
        if (! $since) {
            return (string) setting('volunteer.org.service_duration.unknown', 'لسّه في أوّل الطريق');
        }

        $months = max(0, (int) $since->diffInMonths(now()));
        $years = intdiv($months, 12);
        $rest = $months % 12;

        return match (true) {
            $years > 0 && $rest > 0 => strtr(setting('volunteer_org.member_directory.service_duration_1', ':p1 سنة و:p2 شهر'), [':p1' => (string) ($years), ':p2' => (string) ($rest)]),
            $years > 0 => strtr(setting('volunteer_org.member_directory.service_duration_2', ':p1 سنة'), [':p1' => (string) ($years)]),
            $months > 0 => strtr(setting('volunteer_org.member_directory.service_duration_3', ':p1 شهر'), [':p1' => (string) ($months)]),
            default => strtr(setting('volunteer_org.member_directory.service_duration_4', ':p1 يوم'), [':p1' => (string) ((int) $since->diffInDays(now()))]),
        };
    }

    /**
     * عدّادات الهيدر: الأعضاء · الفرعيّات · الشواغر — **بلا العنصر الشرفيّ** (13.4-ص-ج).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function counters(Entity $root, Collection $memberships): array
    {
        $subEntities = $this->scope->subEntities($root);

        return [
            'members' => $memberships->count(),
            'sub_entities' => $subEntities->count(),
            'vacancies' => $this->vacancies($root, $memberships)->count(),
        ];
    }

    /**
     * الشواغر: كيان بلا مسؤول أصيل — أو مسؤوله **«قائم بأعمال»** لحين الاعتماد البشريّ (13.4-ف-د).
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, array<string, mixed>>
     */
    public function vacancies(Entity $root, Collection $memberships): Collection
    {
        $byEntity = $memberships->groupBy('entity_id');

        return $this->scope->subEntities($root)
            ->map(function (Entity $entity) use ($byEntity) {
                /** @var Collection<int, Membership> $rows */
                $rows = $byEntity->get($entity->id, collect());
                $lead = $rows->sortByDesc(fn (Membership $m) => (int) ($m->position?->rank ?? 0))->first();

                if ($lead && ! $lead->is_acting) {
                    return null;
                }

                // مرشّح سلّم الترقية: أعلى رتبةٍ تحت الشاغر داخل الكيان نفسه
                $candidate = $rows
                    ->when($lead, fn ($c) => $c->where('id', '!=', $lead->id))
                    ->sortByDesc(fn (Membership $m) => (int) ($m->position?->rank ?? 0))
                    ->first();

                return [
                    'entity' => $entity->name_ar,
                    'entity_id' => $entity->id,
                    'acting' => $lead?->user?->shortName(),
                    'candidate' => $candidate?->user?->shortName(),
                    'candidate_position' => $candidate?->position?->name_ar,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * الغيابات السارية اليوم — ومنها شارة «غائب حتى يوم كذا — البديل: فلان».
     *
     * @param  list<int>  $membershipIds
     * @return Collection<int, MembershipAbsence>
     */
    private function currentAbsences(array $membershipIds): Collection
    {
        return MembershipAbsence::query()
            ->whereIn('membership_id', $membershipIds)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->with('delegate_membership.user')
            ->get()
            ->keyBy('membership_id');
    }

    private function statusOf(Membership $m, ?MembershipAbsence $absence): string
    {
        return match (true) {
            $absence !== null || $m->status === 'absent' => 'absent',
            $m->status === 'suspended' => 'suspended',
            (bool) $m->is_acting => 'acting',
            default => 'active',
        };
    }
}
