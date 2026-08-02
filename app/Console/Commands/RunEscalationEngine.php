<?php

namespace App\Console\Commands;

use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Console\Command;

/**
 * تشغيل محرّك التصعيد على النوافذ الفائتة (الدستور 23 — القسم 5).
 *
 * لماذا أمر مستقلّ؟ لأنّ كلّ ما في دورة العمل نوافذ زمنيّة، والزمن لا يمرّ
 * بضغطة مستخدم. فبلا تشغيل دوريّ تظلّ الحالات معلّقة على مكاتب أصحابها إلى
 * الأبد، ولا يقع اعتماد تلقائيّ ولا تسوية آليّة ولا خصم فوات تفتيش.
 *
 * ⚠️ يحتاج **جدولة** (كلّ دقيقة/خمس دقائق) — ولم نسجّله في `routes/console.php`
 * لأنّ الملفّ مشترك ولا يملكه هذا المجال.
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

        // (١) النوافذ الفائتة: ترفع للأبلاين مع أثر التباطؤ، أو تُسوّى آليًّا عند السقف
        $engineResult = $engine->run();

        // (٢) مهلة المالك ⟵ اعتماد تلقائيّ بنقاط المساهم كاملة
        $autoApproved = $contributions->runAutoApprovals();

        // (٣) نقاط التفتيش الفائتة داخل نافذة النشاط ⟵ خصم من جدول Rep
        $missedCheckpoints = $contributions->runMissedCheckpoints();

        // (٤) فوات الديدلاين الداخليّ بلا تسليم ⟵ خصم عدم تسليم المساهم
        $missedDeadlines = $contributions->runMissedInternalDeadlines();

        $this->table(['ما تمّ', 'العدد'], [
            ['حالات صعدت للأبلاين', $engineResult['escalated']],
            ['تسويات آليّة عند السقف', $engineResult['settled']],
            ['بنود اعتُمدت تلقائيًّا', $autoApproved],
            ['نقاط تفتيش فائتة', $missedCheckpoints],
            ['ديدلاينات داخليّة فائتة', $missedDeadlines],
        ]);

        return self::SUCCESS;
    }
}
