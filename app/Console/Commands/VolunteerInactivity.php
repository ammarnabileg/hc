<?php

namespace App\Console\Commands;

use App\Models\BehaviorTransaction;
use App\Services\Volunteer\Retention\BehaviorEscalation;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\InactivityLadder;
use App\Services\Volunteer\Retention\OptionalCutService;
use App\Services\Volunteer\Retention\SuspensionService;
use App\Services\Volunteer\Retention\WarningRung;
use Illuminate\Console\Command;

/**
 * المسحة اليوميّة للاحتفاظ (13.4-س).
 *
 * مسحاتٌ في أمرٍ واحد لأنّها خطواتٌ على **سلّمٍ واحد**:
 *  1) **سلّم الخمول:** 21 يومًا بلا نشاط ⟵ تنبيه واحد، ثمّ −0.5 أسبوعيًّا
 *     ما دام خاملًا — ويتوقّف فور عودة النشاط (13.4-س-ب).
 *  2) **إنذار −8:** إشعار لكلّ أبلايناته النشطين، والتزام تواصل موثَّق خلال
 *     48 ساعة على أبلاين العضويّة الكاسرة — ومعه **ختم الإخلال** لمن فات
 *     ميعاده بلا توثيق (23-0.2-1).
 *  3) **بتر الاختياريّ عند −9.5:** تُنهى عضويّاته في المحافظات والملفات،
 *     ويُحرَم من فتح عضويّة جديدة فيهما حتى التصفير الشهريّ (23-0.2-2 و3).
 *  4) **تعليق −10:** تُعلَّق كلّ عضويّاته ولوحة التطوّع، وتُغطّى مسؤوليّاته
 *     الإشرافيّة بأبلاينه المباشر، وتُفتَح إحالة اللجنة (23-0.2-4).
 *  5) **الباب التراكميّ للجنة:** المكتسَب خلال 90 يومًا ≤ −15 ⟵ نفس مسار
 *     اللجنة ولو كان الظاهر صفرًا (13.4-س-ج).
 *  6) **مزامنة معاملات السلوك المعلَّقة** مع محرّك التصعيد — احتياطًا لو
 *     تسوّت الحالة آليًّا بلا مرور على المحرّك (13.4-ن-هـ).
 *
 * ⚠️ **والترتيب هنا هو ترتيب السلّم نفسه ولا يُبدَّل**: النصّ عند −10 يفترض أنّ
 * البتر وقع قبله («وبحكم البند 2 … الاختياري انتهى قبلها»)، والإنذار قبلهما
 * كليهما. فأيّ تبديلٍ في هذا الترتيب يخالف النصّ لا يخالف الذوق.
 */
class VolunteerInactivity extends Command
{
    protected $signature = 'volunteers:inactivity
                            {--dry-run : اعرض ما سيحدث بلا تنفيذ}';

    protected $description = 'سلّم الخمول (تنبيه ثمّ خصم أسبوعيّ) وعتبات لجنة التحقيق بما فيها المكتسَب التراكميّ';

    public function handle(
        InactivityLadder $ladder,
        WarningRung $warning,
        OptionalCutService $cut,
        SuspensionService $suspension,
        CommitteePath $committee,
        BehaviorEscalation $behavior,
    ): int {
        if ($this->option('dry-run')) {
            $this->line('معاينة فقط — التنبيه بعد '.$ladder->alertDays().' يومًا، والخصم الأسبوعيّ '
                .number_format($ladder->weeklyValue(), 2).', والإنذار عند '
                .number_format($warning->threshold(), 2).' بالتزام تواصل '.$warning->contactHours()
                .' ساعة، وبتر الاختياريّ عند '
                .number_format($cut->threshold(), 2).' على مسارات ('.implode(' · ', $cut->optionalTrackKeys())
                .'), والتعليق عند '.number_format($suspension->threshold(), 2)
                .', وعتبة '.$committee->windowDays().' يومًا هي '
                .number_format($committee->cumulativeThreshold(), 2).'.');

            return self::SUCCESS;
        }

        $idle = $ladder->sweep();
        $this->info('الخمول: '.$idle['scanned'].' متطوّعًا · تنبيهات '.$idle['alerted']
            .' · خصومات '.$idle['deducted'].' · عادوا '.$idle['recovered'].'.');

        // ⭐ الدرجة الأولى (23-0.2-1) — ومعها ختم مَن فات عليه التزام الـ48 ساعة
        $warnings = $warning->sweep();
        $this->info('إنذار العتبة الأولى: '.$warnings['scanned'].' متطوّعًا · إنذارات جديدة '
            .$warnings['warned'].' · التزامات تواصل فاتت '.$warnings['breached'].'.');

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

        /*
         | ⭐ **الدرجة الأخيرة بعد البتر مباشرةً** — واللجنة تولد من داخل التعليق
         | نفسه (`SuspensionService::apply()` ينادي `CommitteePath::refer()`)،
         | فلا يُفتَح ملفٌّ على حسابٍ لم يُعلَّق بعد.
         */
        $suspensions = $suspension->sweep();
        $this->info('تعليق العتبة الأخيرة: '.$suspensions['scanned'].' متطوّعًا · تعليقات جديدة '
            .$suspensions['suspended'].'.');

        // ويبقى الباب التراكميّ (−15 خلال 90 يومًا) وحده يمرّ من هنا — ومَن عُلِّق
        // له إحالةٌ مفتوحة أصلًا فلا تتكرّر.
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
