<?php

namespace App\Console\Commands;

use App\Models\BehaviorTransaction;
use App\Services\Volunteer\Retention\BehaviorEscalation;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\InactivityLadder;
use App\Services\Volunteer\Retention\OptionalCutService;
use Illuminate\Console\Command;

/**
 * المسحة اليوميّة للاحتفاظ (13.4-س).
 *
 * ثلاث مسحات في أمرٍ واحد لأنّها ثلاث خطوات على **سلّمٍ واحد**:
 *  1) **سلّم الخمول:** 21 يومًا بلا نشاط ⟵ تنبيه واحد، ثمّ −0.5 أسبوعيًّا
 *     ما دام خاملًا — ويتوقّف فور عودة النشاط (13.4-س-ب).
 *  2) **عتبات اللجنة:** الرقم الظاهر ≤ −10، أو **المكتسَب التراكميّ خلال
 *     90 يومًا ≤ −15** ⟵ نفس مسار اللجنة (13.4-س-ج).
 *  3) **بتر الاختياريّ عند −9.5:** تُنهى عضويّاته في المحافظات والملفات،
 *     ويُحرَم من فتح عضويّة جديدة فيهما حتى التصفير الشهريّ (23-0.2-2 و3).
 *  4) **مزامنة معاملات السلوك المعلَّقة** مع محرّك التصعيد — احتياطًا لو
 *     تسوّت الحالة آليًّا بلا مرور على المحرّك (13.4-ن-هـ).
 */
class VolunteerInactivity extends Command
{
    protected $signature = 'volunteers:inactivity
                            {--dry-run : اعرض ما سيحدث بلا تنفيذ}';

    protected $description = 'سلّم الخمول (تنبيه ثمّ خصم أسبوعيّ) وعتبات لجنة التحقيق بما فيها المكتسَب التراكميّ';

    public function handle(InactivityLadder $ladder, OptionalCutService $cut, CommitteePath $committee, BehaviorEscalation $behavior): int
    {
        if ($this->option('dry-run')) {
            $this->line('معاينة فقط — التنبيه بعد '.$ladder->alertDays().' يومًا، والخصم الأسبوعيّ '
                .number_format($ladder->weeklyValue(), 2).', وبتر الاختياريّ عند '
                .number_format($cut->threshold(), 2).' على مسارات ('.implode(' · ', $cut->optionalTrackKeys())
                .'), وعتبة '.$committee->windowDays().' يومًا هي '
                .number_format($committee->cumulativeThreshold(), 2).'.');

            return self::SUCCESS;
        }

        $idle = $ladder->sweep();
        $this->info('الخمول: '.$idle['scanned'].' متطوّعًا · تنبيهات '.$idle['alerted']
            .' · خصومات '.$idle['deducted'].' · عادوا '.$idle['recovered'].'.');

        /*
         | ⭐ **الدرجة الوسطى قبل الأخيرة** — وترتيبها ليس شكليًّا: النصّ عند
         | −10 يفترض أنّ البتر وقع قبله («وبحكم البند 2، المعاملة الكاسرة لـ−10
         | تكون في القسم بالضرورة — **الاختياري انتهى قبلها**»). فلو سبقت مسحةُ
         | اللجنة مسحةَ البتر لَعُلِّق حسابٌ وله عضويّات اختياريّة قائمة — وهي
         | حالةٌ ينفيها الدستور صراحةً.
         */
        $cuts = $cut->sweep();
        $this->info('بتر الاختياريّ: '.$cuts['scanned'].' متطوّعًا · بترٌ جديد '.$cuts['cut']
            .' · رُفِع الحرمان عن '.$cuts['released'].'.');

        $referrals = $committee->sweep();
        $this->info('العتبات: '.$referrals['scanned'].' متطوّعًا · إحالات لجنة '.$referrals['referred'].'.');

        $synced = $this->syncBehaviorCases($behavior);
        $this->line('معاملات سلوك جسيمة اتسوّت: '.$synced.'.');

        return self::SUCCESS;
    }

    /** أيّ معاملة معلَّقة وحالتها على المحرّك محسومة ⟵ تُنفَّذ نتيجتها */
    private function syncBehaviorCases(BehaviorEscalation $behavior): int
    {
        $count = 0;

        BehaviorTransaction::query()
            ->where('status', 'pending_approval')
            ->whereNotNull('escalation_id')
            ->get()
            ->each(function (BehaviorTransaction $record) use ($behavior, &$count) {
                $case = $behavior->caseOf($record);

                if (! $case || $case->status === 'open') {
                    return;
                }

                $behavior->apply($record, (string) ($case->decision ?? 'rejected'), null);
                $count++;
            });

        return $count;
    }
}
