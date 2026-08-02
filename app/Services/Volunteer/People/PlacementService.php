<?php

namespace App\Services\Volunteer\People;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\PlacementRequest;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\Volunteer\Org\CardIssuer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * القوائم والتسكين (13.4-هـ · 24.4-12) — وهنا **ثلاثة أقفال إلزاميّة**:
 *
 *  1) **قفل مؤقّت:** لا طلب آخر لنفس الشخص أثناء طلب معلَّق.
 *  2) **قفل ذرّيّ:** مشرفان لا يسكّنان نفس المرشّح لحظيًّا — معاملة قاعدة بيانات
 *     + قفل صفّ + **تحديث شرطيّ** (Compare-and-Swap) يعمل حتى حيث لا قفل صفوف.
 *  3) **مهلة ردّ 48 ساعة:** وبفواتها يعود المرشّح للقائمة تلقائيًّا.
 *
 * والامتلاء هنا **مؤشّر لا مانع** (13.4-ف): القسم الممتلئ يبان بعلامته ولا يُخفى.
 */
class PlacementService
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly PeopleBridge $bridge,
        private readonly CardIssuer $cards,
    ) {}

    /** مهلة ردّ المرشّح بالساعات — من الإعدادات (افتراضيّ 48) */
    public function responseHours(): int
    {
        return max(1, (int) setting('placement.response_hours', 48));
    }

    public function statusLabels(): array
    {
        return [
            'sent' => (string) setting('placement.status.sent.label', 'مُرسَل'),
            'awaiting' => (string) setting('placement.status.awaiting.label', 'بانتظار موافقة المرشّح'),
            'accepted' => (string) setting('placement.status.accepted.label', 'مقبول'),
            'rejected' => (string) setting('placement.status.rejected.label', 'مرفوض'),
            'withdrawn' => (string) setting('placement.status.withdrawn.label', 'مسحوب'),
            'expired' => (string) setting('placement.status.expired.label', 'فاتت المهلة'),
        ];
    }

    public function statusState(string $status): string
    {
        return match ($status) {
            'accepted' => 'ok',
            'rejected', 'expired' => 'danger',
            'withdrawn' => 'idle',
            default => 'warn',
        };
    }

    // ------------------------------------------------------------ القائمة

    /**
     * القائمة النهائيّة مرتّبة.
     *
     * @param  string  $sort  newest = الأحدث أوّلًا (الافتراضيّ) · longest_waiting = الأقدم انتظارًا
     */
    public function finalList(array $filters = [], string $sort = 'newest'): Collection
    {
        return RecruitmentCandidate::query()
            ->with('user')
            ->whereIn('stage', ['final_list', 'placed'])
            ->when(! empty($filters['q']), function ($b) use ($filters) {
                $q = trim((string) $filters['q']);
                $b->whereIn('user_id', User::query()
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->select('id'));
            })
            ->when(isset($filters['score_min']) || isset($filters['score_max']), function ($b) use ($filters) {
                $min = (float) ($filters['score_min'] ?? 0);
                $max = (float) ($filters['score_max'] ?? 100);
                $b->where(fn ($w) => $w->whereNull('qualifying_score')->orWhereBetween('qualifying_score', [$min, $max]));
            })
            /*
             | الأقدم انتظارًا أوّلًا حين يكون الشاغر واحدًا والمرشّحون كُثُر (13.4-هـ)
             | — ويُقاس بـ`applied_at` **وحده** فمدّة الانتظار حقيقةٌ لا تُمحى.
             | أمّا «الأحدث أوّلًا» فيقرأ **آخر إبداء استعداد**: مَن جدّد استعداده
             | «يصعد في القائمة» (13.4-هـ) بلا أن يفقد أقدميّته في الترتيب الآخر.
             */
            ->when($sort === 'longest_waiting',
                fn ($b) => $b->orderBy('applied_at'),
                fn ($b) => $b->orderByRaw('COALESCE(readiness_renewed_at, applied_at) DESC'))
            ->get();
    }

    public function sortOptions(): array
    {
        return [
            'newest' => (string) setting('placement.sort.newest.label', 'الأحدث أوّلًا'),
            'longest_waiting' => (string) setting('placement.sort.longest.label', 'الأقدم انتظارًا أوّلًا'),
        ];
    }

    // ------------------------------------------------------------ الإشغال

    /**
     * نسبة إشغال كلّ قسم فرعيّ — **مؤشّر لا مانع** (13.4-ف).
     *
     * @return array<int, array{entity:Entity,members:int,cap:int|null,percent:int,state:string,full:bool}>
     */
    public function occupancy(?int $parentId = null): array
    {
        $entities = Entity::query()
            ->where('status', 'active')
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNotNull('parent_id'))
            ->orderBy('name_ar')
            ->get();

        $counts = Membership::query()
            ->whereIn('entity_id', $entities->pluck('id'))
            ->where('status', 'active')
            ->selectRaw('entity_id, count(*) as c')
            ->groupBy('entity_id')
            ->pluck('c', 'entity_id');

        $defaultCap = (int) setting('placement.default_member_cap', 12);
        $warnAt = (int) setting('placement.occupancy.warn_percent', 80);

        return $entities->map(function (Entity $entity) use ($counts, $defaultCap, $warnAt) {
            $members = (int) ($counts[$entity->id] ?? 0);
            $cap = (int) ($entity->member_cap ?: $defaultCap);
            $percent = $cap > 0 ? (int) round($members / $cap * 100) : 0;

            return [
                'entity' => $entity,
                'members' => $members,
                'cap' => $cap,
                'percent' => $percent,
                'full' => $percent >= 100,
                'state' => match (true) {
                    $percent >= 100 => 'danger',
                    $percent >= $warnAt => 'warn',
                    default => 'ok',
                },
            ];
        })->all();
    }

    /** اقتراح تلقائيّ: الأقلّ إشغالًا — والمشرف حرّ في تجاوزه */
    public function suggest(array $occupancy): ?Entity
    {
        if ($occupancy === []) {
            return null;
        }

        usort($occupancy, fn ($a, $b) => $a['percent'] <=> $b['percent']);

        return $occupancy[0]['entity'];
    }

    /** قالب رسالة الواتساب — نصّه من الإعدادات (2.13) */
    public function whatsappTemplate(RecruitmentCandidate $candidate, ?Entity $entity = null): string
    {
        return str_replace(
            [':name', ':entity', ':hours'],
            [$candidate->user?->shortName() ?? '', $entity?->name_ar ?? '', (string) $this->responseHours()],
            (string) setting('placement.whatsapp.template',
                'أهلًا :name 👋 عندنا مكان مناسب ليك في :entity. تقدر تدخل المنصّة وتوافق خلال :hours ساعة.'),
        );
    }

    // ------------------------------------------------------------ الأقفال

    /** القفل المؤقّت: هل عليه طلب معلَّق الآن؟ */
    public function pendingRequest(RecruitmentCandidate $candidate): ?PlacementRequest
    {
        if (! $candidate->pending_placement_request_id) {
            return null;
        }

        return PlacementRequest::query()->find($candidate->pending_placement_request_id);
    }

    /**
     * إرسال طلب تسكين.
     *
     * القفل الذرّيّ: كلّ شيء داخل معاملة واحدة، والصفّ مقفول للتحديث، ثمّ
     * **تحديث شرطيّ على `pending_placement_request_id` وهو NULL** — فأوّل مشرف
     * يكسب الصفّ، والثاني يرجع بصفر صفوف متأثّرة فيُرفَض قبل أن يُنشئ شيئًا.
     *
     * @throws \RuntimeException طلب معلَّق قائم بالفعل
     */
    public function request(RecruitmentCandidate $candidate, Entity $entity, Position $position, User $actor, ?string $note = null): PlacementRequest
    {
        return DB::transaction(function () use ($candidate, $entity, $position, $actor, $note) {
            /** @var RecruitmentCandidate $locked */
            $locked = RecruitmentCandidate::query()->lockForUpdate()->findOrFail($candidate->id);

            $this->expireIfDue($locked);

            if ($locked->pending_placement_request_id) {
                throw new \RuntimeException('في طلب معلَّق قائم بالفعل للمرشّح ده — استنّى ردّه أو اسحب الطلب الأوّل.');
            }

            $request = PlacementRequest::create([
                'recruitment_candidate_id' => $locked->id,
                'entity_id' => $entity->id,
                'position_id' => $position->id,
                'requested_by' => $actor->id,
                'status' => 'sent',
                'respond_due_at' => now()->addHours($this->responseHours()),
                'note' => $note,
            ]);

            // ⭐ القفل الذرّيّ: لا يكسب الصفّ إلّا مَن وجده فارغًا
            $won = RecruitmentCandidate::query()
                ->whereKey($locked->id)
                ->whereNull('pending_placement_request_id')
                ->update(['pending_placement_request_id' => $request->id]);

            if ($won === 0) {
                $request->delete();

                throw new \RuntimeException('مشرف تاني سبقك بطلب تسكين للمرشّح ده في نفس اللحظة — حدّث الصفحة وشوف حالته.');
            }

            $this->audit->record($actor, 'placement.requested', $request, [], [
                'candidate' => $locked->id,
                'entity' => $entity->id,
                'position' => $position->id,
                'due' => $request->respond_due_at->toDateTimeString(),
            ]);

            if ($locked->user) {
                $this->bridge->notify(
                    $locked->user,
                    'recruitment',
                    'وصلك طلب تسكين',
                    'قسم '.$entity->name_ar.' — لازم تردّ خلال '.$this->responseHours().' ساعة.',
                    route('volunteer.placement'),
                    $request->respond_due_at,
                    true,
                );
            }

            return $request;
        });
    }

    /** سحب الطلب قبل الموافقة — ويحرّر القفل المؤقّت */
    public function withdraw(PlacementRequest $request, User $actor): PlacementRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            if (! in_array($request->status, ['sent', 'awaiting'], true)) {
                throw new \RuntimeException('الطلب ده اتقفل خلاص — مش هينفع يتسحب.');
            }

            $request->forceFill(['status' => 'withdrawn'])->save();
            $this->release($request);

            $this->audit->record($actor, 'placement.withdrawn', $request, ['status' => 'sent'], ['status' => 'withdrawn']);

            return $request;
        });
    }

    /**
     * ردّ المرشّح: قبول ⟵ عضويّة + احتفال ذروة · رفض ⟵ لا شيء يحصل والقائمة تفضل مفتوحة.
     *
     * @throws \RuntimeException خارج المهلة
     */
    public function respond(PlacementRequest $request, string $decision, User $actor, ?string $note = null): PlacementRequest
    {
        return DB::transaction(function () use ($request, $decision, $actor, $note) {
            /** @var PlacementRequest $locked */
            $locked = PlacementRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! in_array($locked->status, ['sent', 'awaiting'], true)) {
                throw new \RuntimeException('الطلب ده اتقفل خلاص.');
            }

            if (now()->greaterThan($locked->respond_due_at)) {
                $this->expire($locked);

                throw new \RuntimeException('المهلة فاتت والطلب رجع للقائمة — فريق التوظيف هيبعتلك من جديد.');
            }

            $locked->forceFill([
                'status' => $decision === 'accepted' ? 'accepted' : 'rejected',
                'responded_at' => now(),
                'respond_note' => $note,
            ])->save();

            $this->release($locked);

            $candidate = RecruitmentCandidate::query()->find($locked->recruitment_candidate_id);

            if ($decision === 'accepted' && $candidate) {
                $this->activate($locked, $candidate);
            }

            $this->audit->record($actor, 'placement.responded', $locked, [], [
                'decision' => $decision,
                'note' => $note,
            ]);

            return $locked;
        });
    }

    /**
     * ⭐ مهلة الـ48 ساعة: ما فات موعده يرجع للقائمة — تُستدعى عند كلّ فتح للصفحة
     * فلا تعتمد الميزة على وجود كرون.
     *
     * @return int عدد الطلبات التي انتهت مهلتها
     */
    public function expireOverdue(): int
    {
        $due = PlacementRequest::query()
            ->whereIn('status', ['sent', 'awaiting'])
            ->where('respond_due_at', '<', now())
            ->get();

        foreach ($due as $request) {
            DB::transaction(fn () => $this->expire($request));
        }

        return $due->count();
    }

    // ------------------------------------------------------------ داخليّ

    private function expireIfDue(RecruitmentCandidate $candidate): void
    {
        $pending = $this->pendingRequest($candidate);

        if ($pending && now()->greaterThan($pending->respond_due_at)) {
            $this->expire($pending);
            $candidate->refresh();
        }
    }

    private function expire(PlacementRequest $request): void
    {
        $request->forceFill(['status' => 'expired'])->save();
        $this->release($request);

        $this->audit->record(null, 'placement.expired', $request, [], [
            'due' => $request->respond_due_at?->toDateTimeString(),
        ]);
    }

    /** تحرير القفل المؤقّت — الشرط على رقم الطلب نفسه فلا يُحرَّر قفلُ غيره */
    private function release(PlacementRequest $request): void
    {
        RecruitmentCandidate::query()
            ->whereKey($request->recruitment_candidate_id)
            ->where('pending_placement_request_id', $request->id)
            ->update(['pending_placement_request_id' => null]);
    }

    /** القبول: عضويّة جديدة + بطاقة رقميّة + المُسكَّن غير مفعَّل في القائمة + احتفال ذروة */
    private function activate(PlacementRequest $request, RecruitmentCandidate $candidate): void
    {
        $membership = Membership::create([
            'user_id' => $candidate->user_id,
            'entity_id' => $request->entity_id,
            'position_id' => $request->position_id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        /*
         | ⭐ البطاقة الرقميّة **تُصدَر لحظة التسكين** (13.4-ر-ج) — لا عند أوّل
         | زيارةٍ لصفحتها. كان `CardIssuer::issueFor()` بلا مستدعٍ إطلاقًا، فتسكينٌ
         | كامل يمرّ والبطاقات صفر قبله وصفر بعده. و«أخوكم» مستثنًى داخل المُصدِر
         | نفسه (13.4-ص-و) فلا شرط مكرّر هنا.
         */
        $card = $this->cards->issueFor($membership->fresh(['user', 'position', 'entity.track']));

        if ($card && $candidate->user) {
            $this->bridge->celebrate($candidate->user, 'volunteer_card.issued', $card);
        }

        // لا يختفي من القائمة — يصير غير مفعَّل ويظلّ ظاهرًا لكلّ مخوَّل (13.4-هـ)
        $candidate->forceFill([
            'stage' => 'placed',
            'stage_changed_at' => now(),
            'is_active_in_list' => false,
        ])->save();

        if ($candidate->user) {
            $this->bridge->celebrate($candidate->user, 'placement.accepted', $request);
            $this->bridge->notify($candidate->user, 'recruitment', 'أهلًا بيك معانا 🎉',
                'اتسكّنت في قسمك — لوحة التطوّع بقت متاحة ليك.', route('volunteer.placement'));
        }
    }
}
