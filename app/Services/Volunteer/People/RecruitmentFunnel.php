<?php

namespace App\Services\Volunteer\People;

use App\Models\AuditLog;
use App\Models\CandidateEntityFit;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * قمع التطوّع (24.4 — تحليلات التطوّع · الصلاحيّة `recruitment_analytics.*`):
 * بدأ ⟵ أتمّ ⟵ مقابلة ⟵ مقبول ⟵ مُسكَّن، مع متوسّط زمن كلّ مرحلة —
 * **مادّة قرار لا أرقام زينة** (نفس معيار بقيّة تحليلات التطوّع).
 *
 * المصدر: `recruitment_candidates.stage` للمرحلة الحاليّة + سجلّ `audit_logs`
 * (`candidate.stage_moved`) لِتَتَبُّع **وقت كلّ نقلة** — فلا حاجة لجدول تاريخ
 * منفصل: كلّ نقلة أصلًا **بسبب مكتوب** تدخل `audit_logs` (`CandidatePipeline::move`).
 *
 * ⭐ حدود مقصودة (لا نقص سهوًا):
 *  - المراحل الخمس هي فقط مفاتيح `CandidatePipeline::stages()` — و«مرفوض» ليس
 *    مرحلة قمع بل خروج، فلا يُحتسَب مقصودًا (يطابق نصّ الصلاحيّة حرفيًّا).
 *  - متوسّط الزمن يُحسَب بين مرحلتين **متجاورتين في الترتيب فقط**: نقلة تتخطّى
 *    مرحلة (تطبيقٌ يدويّ مباشر لمقابلة) لا تُحتسَب زمنًا لمرحلة لم تُزَر، ونقلة
 *    للخلف (تصحيح) لا تُفسِد حساب النقلة الصحيحة التالية.
 *  - «مُسكَّن» نهاية القمع فلا نقلة بعدها؛ فالمتوسّطات أربعة لا خمسة (بين كلّ
 *    مرحلة والتي تليها) — موثَّق في `_STATUS.md` بدل أن يُخترَع رقم بلا معنى.
 *  - متوسّط الزمن يُحسَب فقط ممّن **أكمل** النقلة (خرج من المرحلة فعلًا)؛ مَن لا
 *    يزال منتظرًا فيها لا يُحتسَب لأنّ مدّته لم تنتهِ بعد (لا يُقاس بعدّاد متوقّف).
 */
class RecruitmentFunnel
{
    public function __construct(private readonly CandidatePipeline $pipeline) {}

    /** ترتيب مفاتيح القمع الخمسة — من نفس مصدر أعمدة لوحة المرشّحين (لا مصدر ثانٍ) */
    public function stageOrder(): array
    {
        return array_keys($this->pipeline->stages());
    }

    /**
     * استعلام المرشّحين المرئيّين لهذه الصلاحيّة تحديدًا — نطاقٌ مستقلّ عن
     * `candidates.list` لأنّ حامل `recruitment_analytics.view` قد لا يحمل
     * صلاحيّة لوحة المرشّحين نفسها (12.2.1-ب: النطاق إلزاميّ مع كلّ صلاحيّة).
     *
     * @param  array{days?:int|null,entity?:int|string|null}  $filters
     */
    public function query(User $viewer, array $filters = [])
    {
        $visibleEntities = app(ScopeFilter::class)->visibleEntityIds($viewer, 'recruitment_analytics.view');

        return RecruitmentCandidate::query()
            ->when($visibleEntities !== null, fn ($b) => $b->whereIn('id', CandidateEntityFit::query()
                ->whereIn('entity_id', $visibleEntities === [] ? [0] : $visibleEntities)
                ->select('recruitment_candidate_id')))
            ->when(! empty($filters['entity']), fn ($b) => $b->whereIn('id', CandidateEntityFit::query()
                ->where('entity_id', (int) $filters['entity'])
                ->select('recruitment_candidate_id')))
            ->when(! empty($filters['days']), fn ($b) => $b->where(
                fn ($w) => $w->where('applied_at', '>=', now()->subDays((int) $filters['days']))
                    ->orWhere(fn ($w2) => $w2->whereNull('applied_at')->where('created_at', '>=', now()->subDays((int) $filters['days'])))
            ));
    }

    /**
     * القمع كاملًا: عدد مَن بلغ كلّ مرحلة (لو مرّةً واحدةً على الأقلّ) + متوسّط
     * زمن كلّ مرحلة بالساعات واليوم + حجم العيّنة (كم مرشّحًا احتُسِب منه المتوسّط).
     *
     * @return array{stages: array<int, array{key:string,label:string,count:int,pct_of_start:?float}>,
     *               durations: array<int, array{from_key:string,from_label:string,to_key:string,to_label:string,avg_hours:?float,avg_days:?float,sample:int}>,
     *               total: int}
     */
    public function compute(User $viewer, array $filters = []): array
    {
        $labels = $this->pipeline->stages();
        $order = array_keys($labels);

        $candidates = $this->query($viewer, $filters)->get(['id', 'applied_at', 'created_at']);

        $logsByCandidate = $candidates->isEmpty()
            ? collect()
            : AuditLog::query()
                ->where('auditable_type', (new RecruitmentCandidate)->getMorphClass())
                ->whereIn('auditable_id', $candidates->pluck('id'))
                ->where('action', 'candidate.stage_moved')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['auditable_id', 'new_values', 'created_at'])
                ->groupBy('auditable_id');

        $reached = array_fill_keys($order, 0);
        $durationSeconds = array_fill_keys($order, 0.0);
        $durationSamples = array_fill_keys($order, 0);

        foreach ($candidates as $candidate) {
            $sequence = $this->journeyOf($candidate, $logsByCandidate->get($candidate->id, collect()));

            foreach (array_unique(array_column($sequence, 'stage')) as $stageKey) {
                if (array_key_exists($stageKey, $reached)) {
                    $reached[$stageKey]++;
                }
            }

            for ($i = 0; $i < count($sequence) - 1; $i++) {
                $from = $sequence[$i];
                $to = $sequence[$i + 1];
                $fromIndex = array_search($from['stage'], $order, true);
                $toIndex = array_search($to['stage'], $order, true);

                // ⭐ فقط النقلة المتجاورة الصحيحة (from ⟵ to مباشرةً في الترتيب)
                if ($fromIndex !== false && $toIndex === $fromIndex + 1) {
                    $durationSeconds[$from['stage']] += $from['at']->diffInSeconds($to['at']);
                    $durationSamples[$from['stage']]++;
                }
            }
        }

        $startCount = $reached[$order[0]] ?? 0;

        $stages = [];
        foreach ($order as $key) {
            $stages[] = [
                'key' => $key,
                'label' => $labels[$key],
                'count' => $reached[$key],
                'pct_of_start' => $startCount > 0 ? round(($reached[$key] / $startCount) * 100, 1) : null,
            ];
        }

        $durations = [];
        for ($i = 0; $i < count($order) - 1; $i++) {
            $from = $order[$i];
            $to = $order[$i + 1];
            $sample = $durationSamples[$from];
            $avgSeconds = $sample > 0 ? $durationSeconds[$from] / $sample : null;

            $durations[] = [
                'from_key' => $from,
                'from_label' => $labels[$from],
                'to_key' => $to,
                'to_label' => $labels[$to],
                'avg_hours' => $avgSeconds !== null ? round($avgSeconds / 3600, 1) : null,
                'avg_days' => $avgSeconds !== null ? round($avgSeconds / 86400, 1) : null,
                'sample' => $sample,
            ];
        }

        return ['stages' => $stages, 'durations' => $durations, 'total' => $candidates->count()];
    }

    /**
     * رحلة مرشّح واحد كسلسلة (مرحلة ⟵ وقت الدخول) بدءًا من التقديم ثمّ كلّ
     * نقلة موثَّقة في `audit_logs` بترتيبها الزمنيّ.
     *
     * @return array<int, array{stage:string, at:Carbon}>
     */
    private function journeyOf(RecruitmentCandidate $candidate, Collection $logs): array
    {
        $start = $candidate->applied_at ?? $candidate->created_at;
        $sequence = [['stage' => 'applied', 'at' => $start]];

        foreach ($logs as $log) {
            $stage = $log->new_values['stage'] ?? null;

            if (is_string($stage) && $stage !== '') {
                $sequence[] = ['stage' => $stage, 'at' => $log->created_at];
            }
        }

        return $sequence;
    }

    /** صفوف CSV (BOM + هيدر + بيانات) — تُستهلَك من تصدير الأدمن ولوحة التطوّع معًا */
    public function toCsvRows(array $computed): array
    {
        $rows = [];

        $rows[] = [
            (string) setting('recruitment_funnel.export.col_stage', 'المرحلة'),
            (string) setting('recruitment_funnel.export.col_count', 'العدد'),
            (string) setting('recruitment_funnel.export.col_pct', 'نسبة من البداية %'),
            (string) setting('recruitment_funnel.export.col_avg_days', 'متوسّط الزمن حتّى المرحلة التالية (يوم)'),
            (string) setting('recruitment_funnel.export.col_sample', 'حجم العيّنة'),
        ];

        $durationsByFrom = collect($computed['durations'])->keyBy('from_key');

        foreach ($computed['stages'] as $stage) {
            $duration = $durationsByFrom->get($stage['key']);

            $rows[] = [
                $stage['label'],
                $stage['count'],
                $stage['pct_of_start'] !== null ? $stage['pct_of_start'] : '',
                $duration && $duration['avg_days'] !== null ? $duration['avg_days'] : '',
                $duration ? $duration['sample'] : '',
            ];
        }

        return $rows;
    }
}
