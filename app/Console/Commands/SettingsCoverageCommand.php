<?php

namespace App\Console\Commands;

use App\Services\Admin\System\SettingsCoverage;
use Illuminate\Console\Command;

/**
 * `php artisan settings:coverage` — هل يقدر مالك المنصّة على تعديل كلّ إعداد؟
 *
 * فحصان لا فحصٌ واحد:
 *
 * **1) المفاتيح** — كلّ مفتاح يقرؤه الكود (`setting('…')` في `app/` و`resources/`
 * و`routes/`) له صفٌّ بعد `DatabaseSeeder`؟ المفتاح بلا صفّ يأخذ الافتراضيّ
 * المكتوب في الكود، وهو **رقمٌ محروق بخطوة إضافيّة** — عين ما تمنعه 2.13-ب.
 *
 * **2) المجموعات** — كلّ مجموعة مزروعة لها تابٌ في اللوحة؟ إعدادٌ في القاعدة بلا
 * شاشة محروقٌ كذلك (2.13-و).
 *
 * وكان الفحص الأوّل غائبًا فكان الأمر يطبع 100% دائمًا: المجموعة لا توجد إلّا لو
 * زُرِعت، والمزروع له تابه — سؤالٌ يمرّ مهما اتّسعت الفجوة. **وحارسٌ يمرّ دائمًا
 * أسوأ من غياب حارس**، لأنّه يمنح ثقةً كاذبة ويوقف البحث.
 */
class SettingsCoverageCommand extends Command
{
    protected $signature = 'settings:coverage
                            {--all : اعرض كلّ المجموعات لا اليتيمة وحدها}
                            {--prefixes : اعرض البادئات المتناثرة على أكثر من مجموعة}
                            {--limit=40 : كم مفتاحًا ناقصًا يُطبَع (0 = الكلّ)}
                            {--where : اعرض أوّل موضع يقرأ كلّ مفتاح ناقص}';

    protected $description = 'فحص تغطية الإعدادات: كلّ مفتاح يقرؤه الكود له صفّ، وكلّ مجموعة لها شاشة (2.13)';

    public function handle(SettingsCoverage $coverage): int
    {
        $total = $coverage->totalKeys();

        if ($total === 0) {
            $this->warn('مافيش إعدادات في قاعدة البيانات — شغّل `php artisan db:seed` الأوّل.');

            return self::FAILURE;
        }

        $failed = $this->reportKeys($coverage);
        $failed = $this->reportGroups($coverage) || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** الفحص الأوّل: مفاتيح يقرؤها الكود ولا صفّ لها */
    private function reportKeys(SettingsCoverage $coverage): bool
    {
        $read = $coverage->readKeyCount();
        $missing = $coverage->missingKeys();
        $scanner = $coverage->scanner();

        $this->newLine();
        $this->line('<options=bold>1) المفاتيح</> — '."{$coverage->keyCoveragePercent()}%  ".
            '(<fg=green>'.($read - $missing->count())."</> من {$read} مفتاحًا يقرؤها الكود لها صفّ · ".
            $coverage->totalKeys().' صفًّا مزروعًا)');

        $this->reportDynamic($coverage);

        if ($missing->isEmpty()) {
            $this->info('كلّ مفتاح يقرؤه الكود له صفٌّ يعدّله المالك ✓');
            $this->newLine();

            return false;
        }

        $limit = (int) $this->option('limit');
        $shown = $limit > 0 ? $missing->take($limit) : $missing;

        $this->error('مفاتيح يقرؤها الكود بلا صفّ — قيمٌ محروقة بخطوة إضافيّة ('.$missing->count().' مفتاحًا):');

        if ($this->option('where')) {
            $this->table(
                ['المفتاح', 'أوّل موضع يقرؤه'],
                $shown->map(fn (array $places, string $key) => [$key, $places[0] ?? '—'])->values()->all(),
            );
        } else {
            foreach ($shown->keys()->chunk(3) as $chunk) {
                $this->line('  <fg=yellow>'.$chunk->implode('</>  ·  <fg=yellow>').'</>');
            }
        }

        if ($missing->count() > $shown->count()) {
            $this->line('  … و'.($missing->count() - $shown->count()).' غيرها (`--limit=0` يعرضها كلّها).');
        }

        $this->line('الإصلاح: عرّفها في <options=bold>ميثود `settings()` بسيدر مجالك</> — يستدعيها <options=bold>SettingDefinitionsSeeder</> في مسار الإنتاج.');
        $this->newLine();

        return true;
    }

    /** الأنماط المركَّبة وقتَ التشغيل — تُذكَر منفصلةً بدل أن تُعَدّ ناقصةً زورًا */
    private function reportDynamic(SettingsCoverage $coverage): void
    {
        $scanner = $coverage->scanner();
        $patterns = $scanner->dynamicPatterns();

        if ($patterns === [] && $scanner->variableCallSites() === 0) {
            return;
        }

        $unmatched = $scanner->unmatchedDynamicPatterns($coverage->seededKeys());

        $this->line('   <fg=gray>مفاتيح مركَّبة وقت التشغيل: '.count($patterns).' نمطًا · '.
            $scanner->variableCallSites().' موضعًا بمفتاح من متغيّر — تُفحَص بالنمط لا بالنصّ.</>');

        foreach ($patterns as $pattern => $places) {
            $ok = ! $unmatched->has($pattern);
            $this->line('   '.($ok ? '<fg=green>✓</>' : '<fg=red>✗</>')."  <fg=gray>{$pattern}</>".
                ($ok ? '' : '  <fg=red>— مافيش صفٌّ واحد يطابقه</>'));
        }

        $this->newLine();
    }

    /** الفحص الثاني: مجموعة مزروعة بلا تاب في اللوحة */
    private function reportGroups(SettingsCoverage $coverage): bool
    {
        $total = $coverage->totalKeys();
        $orphans = $coverage->orphanGroups();

        $this->line('<options=bold>2) المجموعات</> — '."{$coverage->coveragePercent()}%  ".
            '(<fg=green>'.($total - $coverage->orphanKeyCount())."</> من {$total} صفًّا له شاشة)");
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

            return false;
        }

        $this->error('مجموعات بلا شاشة — مخالفة 2.13 ('.$orphans->count().' مجموعة · '.$coverage->orphanKeyCount().' مفتاحًا):');
        $this->table(
            ['المجموعة', 'المفاتيح'],
            $orphans->map(fn (array $row) => [$row['group'], $row['count']])->all(),
        );
        $this->line('الإصلاح: أضِف المجموعة لتابها في <options=bold>SettingsRegistry::tabs()</> وعنوانها في <options=bold>groupCatalog()</>.');

        return true;
    }
}
