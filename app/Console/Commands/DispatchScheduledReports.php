<?php

namespace App\Console\Commands;

use App\Services\AdminScreens\ReportScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * إرسال التقارير المجدولة المستحقّة (24.3-خامسًا).
 *
 * يُشغَّل كلّ ساعة: يمرّ على الجدولات النشطة التي حلّ موعدها، يبني تقرير كلّ
 * واحدة ويرسله، ثمّ يكتب سطرًا في سجلّ الإرسال **مهما كانت النتيجة** ويحسب
 * الموعد التالي. والتقرير الذي يفشل لا يُسقِط الباقي — كلّ جدولة معزولة.
 */
class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch
                            {--dry-run : اعرض المستحقّ بلا إرسال}';

    protected $description = 'إرسال التقارير المجدولة المستحقّة وتنظيف سجلّ الإرسال القديم';

    public function handle(ReportScheduler $scheduler): int
    {
        $due = $scheduler->due(CarbonImmutable::now());

        if ($this->option('dry-run')) {
            $this->info('جدولات مستحقّة: '.$due->count());

            foreach ($due as $schedule) {
                $this->line(' • '.$schedule->name.' — التقرير: '.$schedule->report_tab.' · التكرار: '.$schedule->frequency);
            }

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $empty = 0;

        foreach ($due as $schedule) {
            $result = $scheduler->run($schedule);

            match ($result['result']) {
                'sent' => $sent++,
                'empty' => $empty++,
                default => $failed++,
            };
        }

        $pruned = $scheduler->pruneLog();

        $this->info('اتبعت: '.$sent.' · فاضية: '.$empty.' · فشلت: '.$failed.' · اتنضّف من السجلّ: '.$pruned.' سطر.');

        return self::SUCCESS;
    }
}
