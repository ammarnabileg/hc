<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Security\UserModeration;
use Illuminate\Console\Command;

/**
 * `php artisan moderation:release-expired` — **التعليق المؤقّت ينتهي وحده** (12.1-متقدّم-3).
 *
 * الرفع مضمونٌ في الجدار عند أوّل طلبٍ من صاحب الحساب، لكنّ الاعتماد على زيارته
 * وحدها يعني أنّ **قائمة الأدمن تفضل تقول «معلّق»** لحسابٍ انتهت مدّته ولم يفتح
 * المنصّة بعد — فيقرأ الفريق حالةً كاذبة ويتصرّف عليها. المسحة هنا تصحّح السجلّ
 * بلا انتظار، والجدار يبقى ضمانةَ العدل لو وقف الكرون.
 */
class ReleaseExpiredSuspensions extends Command
{
    protected $signature = 'moderation:release-expired';

    protected $description = 'رفع التعليقات المؤقّتة التي انقضت مدّتها (12.1)';

    public function handle(UserModeration $moderation): int
    {
        $released = 0;

        User::query()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->chunkById(100, function ($users) use ($moderation, &$released) {
                foreach ($users as $user) {
                    $released += $moderation->expireIfDue($user) ? 1 : 0;
                }
            });

        $this->info($released > 0
            ? "رجع {$released} حساب بعد انتهاء مدّة تعليقه ✓"
            : 'مافيش تعليق انتهت مدّته.');

        return self::SUCCESS;
    }
}
