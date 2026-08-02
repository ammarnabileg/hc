<?php

namespace App\Services\Volunteer\Profile;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * تاب «الهيكل التنظيميّ» (13.4-م-3): موقعه · تايم-لاين البوزشنز ·
 * **سلسلة الأبلاين كاملة لأعلى** · الداونلاين · سجلّ الحركات التنظيميّة.
 *
 * والداونلاين لا يُعاد بناؤه هنا: يُوصَل بكانفاس الهيكل القائم `volunteer.org`
 * (مصدر واحد بلا تكرار)، ويُعرَض هنا ملخّصه فقط.
 *
 * ⛔ **المؤشّر الأحمر لا يُعرَض هنا** — مكانه مع مؤشّر Rep بجانب الاسم (13.4-هـ).
 */
final class OrganizationPanel
{
    public function __construct(private readonly ViewerLevel $levels) {}

    public function build(User $owner, ?User $viewer, string $level, ?Membership $membership): array
    {
        $privileged = $this->levels->isPrivileged($level);

        return [
            'membership' => $membership,
            'entity' => $membership?->entity?->name_ar,
            'parent_entity' => $membership?->entity?->parent?->name_ar,
            'position' => $membership?->position?->name_ar,
            'placed_at' => $membership?->started_at,
            'timeline' => $this->positionTimeline($owner),
            'upline_chain' => $membership ? $this->uplineChain($membership) : collect(),
            'downline' => $membership ? $this->downline($membership) : collect(),
            'network_total' => $membership ? $this->networkTotal($membership) : 0,
            // سجلّ الحركات: بمَن نفّذها ومتى — **للمخوَّل** وحده (13.4-م-3)
            'movements' => $privileged ? $this->movements($owner) : collect(),
            'shows_movements' => $privileged,
            'canvas_url' => route('volunteer.org'),
            'can_open_canvas' => $viewer?->allows('org_chart.view', $owner) === true,
        ];
    }

    /**
     * تايم-لاين البوزشنز بتواريخ كلّ انتقال — ومنه تُصدَر شهادة البوزشن.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function positionTimeline(User $owner): Collection
    {
        return Membership::query()
            ->with(['position:id,name_ar,rank', 'entity:id,name_ar'])
            ->where('user_id', $owner->id)
            ->orderBy('started_at')
            ->get()
            ->map(fn (Membership $m) => [
                'position' => $m->position?->name_ar ?? '',
                'entity' => $m->entity?->name_ar ?? '',
                'from' => $m->started_at,
                'to' => $m->ended_at,
                'is_current' => $m->status === 'active' && $m->ended_at === null,
                'is_acting' => (bool) $m->is_acting,
            ]);
    }

    /**
     * سلسلة الأبلاين **كاملة لأعلى** حتى مشرف عامّ التطوّع —
     * نفس منطق سلّم التصعيد المرئيّ (13.4-ط).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function uplineChain(Membership $membership): Collection
    {
        $chain = collect();
        $current = $membership->upline;
        $guard = 0;

        while ($current && $guard++ < 50) {
            $current->loadMissing(['user:id,name,code,avatar_path', 'position:id,name_ar,rank', 'entity:id,name_ar']);

            $chain->push([
                'name' => $current->user?->shortName() ?? '',
                'code' => $current->user?->code ?? '',
                'position' => $current->position?->name_ar ?? '',
                'entity' => $current->entity?->name_ar ?? '',
                'profile_url' => $current->user ? '/u/'.$current->user->code : null,
                'is_direct' => $chain->isEmpty(),
            ]);

            $current = $current->upline;
        }

        return $chain;
    }

    /**
     * الداونلاين المباشر — كروت مضغوطة، والتفاصيل الكاملة في الكانفاس.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function downline(Membership $membership): Collection
    {
        return Membership::query()
            ->with(['user:id,name,code,avatar_path', 'position:id,name_ar'])
            ->where('upline_id', $membership->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'code' => $m->user?->code ?? '',
                'position' => $m->position?->name_ar ?? '',
                'profile_url' => $m->user ? '/u/'.$m->user->code : null,
                // حِمل كلّ أبلاين: عدد مَن تحته — أداة موازنة توزيع لا حكم على شخص
                'load' => Membership::where('upline_id', $m->id)->where('status', 'active')->count(),
            ]);
    }

    /** عدّاد الشبكة الكاملة: كلّ المستويات تحته لا المستوى الأوّل فقط (13.4-م-3) */
    public function networkTotal(Membership $membership): int
    {
        $ids = [$membership->id];
        $total = 0;
        $guard = 0;

        while ($ids !== [] && $guard++ < 50) {
            $ids = Membership::query()
                ->whereIn('upline_id', $ids)
                ->where('status', 'active')
                ->pluck('id')
                ->all();

            $total += count($ids);
        }

        return $total;
    }

    /**
     * سجلّ الحركات التنظيميّة: نقل قسم · ترقية · تغيير أبلاين · تعليق —
     * بمَن نفّذها ومتى، من سجلّ التدقيق الواحد (Audit).
     *
     * @return Collection<int, AuditLog>
     */
    public function movements(User $owner): Collection
    {
        $membershipIds = Membership::where('user_id', $owner->id)->pluck('id');

        return AuditLog::query()
            ->with('user:id,name,code')
            ->where(function ($q) use ($membershipIds, $owner) {
                $q->where(fn ($b) => $b->where('auditable_type', Membership::class)->whereIn('auditable_id', $membershipIds))
                    ->orWhere(fn ($b) => $b->where('auditable_type', User::class)->where('auditable_id', $owner->id));
            })
            ->latest('id')
            ->limit((int) setting('volunteer.profile.org.movements_size', 20))
            ->get();
    }
}
