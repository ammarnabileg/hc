<?php

namespace App\Console\Commands;

use App\Services\Volunteer\Goals\RepService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * التصفير الشهريّ لدرجة الالتزام (الدستور 13.4-ن-ز · 23 — 1.8).
 *
 * الموعد **إعداد لا رقم محروق**: يوم `rep.reset.day_of_month`
 * الساعة `rep.reset.hour_cairo` بتوقيت القاهرة — للجميع بغضّ النظر عن معدّلهم.
 *
 * ⭐ الأثر المحدود بدقّة: **الرقم الظاهر وحده يعود صفرًا**، أمّا **سجلّ المعاملات**
 * و**المكتسَب التراكميّ** فيبقيان كاملَين — لأنّ الترقية تُقاس على التراكميّ لا على المسقوف.
 *
 * ولا يشمله تجميد المهل أثناء الصيانة (2.11) — يعمل في موعده ولا يُعلَن ذلك للمتطوّعين.
 */
class ResetMonthlyRep extends Command
{
    protected $signature = 'rep:reset-monthly
                            {--force : نفّذ الآن بلا انتظار موعد التصفير}
                            {--dry-run : اعرض ما سيحدث بلا تنفيذ}';

    protected $description = 'تصفير الرقم الظاهر لدرجة الالتزام شهريًّا مع الإبقاء على السجلّ والمكتسَب التراكميّ';

    public function handle(RepService $rep): int
    {
        $tz = (string) setting('system.timezone', 'Africa/Cairo');
        $now = CarbonImmutable::now($tz);

        if (! $this->option('force') && ! $rep->isResetMoment($now)) {
            $this->line('مش وقت التصفير دلوقتي — الموعد القادم: '.$rep->nextResetAt()->format('Y-m-d H:i').' ('.$tz.').');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('معاينة فقط: التصفير كان هيمسّ الرقم الظاهر وحده، والسجلّ والمكتسَب التراكميّ يفضلوا زيّ ما هم.');

            return self::SUCCESS;
        }

        $count = $rep->resetMonthly();

        $this->info('اتصفّر الرقم الظاهر لـ'.$count.' متطوّع — والسجلّ والمكتسَب التراكميّ كما هما.');
        $this->line('التصفير القادم: '.$rep->nextResetAt()->format('Y-m-d H:i').' ('.$tz.').');

        return self::SUCCESS;
    }
}
