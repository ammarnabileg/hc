<?php

namespace App\Console\Commands;

use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Objections\ObjectionService;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Tasks\NoDeliverySweeper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * تشغيل محرّك التصعيد على النوافذ الفائتة (الدستور 23 — القسم 5).
 *
 * لماذا أمر مستقلّ؟ لأنّ كلّ ما في دورة العمل نوافذ زمنيّة، والزمن لا يمرّ
 * بضغطة مستخدم. فبلا تشغيل دوريّ تظلّ الحالات معلّقة على مكاتب أصحابها إلى
 * الأبد، ولا يقع اعتماد تلقائيّ ولا تسوية آليّة ولا خصم فوات تفتيش.
 *
 * وهو **مجدول كلّ خمس دقائق** في `routes/console.php` — ولذلك لا يجوز أن يسقط
 * كلّه بسبب صفٍّ واحد: كلّ خطوة معزولة، وما يسقط يُسجَّل ويُعزَل ويكمل الباقي.
 */
class RunEscalationEngine extends Command
{
    protected $signature = 'escalations:run
                            {--dry : عرض ما سيحدث بلا تنفيذ}';

    protected $description = 'معالجة النوافذ الفائتة: تصعيد · تسويات آليّة · اعتماد تلقائيّ · فوات نقاط التفتيش';

    public function handle(EscalationEngine $engine, ContributionService $contributions): int
    {
        if ($this->option('dry')) {
            $this->line('وضع المعاينة — لا تنفيذ.');

            return self::SUCCESS;
        }

        /*
         | ⭐ كلّ خطوة في قفصها: خطوةٌ تسقط لا تُسقِط ما بعدها. الأمر مجدول كلّ
         | خمس دقائق على المنصّة بأسرها، فاستثناءٌ واحد غير ملتقَط كان يعني
         | توقّف التسويات والاعتمادات والخصومات **لكلّ متطوّع** إلى الأبد.
         */
        $engineResult = $this->step('محرّك التصعيد', fn () => $engine->run(), ['escalated' => 0, 'settled' => 0, 'failed' => 0]);
        $autoApproved = $this->step('الاعتماد التلقائيّ للمساهم', fn () => $contributions->runAutoApprovals(), 0);
        $missedCheckpoints = $this->step('نقاط التفتيش الفائتة', fn () => $contributions->runMissedCheckpoints(), 0);
        $missedDeadlines = $this->step('الديدلاينات الداخليّة الفائتة', fn () => $contributions->runMissedInternalDeadlines(), 0);
        $missedTasks = $this->step('مسار عدم التسليم', fn () => app(NoDeliverySweeper::class)->run(), ['no_delivery' => 0, 'merge_window' => 0]);
        $objections = $this->step('الاعتراضات الفائتة', fn () => app(ObjectionService::class)->runOverdue(), ['escalated' => 0, 'at_top' => 0]);
        $thawed = $this->step('فكّ تجميد الغياب', fn () => app(AbsenceService::class)->thawFinished(), 0);

        $this->table(['ما تمّ', 'العدد'], [
            ['حالات صعدت للأبلاين', $engineResult['escalated']],
            ['تسويات آليّة عند السقف', $engineResult['settled']],
            ['حالات معزولة تحتاج مراجعة', $engineResult['failed']],
            ['بنود اعتُمدت تلقائيًّا', $autoApproved],
            ['نقاط تفتيش فائتة', $missedCheckpoints],
            ['ديدلاينات داخليّة فائتة', $missedDeadlines],
            ['مهامّ دخلت مسار عدم التسليم', $missedTasks['no_delivery']],
            ['نوافذ دمج فائتة', $missedTasks['merge_window']],
            ['اعتراضات صعدت', $objections['escalated']],
            ['غيابات فُكّ تجميدها', $thawed],
            ['خطوات سقطت', $this->failures],
        ]);

        return self::SUCCESS;
    }

    /** عدد الخطوات التي سقطت في هذه الدورة — تُعرَض ولا تُخفى */
    private int $failures = 0;

    /**
     * تنفيذ خطوة معزولة: تسقط ⟵ تُسجَّل وتُعرَض ويكمل الباقي.
     *
     * @template TValue
     *
     * @param  callable():TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    private function step(string $label, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->failures++;
            report($exception);
            Log::error('دورة العمل: سقطت خطوة ولم تُسقِط الباقي', [
                'step' => $label,
                'message' => $exception->getMessage(),
            ]);
            $this->components->error($label.' سقطت: '.$exception->getMessage());

            return $fallback;
        }
    }
}
