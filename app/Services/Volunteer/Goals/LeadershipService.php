<?php

namespace App\Services\Volunteer\Goals;

use App\Models\LeadershipCriterion;
use App\Models\LeadershipEvaluation;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * مؤشّر القيادة — «تقييماتي» (الدستور 24.4 · 13.4-ن-د · 23).
 *
 * ثلاث قواعد لا تُكسَر:
 *  1) **من الداونلاين للأبلاين فقط** — لا العكس ولا الأقران.
 *  2) **مجهول تمامًا** — لا يُعاد اسم المقيّم في أيّ عرض ولا في أيّ استعلام للمقيَّم.
 *  3) **لا يظهر المتوسّط إلّا بـ3 مقيّمين فأكثر** — حمايةً للسرّيّة، والعتبة إعداد.
 */
class LeadershipService
{
    /** عتبة المقيّمين — إعداد لا رقم محروق (2.13) */
    public function minRaters(): int
    {
        return max(1, (int) setting('evaluations.min_raters', 3));
    }

    /** نافذة العرض الافتراضيّة (12 أسبوعًا) */
    public function windowWeeks(): int
    {
        return max(1, (int) setting('evaluations.window_weeks', 12));
    }

    /** أقصى درجة للمنزلق */
    public function maxScore(): int
    {
        return max(1, (int) setting('evaluations.max_score', 10));
    }

    /** @return Collection<int,LeadershipCriterion> */
    public function criteria(): Collection
    {
        return LeadershipCriterion::query()
            ->where('is_archived', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function weekStart(?CarbonImmutable $date = null): CarbonImmutable
    {
        $date ??= CarbonImmutable::now();

        return $date->startOfWeek(CarbonImmutable::SATURDAY)->startOfDay();
    }

    /** الأبلاين المباشر داخل العضويّة النشطة — فلا سلطة ولا تقييم عابرَين للكيانات */
    public function uplineOf(User $user, ?Membership $membership = null): ?User
    {
        $membership ??= $user->activeMembership();

        if (! $membership?->upline_id) {
            return null;
        }

        $upline = Membership::query()->with('user')->find($membership->upline_id);

        return $upline?->user;
    }

    public function alreadyEvaluated(User $evaluator, User $evaluatee, ?CarbonImmutable $week = null): bool
    {
        return LeadershipEvaluation::query()
            ->where('evaluator_id', $evaluator->id)
            ->where('evaluatee_id', $evaluatee->id)
            ->whereDate('week_start', ($week ?? $this->weekStart())->toDateString())
            ->exists();
    }

    /**
     * حفظ التقييم الأسبوعيّ — مرّة واحدة لكلّ أسبوع، ومجهولًا تمامًا.
     *
     * @param  array<string,int|float>  $scores  مفتاح المعيار ⟵ الدرجة
     *
     * @throws ValidationException
     */
    public function submit(User $evaluator, User $evaluatee, array $scores, ?string $note = null, ?int $entityId = null): LeadershipEvaluation
    {
        if ($evaluator->id === $evaluatee->id) {
            throw ValidationException::withMessages(['scores' => setting('goals.leadership_service.submit_1', 'التقييم يكون لأبلاينك المباشر — لا لنفسك.')]);
        }

        $criteria = $this->criteria();

        if ($criteria->isEmpty()) {
            throw ValidationException::withMessages(['scores' => setting('goals.leadership_service.submit_2', 'مفيش معايير مفعَّلة حاليًّا — كلّم مشرفك.')]);
        }

        $max = $this->maxScore();
        $clean = [];

        foreach ($criteria as $criterion) {
            $value = (float) ($scores[$criterion->key] ?? 0);

            if ($value < 0 || $value > $max) {
                throw ValidationException::withMessages(['scores' => strtr(setting('goals.leadership_service.submit_3', 'الدرجة لازم تكون بين 0 و:p1.'), [':p1' => (string) ($max)])]);
            }

            $clean[$criterion->key] = round($value, 2);
        }

        $week = $this->weekStart();

        if ($this->alreadyEvaluated($evaluator, $evaluatee, $week)) {
            throw ValidationException::withMessages(['scores' => setting('goals.leadership_service.submit_4', 'قيّمت هذا الأسبوع بالفعل — التقييم مرّة واحدة أسبوعيًّا.')]);
        }

        $average = $this->weightedAverage($clean, $criteria);

        $evaluation = LeadershipEvaluation::create([
            'evaluator_id' => $evaluator->id,
            'evaluatee_id' => $evaluatee->id,
            'entity_id' => $entityId ?? $evaluator->activeMembership()?->entity_id,
            'week_start' => $week->toDateString(),
            'criteria_scores' => $clean,
            'average' => $average,
            'note' => $note,
        ]);

        $this->applyImpactIfThresholdMet($evaluatee, $week);

        return $evaluation;
    }

    /**
     * ⭐ عتبة السرّيّة: المتوسّط محجوب دون العدد الأدنى من المقيّمين.
     *
     * @return array{visible:bool,raters:int,average:?float,per_criterion:array<string,?float>}
     */
    public function receivedForWeek(User $user, CarbonImmutable $week): array
    {
        $rows = LeadershipEvaluation::query()
            ->where('evaluatee_id', $user->id)
            ->whereDate('week_start', $week->toDateString())
            ->get(['criteria_scores', 'average']);

        return $this->summarize($rows);
    }

    /**
     * منحنى 12 أسبوعًا — كلّ أسبوع محجوب على حِدَة إن لم يبلغ العتبة.
     *
     * @return array<int,array{label:string,value:?float,visible:bool,raters:int}>
     */
    public function weeklySeries(User $user, ?int $weeks = null): array
    {
        $weeks = $weeks ?: $this->windowWeeks();
        $start = $this->weekStart()->subWeeks($weeks - 1);

        $rows = LeadershipEvaluation::query()
            ->where('evaluatee_id', $user->id)
            ->where('week_start', '>=', $start->toDateString())
            ->get(['week_start', 'criteria_scores', 'average'])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->week_start)->toDateString());

        $series = [];
        $cursor = $start;

        for ($i = 0; $i < $weeks; $i++) {
            $summary = $this->summarize(collect($rows[$cursor->toDateString()] ?? []));

            $series[] = [
                'label' => $cursor->format('m/d'),
                'value' => $summary['visible'] ? $summary['average'] : null,
                'visible' => $summary['visible'],
                'raters' => $summary['raters'],
            ];

            $cursor = $cursor->addWeek();
        }

        return $series;
    }

    /** ملخّص المدى كلّه (المتوسّط العامّ + تفصيل المعايير) */
    public function receivedSummary(User $user, ?int $weeks = null): array
    {
        $weeks = $weeks ?: $this->windowWeeks();
        $start = $this->weekStart()->subWeeks($weeks - 1);

        $rows = LeadershipEvaluation::query()
            ->where('evaluatee_id', $user->id)
            ->where('week_start', '>=', $start->toDateString())
            ->get(['criteria_scores', 'average']);

        return $this->summarize($rows);
    }

    /** تقييماتي المُرسَلة — بلا كشف للمقيَّم عنهم بأكثر من الاسم الذي أعرفه أصلًا */
    public function givenBy(User $user, ?int $weeks = null): Collection
    {
        $weeks = $weeks ?: $this->windowWeeks();

        return LeadershipEvaluation::query()
            ->where('evaluator_id', $user->id)
            ->where('week_start', '>=', $this->weekStart()->subWeeks($weeks - 1)->toDateString())
            ->with('evaluatee')
            ->orderByDesc('week_start')
            ->get();
    }

    // ------------------------------------------------------------------ داخليّ

    /** @param  Collection<int,LeadershipEvaluation>  $rows */
    private function summarize(Collection $rows): array
    {
        $raters = $rows->count();
        $visible = $raters >= $this->minRaters();

        $perCriterion = [];

        if ($visible) {
            foreach ($this->criteria() as $criterion) {
                $values = $rows
                    ->map(fn ($row) => ($row->criteria_scores[$criterion->key] ?? null))
                    ->filter(fn ($v) => $v !== null);

                $perCriterion[$criterion->key] = $values->isEmpty() ? null : round($values->avg(), 2);
            }
        }

        return [
            'visible' => $visible,
            'raters' => $raters,
            'average' => $visible && $raters > 0 ? round($rows->avg('average'), 2) : null,
            'per_criterion' => $perCriterion,
        ];
    }

    /** @param  Collection<int,LeadershipCriterion>  $criteria */
    private function weightedAverage(array $scores, Collection $criteria): float
    {
        $sum = $weights = 0.0;

        foreach ($criteria as $criterion) {
            $weight = max(1, (int) $criterion->weight);
            $sum += ($scores[$criterion->key] ?? 0) * $weight;
            $weights += $weight;
        }

        return $weights > 0 ? round($sum / $weights, 2) : 0.0;
    }

    /** أثر المؤشّر على Rep يُطبَّق مرّةً واحدة عند بلوغ العتبة بالضبط */
    private function applyImpactIfThresholdMet(User $evaluatee, CarbonImmutable $week): void
    {
        $rows = LeadershipEvaluation::query()
            ->where('evaluatee_id', $evaluatee->id)
            ->whereDate('week_start', $week->toDateString())
            ->get(['average', 'entity_id']);

        if ($rows->count() !== $this->minRaters()) {
            return; // قبل العتبة لا أثر، وبعدها لا تكرار
        }

        app(RepService::class)->applyLeadershipImpact(
            $evaluatee,
            round($rows->avg('average'), 2),
            $rows->first()->entity_id,
        );
    }
}
