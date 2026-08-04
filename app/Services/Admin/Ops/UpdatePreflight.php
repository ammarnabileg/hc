<?php

namespace App\Services\Admin\Ops;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * الفحوص القبليّة (2.11-ب): **تُوقِف التحديث إن فشلت**.
 *
 * القاعدة هنا بسيطة وقاسية: لا نبدأ ترحيلًا نعرف سلفًا أنّه قد لا يكتمل. قرصٌ
 * ممتلئ يعني نسخةً احتياطيّةً مبتورة، ومجلّدٌ غير قابل للكتابة يعني نسخةً لا
 * تُكتب أصلًا — وفي الحالتين نكون قد بدأنا الترحيل بلا شبكة أمان.
 *
 * وكلّ عتبة **إعداد** لا رقم محروق (2.13)، لأنّ خادمًا صغيرًا وخادمًا كبيرًا
 * لا يتّفقان على معنى «مساحة كافية».
 */
class UpdatePreflight
{
    public function __construct(
        private readonly SchemaLedger $ledger,
        private readonly UpdateLock $lock,
    ) {}

    /**
     * كلّ فحص: مفتاح · عنوان · قيمة · حالة (ok/danger) · ماذا تفعل لو فشل (2.17).
     *
     * @param  array<int, string>  $migrationPaths
     * @return array<int, array{key:string,label:string,value:string,state:string,hint:string}>
     */
    public function checks(array $migrationPaths = [], ?string $ownToken = null): array
    {
        return [
            $this->php(),
            $this->extensions(),
            $this->writable(),
            $this->disk(),
            $this->database(),
            $this->integrity($migrationPaths),
            $this->lockFree($ownToken),
        ];
    }

    /** فحصٌ واحد فاشل يكفي لإيقاف كلّ شيء — ولا «تحذير» هنا بل مرور أو منع */
    public function passes(array $checks): bool
    {
        return ! in_array('danger', array_column($checks, 'state'), true);
    }

    /** @return array<int, string> */
    public function failures(array $checks): array
    {
        return array_map(
            fn (array $row) => $row['label'].': '.$row['value'].' — '.$row['hint'],
            array_values(array_filter($checks, fn (array $row) => $row['state'] === 'danger')),
        );
    }

    // ------------------------------------------------------------------ الفحوص

    private function php(): array
    {
        $min = (string) setting('updates.preflight.min_php', '8.2');
        $ok = version_compare(PHP_VERSION, $min, '>=');

        return $this->row('php', setting('updates.update_preflight.php_1', 'إصدار PHP'), PHP_VERSION, $ok,
            strtr(setting('updates.update_preflight.php_2', 'المطلوب :p1 أو أحدث — كلّم مزوّد الاستضافة يرفّعه قبل التحديث.'), [':p1' => (string) ($min)]));
    }

    private function extensions(): array
    {
        $required = setting('updates.preflight.required_extensions', ['pdo', 'json', 'zip']);
        $required = is_array($required) ? $required : [];
        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded((string) $ext)));

        return $this->row('extensions', setting('updates.update_preflight.extensions_1', 'امتدادات PHP'),
            $missing === [] ? strtr(setting('updates.update_preflight.extensions_2', ':p1 امتداد موجود'), [':p1' => (string) (count($required))]) : strtr(setting('updates.update_preflight.extensions_3', 'ناقص: :p1'), [':p1' => (string) (implode(' · ', $missing))]),
            $missing === [],
            setting('updates.update_preflight.extensions_4', 'نصّب الامتدادات الناقصة من الخادم — بدونها النسخة الاحتياطيّة نفسها مش هتتعمل.'));
    }

    private function writable(): array
    {
        $paths = setting('updates.preflight.writable_paths', ['storage/app', 'storage/logs', 'storage/framework', 'bootstrap/cache']);
        $paths = is_array($paths) ? $paths : [];
        $paths[] = 'storage/'.trim((string) setting('backups.path', 'backups'), '/');

        $blocked = [];

        foreach (array_unique($paths) as $relative) {
            $full = base_path((string) $relative);

            if (! is_dir($full)) {
                @mkdir($full, 0755, true);
            }

            if (! is_dir($full) || ! is_writable($full)) {
                $blocked[] = (string) $relative;
            }
        }

        return $this->row('writable', setting('updates.update_preflight.writable_1', 'صلاحيّات الكتابة'),
            $blocked === [] ? strtr(setting('updates.update_preflight.writable_2', ':p1 مجلّد مفتوح'), [':p1' => (string) (count($paths))]) : strtr(setting('updates.update_preflight.writable_3', 'مقفول: :p1'), [':p1' => (string) (implode(' · ', $blocked))]),
            $blocked === [],
            setting('updates.update_preflight.writable_4', 'اضبط ملكيّة المجلّدات دي للمستخدم اللي بيشغّل الخادم، وإلا النسخة الاحتياطيّة مش هتتكتب.'));
    }

    /** ⭐ مساحة القرص: لا نبدأ ترحيلًا بلا مكان تسع فيه النسخة الاحتياطيّة */
    private function disk(): array
    {
        $free = @disk_free_space(base_path());
        $minMb = max(0, (int) setting('updates.preflight.min_free_mb', 512));
        $factor = max(1.0, (float) setting('updates.preflight.backup_size_factor', 3));
        $databaseBytes = $this->databaseBytes();
        $needed = max($minMb * 1024 * 1024, (int) round($databaseBytes * $factor));

        if ($free === false) {
            return $this->row('disk', setting('updates.update_preflight.disk_1', 'مساحة القرص'), setting('updates.update_preflight.disk_2', 'غير متاحة للقراءة'), false,
                strtr(setting('updates.update_preflight.disk_3', 'الخادم مش بيسمح بقراءة المساحة — اتأكّد يدويًّا إنّ فيه :p1 فاضية قبل ما تكمّل.'), [':p1' => (string) ($this->mb($needed))]));
        }

        return $this->row('disk', setting('updates.update_preflight.disk_4', 'مساحة القرص'),
            strtr(setting('updates.update_preflight.disk_5', 'فاضي :p1 · المطلوب :p2'), [':p1' => (string) ($this->mb((int) $free)), ':p2' => (string) ($this->mb($needed))]),
            $free >= $needed,
            setting('updates.update_preflight.disk_6', 'فضّي مساحة أو امسح نسخًا قديمة — النسخة الاحتياطيّة المبتورة أسوأ من غيابها.'));
    }

    private function database(): array
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            return $this->row('database', setting('updates.update_preflight.database_1', 'الاتّصال بقاعدة البيانات'), setting('updates.update_preflight.database_2', 'مش متّصلة'), false,
                setting('updates.update_preflight.database_3', 'راجع بيانات الاتّصال في ملفّ البيئة قبل أيّ محاولة تانية.'));
        }

        return $this->row('database', setting('updates.update_preflight.database_4', 'الاتّصال بقاعدة البيانات'), DB::getDriverName(), true, setting('updates.update_preflight.database_5', 'الاتّصال سليم.'));
    }

    /** سلامة الإصدار الحاليّ: هل ملفّات الهجرات المطبَّقة هي نفسها التي طُبِّقت؟ */
    private function integrity(array $paths): array
    {
        if (! setting('updates.preflight.verify_checksums', true)) {
            return $this->row('integrity', setting('updates.update_preflight.integrity_1', 'سلامة الإصدار الحاليّ'), setting('updates.update_preflight.integrity_2', 'الفحص متوقّف من الإعدادات'), true, setting('updates.update_preflight.integrity_3', 'فعّله من بلوك الإعدادات.'));
        }

        $bad = $this->ledger->mismatches($paths);

        return $this->row('integrity', setting('updates.update_preflight.integrity_4', 'سلامة الإصدار الحاليّ'),
            $bad === [] ? strtr(setting('updates.update_preflight.integrity_5', ':p1 هجرة ببصمة مطابقة'), [':p1' => (string) ($this->ledger->appliedCount())]) : strtr(setting('updates.update_preflight.integrity_6', ':p1 هجرة ملفّها اتغيّر بعد تطبيقها'), [':p1' => (string) (count($bad))]),
            $bad === [],
            $bad === [] ? setting('updates.update_preflight.integrity_7', 'كلّ هجرة زيّ ما اتطبّقت بالظبط.') : strtr(setting('updates.update_preflight.integrity_8', 'ملفّ اتعدّل بعد تطبيقه (:p1) — رجّع الملفّ لأصله أو اعمل هجرة جديدة بدل تعديل القديمة.'), [':p1' => (string) (implode(' · ', array_slice($bad, 0, 3)))]));
    }

    /**
     * ⚠️ التشغيل الجاري يمسك القفل بنفسه قبل الفحوص (وهو الترتيب الصحيح: لا نفحص
     * ثمّ نقفل فيتسلّل تشغيلٌ بين الاثنين) — فيمرّر توكنه هنا كي لا يرى نفسه عائقًا.
     */
    private function lockFree(?string $ownToken = null): array
    {
        $lock = $this->lock->current();

        if ($lock && $ownToken !== null && (string) $lock->token === $ownToken) {
            $lock = null;
        }

        return $this->row('lock', setting('updates.update_preflight.lock_free_1', 'قفل التحديث'),
            $lock ? setting('updates.update_preflight.lock_free_2', 'مقفول من ').($lock->holder_name ?? setting('updates.update_preflight.lock_free_3', 'تشغيل تاني')) : setting('updates.update_preflight.lock_free_4', 'مفتوح'),
            $lock === null,
            setting('updates.update_preflight.lock_free_5', 'في تحديث شغّال دلوقتي — استنّاه يخلص، والقفل بيتحرّر لوحده بعد المهلة المضبوطة في الإعدادات.'));
    }

    // ------------------------------------------------------------------ داخليّ

    private function databaseBytes(): int
    {
        try {
            $name = DB::connection()->getDatabaseName();
        } catch (Throwable) {
            return 0;
        }

        return is_string($name) && is_file($name) ? (int) filesize($name) : 0;
    }

    private function mb(int $bytes): string
    {
        return strtr(setting('updates.update_preflight.mb_1', ':p1 م.ب'), [':p1' => (string) (number_format($bytes / 1048576, 1))]);
    }

    private function row(string $key, string $label, string $value, bool $ok, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'state' => $ok ? 'ok' : 'danger',
            'hint' => $hint,
        ];
    }
}
