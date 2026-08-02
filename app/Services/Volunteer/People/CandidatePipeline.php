<?php

namespace App\Services\Volunteer\People;

use App\Models\CandidateEntityFit;
use App\Models\Entity;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Support\Collection;

/**
 * لوحة المرشّحين (13.4-د · 24.4-12): أعمدة الرحلة والفلاتر ونقل الكارت بسبب.
 *
 * قاعدتان حاكمتان هنا:
 *  1) كلّ نقل بين الأعمدة **بسبب مكتوب** ويُسجَّل في `audit_logs`.
 *  2) الخصوصيّة طبقتان لا طبقة: **الرقم لفريق التوظيف**، و**سبب الخروج للمخوَّلين وحدهم**.
 */
class CandidatePipeline
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** أعمدة الرحلة — عناوينها من الإعدادات فلا نصّ محروق (2.13) */
    public function stages(): array
    {
        return [
            'applied' => (string) setting('recruitment.stage.applied.label', 'تقديم'),
            'screening' => (string) setting('recruitment.stage.screening.label', 'فرز'),
            'interview' => (string) setting('recruitment.stage.interview.label', 'مقابلة'),
            'final_list' => (string) setting('recruitment.stage.final_list.label', 'قائمة نهائيّة'),
            'placed' => (string) setting('recruitment.stage.placed.label', 'تسكين'),
        ];
    }

    /**
     * بناء الأعمدة بعد تطبيق الفلاتر.
     *
     * @param  array{stage?:string,entity?:int|string,score_min?:float,score_max?:float,waiting?:string,q?:string}  $filters
     * @return array<string, Collection<int, RecruitmentCandidate>>
     */
    public function board(User $viewer, array $filters = []): array
    {
        $candidates = $this->query($viewer, $filters)->get();
        $fits = $this->fitsFor($candidates->pluck('id')->all());

        $columns = [];

        foreach (array_keys($this->stages()) as $stage) {
            $columns[$stage] = $candidates
                ->where('stage', $stage)
                ->values()
                ->each(fn (RecruitmentCandidate $c) => $c->setRelation('fits', $fits[$c->id] ?? collect()));
        }

        return $columns;
    }

    /** استعلام المرشّحين المفلتَر — يُعاد استعماله في صفحة التسكين أيضًا */
    public function query(User $viewer, array $filters = [])
    {
        $min = (float) ($filters['score_min'] ?? $this->scoreFloor());
        $max = (float) ($filters['score_max'] ?? $this->scoreCeiling());
        $q = trim((string) ($filters['q'] ?? ''));

        // ⭐ النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب): مَن نطاقه قسمٌ لا يرى
        // مرشّحي المنصّة كلّها — والمرشّح يُقاس بأقسامه المناسبة لأنّه بلا عضويّة بعد.
        $visibleEntities = app(ScopeFilter::class)->visibleEntityIds($viewer, 'candidates.list');

        return RecruitmentCandidate::query()
            ->with('user')
            ->when($visibleEntities !== null, fn ($b) => $b->whereIn('id', CandidateEntityFit::query()
                ->whereIn('entity_id', $visibleEntities === [] ? [0] : $visibleEntities)
                ->select('recruitment_candidate_id')))
            ->when(! empty($filters['stage']), fn ($b) => $b->where('stage', $filters['stage']))
            ->when(isset($filters['score_min']) || isset($filters['score_max']), function ($b) use ($min, $max) {
                // نطاق الدرجات بمنزلق مدى — والمرشّح بلا درجة لا يُقصى بصمت
                $b->where(fn ($w) => $w->whereNull('qualifying_score')
                    ->orWhereBetween('qualifying_score', [$min, $max]));
            })
            ->when(! empty($filters['entity']), function ($b) use ($filters) {
                $b->whereIn('id', CandidateEntityFit::query()
                    ->where('entity_id', (int) $filters['entity'])
                    ->select('recruitment_candidate_id'));
            })
            ->when(! empty($filters['waiting']), function ($b) use ($filters) {
                $days = (int) $filters['waiting'];
                $b->where('applied_at', '<=', now()->subDays($days));
            })
            ->when($q !== '', function ($b) use ($q) {
                $b->whereIn('user_id', User::query()
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->select('id'));
            })
            ->orderByDesc('applied_at');
    }

    /** الأقسام المناسبة لكلّ مرشّح — استعلام واحد لا N+1 */
    public function fitsFor(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        return CandidateEntityFit::query()
            ->with('entity')
            ->whereIn('recruitment_candidate_id', $candidateIds)
            ->get()
            ->groupBy('recruitment_candidate_id')
            ->map(fn ($rows) => $rows->pluck('entity')->filter()->values())
            ->all();
    }

    /**
     * نقل الكارت بين الأعمدة — **لا نقل بلا سبب**، والحركة تدخل سجلّ التدقيق.
     *
     * @throws \InvalidArgumentException مرحلة غير معروفة أو سبب فارغ
     */
    public function move(RecruitmentCandidate $candidate, string $toStage, string $reason, User $actor): RecruitmentCandidate
    {
        if (! array_key_exists($toStage, $this->stages())) {
            throw new \InvalidArgumentException('المرحلة دي مش موجودة — اختر مرحلة من أعمدة اللوحة.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('اكتب سبب النقل — كلّ حركة على المرشّح لازم يكون ليها سبب مسجّل.');
        }

        $from = (string) $candidate->stage;

        $candidate->forceFill([
            'stage' => $toStage,
            'stage_changed_at' => now(),
        ])->save();

        $this->audit->record($actor, 'candidate.stage_moved', $candidate,
            ['stage' => $from],
            ['stage' => $toStage, 'reason' => trim($reason)],
        );

        return $candidate;
    }

    // ------------------------------------------------------------ العرض والخصوصيّة

    /** مدّة الانتظار بالأيّام منذ التقديم */
    public function waitingDays(RecruitmentCandidate $candidate): int
    {
        $since = $candidate->applied_at ?? $candidate->created_at;

        return $since ? (int) $since->diffInDays(now()) : 0;
    }

    /** شارة مدّة الانتظار: رمز + لون من قاموس الحالة (2.16) — لا لون بلا رمز */
    public function waitingState(RecruitmentCandidate $candidate): string
    {
        $days = $this->waitingDays($candidate);

        return match (true) {
            $days >= (int) setting('recruitment.waiting.danger_days', 60) => 'danger',
            $days >= (int) setting('recruitment.waiting.warn_days', 30) => 'warn',
            default => 'ok',
        };
    }

    /**
     * ⭐ رقم المرشّح لفريق التوظيف وحده (13.4-د — حوكمة الخصوصيّة).
     * ومَن لا يملكها لا يرى الحقل أصلًا — لا معطَّلًا ولا مموّهًا (2.15-أ-7).
     */
    public function canSeePhone(User $viewer): bool
    {
        return $viewer->allows('candidates.view');
    }

    /**
     * ⭐ سبب الخروج للمخوَّلين وحدهم — **ولا يُنشَر لفريق التوظيف** (13.4-ق-هـ).
     * فصلاحيّة الأوفبوردنج هي البوّابة، لا صلاحيّة التوظيف.
     */
    public function canSeeExitReason(User $viewer): bool
    {
        return $viewer->allows('offboarding.view');
    }

    /** سطر شارة «عائد»: «كان معنا من [تاريخ] إلى [تاريخ] — مدّة الخدمة [كذا]» (13.4-ق-و) */
    public function returningLine(RecruitmentCandidate $candidate): ?string
    {
        if (! $candidate->is_returning || ! $candidate->previous_service_from) {
            return null;
        }

        $from = $candidate->previous_service_from;
        $to = $candidate->previous_service_to ?? $from;
        $months = max(1, (int) round($from->diffInMonths($to)));

        return str_replace(
            [':from', ':to', ':duration'],
            [$from->translatedFormat('j F Y'), $to->translatedFormat('j F Y'), $months.' شهرًا'],
            (string) setting('recruitment.returning.line', 'كان معنا من :from إلى :to — مدّة الخدمة :duration'),
        );
    }

    /** شجرة الأقسام (رئيسيّ ⟵ فرعيّ) لفلاتر «القسم المناسب» وسكشن الأقسام */
    public function entityTree(): Collection
    {
        $all = Entity::query()->where('status', 'active')->orderBy('name_ar')->get();

        return $all->whereNull('parent_id')->values()->map(function (Entity $root) use ($all) {
            $root->setRelation('children', $all->where('parent_id', $root->id)->values());

            return $root;
        });
    }

    public function scoreFloor(): float
    {
        return (float) setting('recruitment.score.min', 0);
    }

    public function scoreCeiling(): float
    {
        return (float) setting('recruitment.score.max', 100);
    }
}
