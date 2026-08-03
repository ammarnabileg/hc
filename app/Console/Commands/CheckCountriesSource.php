<?php

namespace App\Console\Commands;

use App\Services\Admin\System\CountryDataSync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * الفحص الدوريّ لمصدر الدول (الدستور 12.7-د).
 *
 * ⛔ **يجلب، يبني الفروق، ويقف** — ولا يدمج حرفًا مهما كانت الفروق بريئة.
 * نصّ 12.7-د صريح: «فحص فروق النسخة الجديدة **قبل** الدمج»؛ فالدمج قرارُ
 * المالك من الشاشة بعد أن يرى المضاف والمحذوف والمعدَّل بعينه.
 *
 * والموعد **إعداد لا رقم محروق**: كلّ `countries.source.check.every_months`
 * شهرًا، يوم `…day_of_month` الساعة `…hour` بمنطقة `…timezone`. والجدولة مسحةٌ
 * كلّ ساعة والقرار داخل `isCheckDue()` وحدها — فتغيير الأدمن للدوريّة يسري فورًا
 * ولا يتجمّد في تعبير كرونٍ قُرِئ مرّةً عند تحميل `routes/console.php`.
 *
 * وصفرُ فروقٍ = **سكوت**: لا لقطة مكرّرة ولا إشعار.
 */
class CheckCountriesSource extends Command
{
    protected $signature = 'countries:check-source
                            {--force : افحص الآن بلا انتظار موعد الفحص الدوريّ}';

    protected $description = 'جلب نسخة مصدر الدول وبناء فروقها قبل الدمج — بلا أيّ دمج آليّ (12.7-د)';

    public function handle(CountryDataSync $sync): int
    {
        $tz = $sync->checkTimezone();
        $force = (bool) $this->option('force');

        // ⛔ الإيقاف يوقِف فعلًا — و`--force` لا يتخطّى قرار المالك بل موعده وحده
        if (! $sync->checkEnabled()) {
            $this->line('الفحص الدوريّ للمصدر متوقّف من الإعدادات (countries.source.check.enabled) — مفيش حاجة اتعملت.');

            return self::SUCCESS;
        }

        if (! $force && ! $sync->isCheckDue(CarbonImmutable::now($tz))) {
            $this->line('مش وقت الفحص دلوقتي — الموعد القادم: '.$sync->nextCheckAt()->format('Y-m-d H:i').' ('.$tz.').');

            return self::SUCCESS;
        }

        $check = $sync->checkSource(null, $force ? 'manual' : 'schedule');

        // ⛔ الفشل يُقال ولا يُبتلَع — ولا يُطبَع «تمّ الفحص بنجاح» عند أيّ حالة منه
        if (! $check->succeeded()) {
            $this->error('فشل فحص المصدر ('.$check->failure.'): '.$check->message);
            $this->line('واللقطة الأخيرة الناجحة زيّ ما هي — ما اتلمستش.');

            return self::FAILURE;
        }

        if ($check->differences() === 0) {
            // سكوت: لا لقطة مكرّرة ولا إشعار
            $this->info($check->message);

            return self::SUCCESS;
        }

        $sent = $sync->notifyImporters($check);

        $this->info($check->message);
        $this->line('الدمج **ما اتعملش** — القرار للمالك من تاب «بيانات الدول».');
        $this->line('اتبعت إشعار لـ'.$sent.' من أصحاب صلاحيّة الاستيراد.');

        return self::SUCCESS;
    }
}
