<?php

namespace App\Console\Commands;

use App\Services\Volunteer\Goals\RecurringGenerator;
use Illuminate\Console\Command;

/**
 * توليد مهامّ البنود المتكرّرة من المشروع التشغيليّ (الدستور 23 — 1.8).
 *
 * يفعل شيئين في كلّ دورة:
 *  1) **يوسم الفائتة** ويدخلها مسار عدم التسليم — فلا تُقفَل بصمت أبدًا.
 *  2) **يولّد المستحقّ** ويوجّهه: فرد بعينه · تناوب موزون **بالموازن** (الأقلّ حملًا)
 *     · أو بلا مالك ليُسحَب من اللوحة العامّة.
 *
 * ملاحظة تشغيليّة: **بلا جدولة هنا** — الجدولة تُضاف في `routes/console.php`
 * (ملفّ مشترك خارج ملكيّة هذا المجال)، والمقترَح تشغيله كلّ ساعة.
 */
class GenerateRecurringItems extends Command
{
    protected $signature = 'recurring:generate
                            {--dry-run : اعرض المستحقّ بلا توليد}';

    protected $description = 'توليد مهامّ البنود المتكرّرة ووسم الفائتة منها';

    public function handle(RecurringGenerator $generator): int
    {
        if ($this->option('dry-run')) {
            $due = $generator->due();

            $this->info('بنود مستحقّة التوليد: '.$due->count());

            foreach ($due as $item) {
                $this->line(' • '.$item->name.' — تكرار: '.($item->recurrence ?? 'غير محدَّد').' · جمهور: '.($item->audience_mode ?? 'غير محدَّد'));
            }

            return self::SUCCESS;
        }

        $result = $generator->run();

        $this->info('اتولّد: '.$result['generated'].' مهمّة · فائتة موسومة: '.$result['missed'].' · متخطّاة (بلا جمهور): '.$result['skipped'].'.');

        return self::SUCCESS;
    }
}
