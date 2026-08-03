<?php

namespace App\Console\Commands;

use App\Services\Events\ReminderScheduler;
use Illuminate\Console\Command;

/**
 * `php artisan events:remind` — **تذكيرات الفعاليّات المجدولة** (13.3 · 12.11).
 *
 * الدستور ينصّ حرفيًّا: «**تذكيرات مجدولة** (قبل يوم/ساعة) عبر الإشعارات/Toast/
 * بريد (2.8)» — وكان الوعد قائمًا في الفورم وفي بلوك الإعدادات **بلا أمرٍ
 * يلتقطه ولا جدولةٍ تشغّله**. والتأجيل بلا مُلتقِطٍ إسقاطٌ صامت.
 *
 * وتشغيله متكرّرًا آمن: `event_reminders` بفهرسه الفريد يضمن ألّا يصل
 * المستلِمَ نفسَه التذكيرُ نفسُه مرّتين.
 *
 * ملاحظة تشغيليّة: **الجدولة في `routes/console.php`** (ملفّ مشترك) — كلّ خمس
 * دقائق، لأنّ «قبل ساعة» بمسحةٍ كلّ ساعة قد تصل متأخّرةً ساعةً كاملة فتفقد
 * معناها. والقرارُ داخل `ReminderScheduler` وحدها، فمواعيدُ التذكير إعدادٌ
 * يحرّره الأدمن لا تعبير كرون يتجمّد على القيمة القديمة.
 */
class SendEventReminders extends Command
{
    protected $signature = 'events:remind';

    protected $description = 'إرسال تذكيرات الفعاليّات المستحقّة للمسجّلين (13.3) بلا تكرار على نفس المستلِم';

    public function handle(ReminderScheduler $scheduler): int
    {
        $result = $scheduler->dispatchDue();
        $notices = $scheduler->dispatchNotices();

        $this->info(
            'فعاليّات مستحقّة: '.$result['events'].
            ' · اتبعت: '.$result['sent'].
            ' · متخطّاة (وصلت قبل كده): '.$result['skipped'].
            ' · تعثّرت: '.$result['failed'].
            ' · إشعارات مجدولة اتبعتت: '.$notices['notices'].
            ' (لـ'.$notices['sent'].' مستلِم).',
        );

        return self::SUCCESS;
    }
}
