<?php

namespace App\Console\Commands;

use App\Services\Notifications\AnnouncementMailer;
use Illuminate\Console\Command;

/**
 * `php artisan announcements:deliver-emails` — قناة البريد في «القنوات الموحّدة» (12.6-أ).
 *
 * يلتقط ثلاثة أشياء في كلّ تشغيلة: منشورًا حان وقته، ورسالةً **تأجّلت** بحدّ
 * الهدوء (12.6-ب) وحلّ موعدها، وتسليمًا **تعثّر** ولم يستنفد محاولاته.
 *
 * وتشغيله متكرّرًا آمن: صفوف `announcement_deliveries` بفهرسها الفريد تضمن ألّا
 * يصل المستخدمَ نفسَه المنشورُ نفسُه مرّتين.
 *
 * ملاحظة تشغيليّة: **بلا جدولة هنا** — الجدولة تُضاف في `routes/console.php`
 * (ملفّ مشترك خارج ملكيّة هذا المجال)، والمقترَح تشغيله كلّ ساعة.
 */
class DeliverAnnouncementEmails extends Command
{
    protected $signature = 'announcements:deliver-emails';

    protected $description = 'إرسال بريد منشورات التعليمات المستحقّة (12.6-أ) باحترام حدّ الهدوء (12.6-ب)';

    public function handle(AnnouncementMailer $mailer): int
    {
        $result = $mailer->dispatchDue();

        $this->info(
            'منشورات: '.$result['announcements'].
            ' · اتبعت: '.$result['sent'].
            ' · اتأجّلت: '.$result['deferred'].
            ' · مستبعَدون: '.$result['skipped'].
            ' · تعثّرت: '.$result['failed'].'.',
        );

        return self::SUCCESS;
    }
}
