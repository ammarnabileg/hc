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
                            {--force : افحص الآن بلا انتظار موعد الفحص الدوريّ}
                            {--dump= : اكتب حمولة اللقطة الناتجة في ملفٍّ داخل الحزمة (نسخة التنصيب)}';

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

        // الجلب أوّلًا وحده: مَن سيكتب نسخة التنصيب يحتاج الحمولة **قبل** أن
        // تُحذَف لقطةُ «صفر فروق» — ولا يجلب مرّتين لأجل ذلك (7 ميجابايت لا 14)
        $check = $sync->fetch(null, $force ? 'manual' : 'schedule');

        // ⛔ الفشل يُقال ولا يُبتلَع — ولا يُطبَع «تمّ الفحص بنجاح» عند أيّ حالة منه
        if (! $check->succeeded()) {
            $this->error('فشل فحص المصدر ('.$check->failure.'): '.$check->message);
            $this->line('واللقطة الأخيرة الناجحة زيّ ما هي — ما اتلمستش.');

            return self::FAILURE;
        }

        /*
         | ⭐ `--dump` — **نسخة التنصيب تُولَّد من المصدر بالآليّة، لا تُكتَب بيد**.
         |
         | لماذا نحتاجها أصلًا؟ لأنّ 2.5-ج يشترط «كلّ دول العالم ومحافظاتها
         | كاملة» في **قوائم التسجيل نفسها**، بينما `countries:check-source`
         | يحتاج شبكةً وقرارَ مالك — وكلاهما غير متاح لحظة `db:seed` على تنصيبٍ
         | جديد. فالنسخة المجلوبة تُثبَّت ملفًّا في الحزمة، ويزرعها
         | `CountriesSeeder` **بنفس المسار** (`import ⟵ diff ⟵ merge ⟵ تحقّق`)
         | بلا شبكة. والتحديث بعد ذلك يبقى قرار المالك من الشاشة كما هو.
         |
         | ويُكتَب **قبل** فحص الفروق: النسخة سليمة سواءٌ طابقت بياناتنا أم لا،
         | و«صفر فروق» يحذف اللقطة فلا تبقى حمولةٌ تُكتَب بعده.
         */
        if ($path = trim((string) $this->option('dump'))) {
            $bytes = $this->dump($check->snapshot->payload, $path);

            $this->line('اتكتبت نسخة التنصيب في '.$path.' ('.number_format($bytes / 1024).' KB).');
        }

        $check = $sync->summarise($check);

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

    /**
     * كتابة الحمولة ملفَّ JSON داخل الحزمة — ومعها **إسناد ODbL** في الملفّ نفسه.
     *
     * الرخصة ODbL v1.0 تشترط الإسناد على كلّ نسخةٍ تُوزَّع، وهذا الملفّ نسخة
     * تُوزَّع مع الكود — فالإسناد جزءٌ منه لا وثيقةٌ بجواره تُنسى.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dump(array $payload, string $path): int
    {
        $path = str_starts_with($path, '/') ? $path : base_path($path);

        @mkdir(dirname($path), 0755, true);

        $json = json_encode([
            'source' => (string) setting('countries.source', 'dr5hn'),
            'source_url' => (string) setting('countries.source_url', ''),
            'license' => 'ODbL v1.0',
            'attribution' => (string) setting('countries.attribution', ''),
            'fetched_at' => now()->toIso8601String(),
            'countries' => array_values((array) ($payload['countries'] ?? [])),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        file_put_contents($path, $json);

        return strlen((string) $json);
    }
}
