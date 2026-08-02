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
    public function checks(array $migrationPaths = []): array
    {
        return [
            $this->php(),
            $this->extensions(),
            $this->writable(),
            $this->disk(),
            $this->database(),
            $this->integrity($migrationPaths),
            $this->lockFree(),
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

        return $this->row('php', 'إصدار PHP', PHP_VERSION, $ok,
            "المطلوب {$min} أو أحدث — كلّم مزوّد الاستضافة يرفّعه قبل التحديث.");
    }

    private function extensions(): array
    {
        $required = setting('updates.preflight.required_extensions', ['pdo', 'json', 'zip']);
        $required = is_array($required) ? $required : [];
        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded((string) $ext)));

        return $this->row('extensions', 'امتدادات PHP',
            $missing === [] ? count($required).' امتداد موجود' : 'ناقص: '.implode(' · ', $missing),
            $missing === [],
            'نصّب الامتدادات الناقصة من الخادم — بدونها النسخة الاحتياطيّة نفسها مش هتتعمل.');
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

        return $this->row('writable', 'صلاحيّات الكتابة',
            $blocked === [] ? count($paths).' مجلّد مفتوح' : 'مقفول: '.implode(' · ', $blocked),
            $blocked === [],
            'اضبط ملكيّة المجلّدات دي للمستخدم اللي بيشغّل الخادم، وإلا النسخة الاحتياطيّة مش هتتكتب.');
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
            return $this->row('disk', 'مساحة القرص', 'غير متاحة للقراءة', false,
                'الخادم مش بيسمح بقراءة المساحة — اتأكّد يدويًّا إنّ فيه '.$this->mb($needed).' فاضية قبل ما تكمّل.');
        }

        return $this->row('disk', 'مساحة القرص',
            'فاضي '.$this->mb((int) $free).' · المطلوب '.$this->mb($needed),
            $free >= $needed,
            'فضّي مساحة أو امسح نسخًا قديمة — النسخة الاحتياطيّة المبتورة أسوأ من غيابها.');
    }

    private function database(): array
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            return $this->row('database', 'الاتّصال بقاعدة البيانات', 'مش متّصلة', false,
                'راجع بيانات الاتّصال في ملفّ البيئة قبل أيّ محاولة تانية.');
        }

        return $this->row('database', 'الاتّصال بقاعدة البيانات', DB::getDriverName(), true, 'الاتّصال سليم.');
    }

    /** سلامة الإصدار الحاليّ: هل ملفّات الهجرات المطبَّقة هي نفسها التي طُبِّقت؟ */
    private function integrity(array $paths): array
    {
        if (! setting('updates.preflight.verify_checksums', true)) {
            return $this->row('integrity', 'سلامة الإصدار الحاليّ', 'الفحص متوقّف من الإعدادات', true, 'فعّله من بلوك الإعدادات.');
        }

        $bad = $this->ledger->mismatches($paths);

        return $this->row('integrity', 'سلامة الإصدار الحاليّ',
            $bad === [] ? $this->ledger->appliedCount().' هجرة ببصمة مطابقة' : count($bad).' هجرة ملفّها اتغيّر بعد تطبيقها',
            $bad === [],
            $bad === [] ? 'كلّ هجرة زيّ ما اتطبّقت بالظبط.' : 'ملفّ اتعدّل بعد تطبيقه ('.implode(' · ', array_slice($bad, 0, 3)).') — رجّع الملفّ لأصله أو اعمل هجرة جديدة بدل تعديل القديمة.');
    }

    private function lockFree(): array
    {
        $lock = $this->lock->current();

        return $this->row('lock', 'قفل التحديث',
            $lock ? 'مقفول من '.($lock->holder_name ?? 'تشغيل تاني') : 'مفتوح',
            $lock === null,
            'في تحديث شغّال دلوقتي — استنّاه يخلص، والقفل بيتحرّر لوحده بعد المهلة المضبوطة في الإعدادات.');
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
        return number_format($bytes / 1048576, 1).' م.ب';
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
