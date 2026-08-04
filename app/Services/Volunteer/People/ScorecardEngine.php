<?php

namespace App\Services\Volunteer\People;

use App\Models\CandidateEntityFit;
use App\Models\Interview;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * نموذج نتيجة المقابلة (13.4-د · 24.4-12).
 *
 * ثلاث قواعد لا تتزحزح:
 *  1) **الدرجة الإجماليّة تلقائيّة** من المعايير بأوزانها — لا يكتبها أحد بيده.
 *  2) **المعيار المؤرشف يُعرَض بدرجته** بوسم «معيار مؤرشف» ولا يُحسَب في إجماليّ اليوم.
 *  3) **الحفظ التلقائيّ مسودّة** — والمسودّة ليست قرارًا حتى يُعتمَد صراحةً.
 */
class ScorecardEngine
{
    public const ARCHIVED_TAG = 'معيار مؤرشف';

    /** @var Collection<int, InterviewCriterion>|null كاش للطلب الواحد — الحساب يتكرّر لكلّ صفّ */
    private ?Collection $activeCache = null;

    public function __construct(private readonly AuditTrail $audit) {}

    /** المعايير الحيّة فقط — وهي وحدها ما يُعرَض للإدخال */
    public function activeCriteria(): Collection
    {
        return $this->activeCache ??= InterviewCriterion::query()
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
    }

    /** كلّ المعايير (حيّة ومؤرشفة) — للعرض على نتيجة قديمة */
    public function allCriteria(): Collection
    {
        return InterviewCriterion::query()->orderBy('sort_order')->get()->keyBy('id');
    }

    public function scale(): int
    {
        return max(1, (int) setting('scorecards.scale_max', 10));
    }

    /**
     * الدرجة الإجماليّة: متوسّط موزون على مقياس الإعدادات.
     * والمعايير المؤرشفة **لا تدخل الحساب** — لأنّها لم تعد جزءًا من معيار القبول.
     *
     * @param  array<int|string, mixed>  $scores  criterion_id => score
     */
    public function total(array $scores): float
    {
        $normalized = $this->normalize($scores);
        $weighted = 0.0;
        $weights = 0.0;

        foreach ($this->activeCriteria() as $criterion) {
            if (! array_key_exists((string) $criterion->id, $normalized)) {
                continue;
            }

            $value = (float) $normalized[(string) $criterion->id];
            $weight = max(0, (int) $criterion->weight) ?: 1;

            $weighted += $value * $weight;
            $weights += $weight;
        }

        return $weights > 0 ? round($weighted / $weights, 2) : 0.0;
    }

    /**
     * صفوف العرض: المعيار · درجته · وسمه إن كان مؤرشفًا.
     *
     * @return array<int, array{id:int,label:string,weight:int,score:float|null,archived:bool}>
     */
    public function rows(?InterviewScorecard $card): array
    {
        $scores = $this->normalize($card?->criteria_scores ?? []);
        $all = $this->allCriteria();
        $rows = [];

        foreach ($all as $criterion) {
            $has = array_key_exists((string) $criterion->id, $scores);

            // المؤرشف يظهر فقط إن كانت له درجة مسجّلة — ولا يُعرَض للإدخال من جديد
            if ($criterion->is_archived && ! $has) {
                continue;
            }

            $rows[] = [
                'id' => (int) $criterion->id,
                'label' => (string) $criterion->label_ar,
                'weight' => max(1, (int) $criterion->weight),
                'score' => $has ? (float) $scores[(string) $criterion->id] : null,
                'archived' => (bool) $criterion->is_archived,
            ];
        }

        return $rows;
    }

    /**
     * مؤشّر الحقول الإجباريّة الناقصة (2.15-د) — قائمة نصّيّة تُعرَض قبل الحفظ.
     *
     * @return array<int, string>
     */
    public function missing(InterviewScorecard $card): array
    {
        $missing = [];
        $scores = $this->normalize($card->criteria_scores ?? []);

        if ((bool) setting('scorecards.skills.required', true) && trim((string) $card->skills_notes) === '') {
            $missing[] = (string) setting('scorecards.skills.label', 'المهارات');
        }

        if ((bool) setting('scorecards.personality.required', true) && trim((string) $card->personality_notes) === '') {
            $missing[] = (string) setting('scorecards.personality.label', 'تحليل الشخصيّة');
        }

        foreach ($this->activeCriteria() as $criterion) {
            if (! array_key_exists((string) $criterion->id, $scores)) {
                $missing[] = (string) $criterion->label_ar;
            }
        }

        return $missing;
    }

    /** حفظ تلقائيّ كمسودّة — يرجع «اتحفظ ✓» للواجهة (2.17-ب) */
    public function autosave(Interview $interview, array $payload, User $actor): InterviewScorecard
    {
        $card = InterviewScorecard::firstOrNew(['interview_id' => $interview->id]);

        $scores = $this->clamp($payload['criteria_scores'] ?? []);

        $card->fill([
            'skills_notes' => $payload['skills_notes'] ?? $card->skills_notes,
            'personality_notes' => $payload['personality_notes'] ?? $card->personality_notes,
            'criteria_scores' => $scores,
            'total_score' => $this->total($scores),
            'is_draft' => true,
        ])->save();

        return $card;
    }

    /**
     * القرار النهائيّ: نجح ⟵ القائمة النهائيّة · رفض بسبب.
     *
     * @throws \InvalidArgumentException
     */
    public function decide(InterviewScorecard $card, string $decision, ?string $reason, User $actor): InterviewScorecard
    {
        if (! in_array($decision, ['passed', 'rejected'], true)) {
            throw new \InvalidArgumentException(setting('recruitment.scorecard_engine.decide_1', 'القرار لازم يكون «نجح» أو «رفض».'));
        }

        if ($decision === 'rejected' && trim((string) $reason) === '') {
            throw new \InvalidArgumentException(setting('recruitment.scorecard_engine.decide_2', 'الرفض بسبب مكتوب — عشان يبقى في سجلّ يُرجَع إليه.'));
        }

        $missing = $this->missing($card);

        if ($missing !== []) {
            throw new \InvalidArgumentException(strtr(setting('recruitment.scorecard_engine.decide_3', 'ناقص: :p1 — كمّلها وبعدين احفظ القرار.'), [':p1' => (string) (implode(' · ', $missing))]));
        }

        $card->forceFill([
            'decision' => $decision,
            'rejection_reason' => $decision === 'rejected' ? trim((string) $reason) : null,
            'is_draft' => false,
        ])->save();

        $candidate = $card->interview?->recruitment_candidate;

        if ($candidate) {
            $from = (string) $candidate->stage;
            $candidate->forceFill([
                'stage' => $decision === 'passed' ? 'final_list' : 'rejected',
                'stage_changed_at' => now(),
            ])->save();

            $this->audit->record($actor, 'scorecard.decided', $candidate,
                ['stage' => $from],
                ['stage' => $candidate->stage, 'decision' => $decision, 'reason' => $reason]);
        }

        return $card;
    }

    /**
     * سكشن «الأقسام المناسبة»: اختيار متعدّد يُكتَب في `candidate_entity_fits`.
     * واختيار الرئيسيّ يحدّد فرعيّاته — والقرار يُتّخذ في الواجهة ويُحفَظ هنا كقائمة نهائيّة.
     *
     * @param  array<int, int>  $entityIds
     */
    public function syncFits(Interview $interview, array $entityIds, User $actor): int
    {
        $candidateId = (int) $interview->recruitment_candidate_id;
        $ids = array_values(array_unique(array_map('intval', $entityIds)));

        CandidateEntityFit::query()
            ->where('recruitment_candidate_id', $candidateId)
            ->whereNotIn('entity_id', $ids ?: [0])
            ->delete();

        foreach ($ids as $entityId) {
            CandidateEntityFit::firstOrCreate([
                'recruitment_candidate_id' => $candidateId,
                'entity_id' => $entityId,
            ]);
        }

        return count($ids);
    }

    /** ملخّص نصّيّ للمعاينة وللتصدير */
    public function summary(InterviewScorecard $card): string
    {
        $lines = [];
        $lines[] = strtr(setting('recruitment.scorecard_engine.summary_1', 'المهارات: :p1'), [':p1' => (string) ((trim((string) $card->skills_notes) ?: '—'))]);
        $lines[] = strtr(setting('recruitment.scorecard_engine.summary_2', 'تحليل الشخصيّة: :p1'), [':p1' => (string) ((trim((string) $card->personality_notes) ?: '—'))]);

        foreach ($this->rows($card) as $row) {
            $lines[] = strtr(setting('recruitment.scorecard_engine.summary_3', ':p1:p2: :p3/:p4 — وزن :p5'), [':p1' => (string) ($row['label']), ':p2' => (string) (($row['archived'] ? ' ('.self::ARCHIVED_TAG.')' : '')), ':p3' => (string) (($row['score'] ?? '—')), ':p4' => (string) ($this->scale()), ':p5' => (string) ($row['weight'])]);
        }

        $lines[] = strtr(setting('recruitment.scorecard_engine.summary_4', 'الدرجة الإجماليّة: :p1/:p2'), [':p1' => (string) ((string) $card->total_score), ':p2' => (string) ($this->scale())]);

        return implode("\n", $lines);
    }

    /** @param array<int|string, mixed> $scores */
    private function normalize(array|string|null $scores): array
    {
        $scores = is_string($scores) ? (json_decode($scores, true) ?: []) : ($scores ?: []);
        $out = [];

        foreach ($scores as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /** حصر الدرجة داخل المقياس — فلا تدخل درجة خارج /10 من طلبٍ مصنوع */
    private function clamp(array|string|null $scores): array
    {
        $max = $this->scale();
        $out = [];

        foreach ($this->normalize($scores) as $id => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $out[(string) (int) $id] = max(0, min($max, (float) $value));
        }

        return $out;
    }
}
