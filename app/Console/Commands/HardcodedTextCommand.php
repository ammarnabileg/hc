<?php

namespace App\Console\Commands;

use App\Services\Admin\System\HardcodedTextScanner;
use Illuminate\Console\Command;

/**
 * `php artisan settings:hardcoded` — **النصّ المحروق**، الوجه الذي لا تراه
 * `settings:coverage`.
 *
 * لماذا أمرٌ ثانٍ؟ لأنّ الأوّل يقيس **المفاتيح التي يقرؤها الكود**، والنصُّ
 * المحروق **لا يمرّ بـ`setting()` أصلًا** فلا يدخل المقياس. فمئةٌ بالمئة هناك
 * تقول «كلّ مفتاحٍ مقروءٍ له صفّ»، ولا تقول شيئًا عن `<h1>أهلًا بعودتك</h1>`.
 * والاكتفاء بها هو **حارسٌ يمرّ دائمًا**: يمنح ثقةً كاذبة ويوقف البحث.
 *
 * **العتبة (Ratchet):** لا نطلب إصلاح آلاف المواضع اليوم — نطلب **ألّا يزيد
 * واحد**. الملفّ `docs/hardcoded-text-baseline.json` يحمل العدد لكلّ ملفّ:
 * - زيادةٌ في أيّ ملفّ (أو ملفٌّ جديد) ⟵ **كود 1**، ويُسمّى الملفّ وسطرُه.
 * - نقصانٌ ⟵ **كود 1** كذلك، برسالةٍ أخرى: «أحكِم العتبة». والعتبة المرتخية
 *   ثغرةٌ تسمح بعودة ما أُصلِح، فالإحكام جزءٌ من الإصلاح لا زينةٌ بعده.
 * - ورفعُ العتبة **لا يقع بالسهو**: `--update-baseline` يرفض أيّ زيادة إلّا
 *   بـ`--accept-increase` صريحة، فالتراجع قرارٌ مكتوبٌ لا ضغطةُ زرّ.
 */
class HardcodedTextCommand extends Command
{
    private const BASELINE = 'docs/hardcoded-text-baseline.json';

    protected $signature = 'settings:hardcoded
                            {--list : اعرض كلّ المواضع (ملفّ · سطر · نصّ)}
                            {--file= : اقصر العرض على ملفّاتٍ يطابق مسارُها هذا النصّ}
                            {--folders : اعرض التوزيع على المجلّدات}
                            {--limit=30 : كم سطرًا يُعرَض في كلّ قائمة (0 = الكلّ)}
                            {--update-baseline : اكتب العتبة من القياس الحاليّ}
                            {--accept-increase : اسمح للعتبة بالارتفاع — قرارٌ صريح لا سهو}
                            {--json : أخرِج القياس كـJSON بلا زخرفة}';

    protected $description = 'فحص النصّ العربيّ المحروق في القوالب والأصناف — الوجه الثاني للقاعدة 2.13';

    public function handle(HardcodedTextScanner $scanner): int
    {
        $current = $scanner->countsByFile();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'total' => $scanner->total(),
                'files' => $current,
                'kinds' => $scanner->countsByKind(),
                'not_measured' => $scanner->exclusionNotes(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->header($scanner);

        if ($this->option('folders')) {
            $this->folders($scanner);
        }

        if ($this->option('list') || $this->option('file')) {
            $this->listFindings($scanner);
        }

        $this->scope($scanner);

        if ($this->option('update-baseline')) {
            return $this->writeBaseline($scanner, $current);
        }

        return $this->compare($scanner, $current);
    }

    // =================================================================== العرض

    private function header(HardcodedTextScanner $scanner): void
    {
        $this->newLine();
        $this->line('<options=bold>النصّ العربيّ المحروق</> — '.
            '<fg=yellow>'.$scanner->total().'</> موضعًا في '.count($scanner->countsByFile()).' ملفًّا');
        $this->line('<fg=gray>المسح: '.implode(' · ', HardcodedTextScanner::ROOTS).
            '  —  الممتثل: نصٌّ داخل '.implode('() · ', array_slice(HardcodedTextScanner::COMPLIANT_CALLS, 0, 4)).'()</>');
        $this->newLine();

        $rows = [];

        foreach ($scanner->countsByKind() as $kind => $count) {
            $rows[] = [$this->kindLabel($kind), $count];
        }

        $this->table(['الموضع', 'العدد'], $rows);
    }

    private function folders(HardcodedTextScanner $scanner): void
    {
        $limit = (int) $this->option('limit');
        $counts = $scanner->countsByFolder();
        $rows = [];

        foreach ($counts as $folder => $count) {
            $rows[] = [$folder, $count];
        }

        $this->line('<options=bold>التوزيع على المجلّدات</>');
        $this->table(['المجلّد', 'العدد'], $limit > 0 ? array_slice($rows, 0, $limit) : $rows);

        if ($limit > 0 && count($rows) > $limit) {
            $this->line('  <fg=gray>… و'.(count($rows) - $limit).' مجلّدًا غيرها (`--limit=0`).</>');
        }

        $this->newLine();
    }

    private function listFindings(HardcodedTextScanner $scanner): void
    {
        $filter = (string) $this->option('file');
        $limit = (int) $this->option('limit');

        $findings = array_values(array_filter(
            $scanner->findings(),
            fn (array $f) => $filter === '' || str_contains($f['file'], $filter),
        ));

        $shown = $limit > 0 ? array_slice($findings, 0, $limit) : $findings;

        foreach ($shown as $finding) {
            $this->line("  <fg=cyan>{$finding['file']}</>:<fg=yellow>{$finding['line']}</>  ".
                "<fg=gray>[{$this->kindLabel($finding['kind'])}]</>  {$finding['text']}");
        }

        if (count($findings) > count($shown)) {
            $this->line('  <fg=gray>… و'.(count($findings) - count($shown)).' موضعًا غيرها (`--limit=0`).</>');
        }

        $this->newLine();
    }

    /**
     * **ما لا يقيسه الفحص** — يُطبَع في كلّ تشغيل بلا خيارٍ يخفيه.
     * الحارس الذي لا يعلن حدوده يُقرَأ رقمُه على أنّه تغطيةٌ كاملة، فيصير الرقمُ
     * نفسه هو الكذبة.
     */
    private function scope(HardcodedTextScanner $scanner): void
    {
        $this->line('<options=bold;fg=yellow>⚠ ما لا يقيسه هذا الفحص</> — اقرأه قبل أن تقرأ الرقم:');

        foreach ($scanner->exclusionNotes() as $note) {
            $this->line("  <fg=gray>•</> {$note}");
        }

        $this->newLine();
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'text' => 'نصّ في القالب',
            'attribute' => 'سمة وسم',
            'attribute-expr' => 'سمة مربوطة',
            'blade-expr' => 'تعبير Blade',
            'directive' => 'وسيط توجيه',
            'script' => 'سكربت داخل القالب',
            'php' => 'نصّ في صنف PHP',
            default => $kind,
        };
    }

    // ================================================================== العتبة

    /**
     * @param  array<string, int>  $current
     */
    private function compare(HardcodedTextScanner $scanner, array $current): int
    {
        $baseline = $this->readBaseline();

        if ($baseline === null) {
            $this->error('مافيش عتبة في `'.self::BASELINE.'` — وفحصٌ بلا عتبة لا يمنع شيئًا.');
            $this->line('اكتبها: <options=bold>php artisan settings:hardcoded --update-baseline</>');

            return self::FAILURE;
        }

        $grew = [];
        $shrank = [];

        foreach ($current as $file => $count) {
            $allowed = $baseline[$file] ?? 0;

            if ($count > $allowed) {
                $grew[$file] = [$allowed, $count];
            } elseif ($count < $allowed) {
                $shrank[$file] = [$allowed, $count];
            }
        }

        foreach ($baseline as $file => $allowed) {
            if (! isset($current[$file]) && $allowed > 0) {
                $shrank[$file] = [$allowed, 0];
            }
        }

        $this->line('<options=bold>العتبة</> — '.array_sum($baseline).' موضعًا مسموحًا بها (مقيسة في '.
            ($this->baselineDate() ?? '—').') · الآن '.$scanner->total());
        $this->newLine();

        if ($grew !== []) {
            $this->error('نصٌّ محروق **جديد** — مخالفة 2.13 ('.count($grew).' ملفًّا):');

            foreach ($grew as $file => [$was, $now]) {
                $this->line('  <fg=red>+'.($now - $was)."</>  <fg=cyan>{$file}</>  <fg=gray>({$was} ⟵ {$now})</>");

                // العتبة تحفظ العدد لا الأسطر، فلا نعرف **أيّ** موضعٍ هو الجديد —
                // نعرض مواضع الملفّ كلّها ونصدق في ذلك بدل ادّعاء دقّةٍ لا نملكها.
                $limit = (int) $this->option('limit');
                $places = array_values(array_filter($scanner->findings(), fn (array $f) => $f['file'] === $file));
                $shown = $limit > 0 ? array_slice($places, 0, $limit) : $places;

                foreach ($shown as $finding) {
                    $this->line("        <fg=yellow>{$finding['line']}</>  {$finding['text']}");
                }

                if (count($places) > count($shown)) {
                    $this->line('        <fg=gray>… و'.(count($places) - count($shown)).
                        ' موضعًا في نفس الملفّ (`--limit=0`) — والعتبة تحفظ العدد لا الأسطر.</>');
                }
            }

            $this->newLine();
            $this->line('الإصلاح: انقل النصّ إلى إعدادٍ يقرؤه القالب — '.
                "<options=bold>{{ setting('المجال.الميزة.المفتاح', 'النصّ الحاليّ') }}</> — ".
                'وعرّف المفتاح في ميثود `settings()` بسيدر مجالك (BUILD.md §3).');
            $this->newLine();

            return self::FAILURE;
        }

        if ($shrank !== []) {
            $this->warn('نقصانٌ محمود في '.count($shrank).' ملفًّا — لكنّ العتبة لسّه مرتخية، وهي ثغرةٌ تسمح بعودة ما أُصلِح:');

            foreach (array_slice($shrank, 0, 20, true) as $file => [$was, $now]) {
                $this->line('  <fg=green>-'.($was - $now)."</>  <fg=cyan>{$file}</>  <fg=gray>({$was} ⟵ {$now})</>");
            }

            $this->newLine();
            $this->line('أحكِمها: <options=bold>php artisan settings:hardcoded --update-baseline</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('مافيش نصٌّ محروق جديد ✓ — والعتبة محكَمة على القياس الحاليّ.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $current
     */
    private function writeBaseline(HardcodedTextScanner $scanner, array $current): int
    {
        $baseline = $this->readBaseline() ?? [];
        $grew = [];

        foreach ($current as $file => $count) {
            if ($count > ($baseline[$file] ?? 0)) {
                $grew[$file] = [$baseline[$file] ?? 0, $count];
            }
        }

        if ($grew !== [] && $baseline !== [] && ! $this->option('accept-increase')) {
            $this->error('العتبة تعلو في '.count($grew).' ملفًّا — والعلوّ لا يقع بالسهو:');

            foreach ($grew as $file => [$was, $now]) {
                $this->line('  <fg=red>+'.($now - $was)."</>  <fg=cyan>{$file}</>  <fg=gray>({$was} ⟵ {$now})</>");
            }

            $this->newLine();
            $this->line('لو كان قرارًا: أعِدها بـ<options=bold>--accept-increase</> واكتب سببه في `_STATUS.md` مجلّدك.');

            return self::FAILURE;
        }

        $payload = [
            'ما هذا الملفّ' => 'عتبة النصّ المحروق (2.13). العدد لكلّ ملفّ — لا يُسمَح بزيادته. يكتبه `php artisan settings:hardcoded --update-baseline`.',
            'ما لا يقيسه الفحص' => $scanner->exclusionNotes(),
            'قيس_في' => now()->toDateString(),
            'الإجماليّ' => $scanner->total(),
            'الملفّات' => $current,
        ];

        file_put_contents(
            base_path(self::BASELINE),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL,
        );

        $this->info('اتكتبت العتبة: '.$scanner->total().' موضعًا في '.count($current).' ملفًّا ⟵ '.self::BASELINE);

        return self::SUCCESS;
    }

    /** @return array<string, int>|null */
    private function readBaseline(): ?array
    {
        $path = base_path(self::BASELINE);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data['الملفّات'] ?? null) ? $data['الملفّات'] : null;
    }

    private function baselineDate(): ?string
    {
        $path = base_path(self::BASELINE);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_string($data['قيس_في'] ?? null) ? $data['قيس_في'] : null;
    }
}
