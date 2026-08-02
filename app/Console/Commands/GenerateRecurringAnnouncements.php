<?php

namespace App\Console\Commands;

use App\Services\Admin\Content\AnnouncementRecurrence;
use Illuminate\Console\Command;

/**
 * `php artisan announcements:recurring` — توليد دورات المنشورات المتكرّرة (12.6-أ).
 *
 * ملاحظة تشغيليّة: **بلا جدولة هنا** — الجدولة تُضاف في `routes/console.php`
 * (ملفّ مشترك خارج ملكيّة هذا المجال)، والمقترَح تشغيله كلّ ساعة.
 */
class GenerateRecurringAnnouncements extends Command
{
    protected $signature = 'announcements:recurring
                            {--dry-run : اعرض المستحقّ بلا توليد}';

    protected $description = 'توليد دورات المنشورات المجدولة تكرارًا (12.6-أ)';

    public function handle(AnnouncementRecurrence $recurrence): int
    {
        if ($this->option('dry-run')) {
            $due = $recurrence->due();

            $this->info('قوالب مستحقّة التوليد: '.$due->count());

            foreach ($due as $template) {
                $this->line(' • '.$template->title.' — تكرار: '.(AnnouncementRecurrence::frequencies()[$template->recurrence] ?? '—'));
            }

            return self::SUCCESS;
        }

        $result = $recurrence->run();

        $this->info('اتولّد: '.$result['generated'].' منشورًا · قوالب انتهى مداها: '.$result['ended'].'.');

        return self::SUCCESS;
    }
}
