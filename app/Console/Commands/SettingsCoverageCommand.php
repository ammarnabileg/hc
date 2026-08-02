<?php

namespace App\Console\Commands;

use App\Services\Admin\System\SettingsCoverage;
use Illuminate\Console\Command;

/**
 * `php artisan settings:coverage` — هل يقدر مالك المنصّة على تعديل كلّ إعداد؟
 *
 * القاعدة 2.13 تقول: لا رقم ولا نصّ محروق، ولكلّ ميزة إعدادات **في لوحة الإدارة**.
 * إعدادٌ مزروع في قاعدة البيانات بلا شاشة هو نصٌّ محروق بخطوة إضافيّة — لذلك
 * يخرج هذا الأمر بكود 1 عند وجود مجموعة يتيمة، فيكسر الـCI بدل أن يمرّ بصمت.
 */
class SettingsCoverageCommand extends Command
{
    protected $signature = 'settings:coverage
                            {--all : اعرض كلّ المجموعات لا اليتيمة وحدها}
                            {--prefixes : اعرض البادئات المتناثرة على أكثر من مجموعة}';

    protected $description = 'فحص تغطية الإعدادات: كلّ مجموعة إعدادات ولها شاشة في لوحة الإدارة (2.13)';

    public function handle(SettingsCoverage $coverage): int
    {
        $total = $coverage->totalKeys();

        if ($total === 0) {
            $this->warn('مافيش إعدادات في قاعدة البيانات — شغّل `php artisan db:seed` الأوّل.');

            return self::SUCCESS;
        }

        $orphans = $coverage->orphanGroups();

        $this->newLine();
        $this->line("<options=bold>تغطية الإعدادات:</> {$coverage->coveragePercent()}%  ".
            "(<fg=green>".($total - $coverage->orphanKeyCount())."</> من {$total} مفتاحًا لها شاشة)");
        $this->newLine();

        if ($this->option('all')) {
            $this->table(
                ['المجموعة', 'المفاتيح', 'التاب', 'عنوان عربيّ'],
                $coverage->report()->map(fn (array $row) => [
                    $row['group'],
                    $row['count'],
                    $row['tab_label'] ?? '— بلا تاب —',
                    $row['labelled'] ? '✓' : '✗',
                ])->all(),
            );
        }

        if ($this->option('prefixes')) {
            $split = $coverage->splitPrefixes();

            if ($split->isEmpty()) {
                $this->info('مافيش بادئة موزَّعة على أكثر من مجموعة ✓');
            } else {
                $this->warn('بادئات موزَّعة على أكثر من مجموعة (وحّدها بمايجريشن ترحيل):');
                foreach ($split as $prefix => $groups) {
                    $this->line("  <fg=yellow>{$prefix}.*</> ⟵ ".implode(' · ', $groups));
                }
            }

            $this->newLine();
        }

        $unlabelled = $coverage->unlabelledGroups();

        if ($unlabelled->isNotEmpty()) {
            $this->warn('مجموعات بلا عنوان عربيّ في `SettingsRegistry::groupCatalog()`:');
            foreach ($unlabelled as $row) {
                $this->line("  <fg=yellow>{$row['group']}</> ({$row['count']} مفتاحًا)");
            }
            $this->newLine();
        }

        if ($orphans->isEmpty()) {
            $this->info('كلّ مجموعة إعدادات لها تابها في لوحة الإدارة ✓');

            return self::SUCCESS;
        }

        $this->error('مجموعات بلا شاشة — مخالفة 2.13 ('.$orphans->count().' مجموعة · '.$coverage->orphanKeyCount().' مفتاحًا):');
        $this->table(
            ['المجموعة', 'المفاتيح'],
            $orphans->map(fn (array $row) => [$row['group'], $row['count']])->all(),
        );
        $this->line('الإصلاح: أضِف المجموعة لتابها في <options=bold>SettingsRegistry::tabs()</> وعنوانها في <options=bold>groupCatalog()</>.');

        return self::FAILURE;
    }
}
