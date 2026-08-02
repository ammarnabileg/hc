<?php

namespace App\Services\Setup;

/**
 * فحص المتطلّبات (2.2): إصدار PHP · الامتدادات · صلاحيّات الكتابة.
 * كلّ نتيجة معها **رمز مع اللون** (2.16-ب) وسطر يقول «اعمل إيه» (2.17-ب)،
 * والناقص الإلزاميّ يوقف المتابعة.
 */
class RequirementsChecker
{
    public function __construct(private readonly SetupPaths $paths) {}

    /** @return array<string, list<array{label:string,value:string,ok:bool,required:bool,fix:string}>> */
    public function groups(): array
    {
        return [
            'php' => $this->php(),
            'extensions' => $this->extensions(),
            'permissions' => $this->permissions(),
        ];
    }

    public function passed(): bool
    {
        foreach ($this->groups() as $checks) {
            foreach ($checks as $check) {
                if ($check['required'] && ! $check['ok']) {
                    return false;
                }
            }
        }

        return true;
    }

    /** الناقص الإلزاميّ بالاسم — لنقوله في رسالة الخطأ بدل «فيه حاجة ناقصة» */
    public function missing(): array
    {
        $missing = [];

        foreach ($this->groups() as $checks) {
            foreach ($checks as $check) {
                if ($check['required'] && ! $check['ok']) {
                    $missing[] = $check['label'];
                }
            }
        }

        return $missing;
    }

    private function php(): array
    {
        $minimum = SetupSettings::text('setup.requirements.php_version', '8.3');

        return [[
            'label' => 'إصدار PHP '.$minimum.' فأعلى',
            'value' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, $minimum, '>='),
            'required' => true,
            'fix' => 'غيّر إصدار PHP من لوحة الاستضافة إلى '.$minimum.' أو أحدث، وارجع حدّث الصفحة.',
        ]];
    }

    private function extensions(): array
    {
        $required = SetupSettings::list('setup.requirements.extensions', [
            'pdo', 'mbstring', 'gd', 'zip', 'intl', 'openssl',
        ]);

        $optional = SetupSettings::list('setup.requirements.optional_extensions', [
            'curl', 'fileinfo', 'exif',
        ]);

        $checks = [];

        foreach ($required as $extension) {
            $checks[] = $this->extension($extension, true);
        }

        foreach ($optional as $extension) {
            $checks[] = $this->extension($extension, false);
        }

        return $checks;
    }

    private function extension(string $extension, bool $required): array
    {
        $loaded = extension_loaded($extension);

        return [
            'label' => 'امتداد '.$extension,
            'value' => $loaded ? 'مثبَّت' : 'مش موجود',
            'ok' => $loaded,
            'required' => $required,
            'fix' => $required
                ? 'فعّل الامتداد '.$extension.' من إعدادات PHP في الاستضافة، أو اطلبه من الدعم الفنّيّ.'
                : 'اختياريّ — المنصّة تشتغل بدونه، لكن تفعيله يحسّن أداء بعض الميزات.',
        ];
    }

    private function permissions(): array
    {
        $paths = SetupSettings::list('setup.requirements.writable_paths', [
            'storage', 'bootstrap/cache',
        ]);

        $checks = [];

        foreach ($paths as $path) {
            $absolute = $this->paths->root().'/'.trim($path, '/');
            $exists = is_dir($absolute);
            $writable = $exists && is_writable($absolute);

            $checks[] = [
                'label' => 'الكتابة على '.$path,
                'value' => match (true) {
                    ! $exists => 'المجلّد مش موجود',
                    $writable => 'مسموحة',
                    default => 'ممنوعة',
                },
                'ok' => $writable,
                'required' => true,
                'fix' => $exists
                    ? 'من مدير الملفّات في الاستضافة، اضبط صلاحيّة المجلّد '.$path.' على 775 (أو 755 لو الخادم بنفس المستخدم).'
                    : 'أنشئ المجلّد '.$path.' داخل مجلّد المشروع، واضبط صلاحيّته على 775.',
            ];
        }

        return $checks;
    }
}
