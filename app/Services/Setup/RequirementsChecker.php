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
            'label' => strtr(setting('setup.requirements_checker.php_1', 'إصدار PHP :p1 فأعلى'), [':p1' => (string) ($minimum)]),
            'value' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, $minimum, '>='),
            'required' => true,
            'fix' => strtr(setting('setup.requirements_checker.php_2', 'غيّر إصدار PHP من لوحة الاستضافة إلى :p1 أو أحدث، وارجع حدّث الصفحة.'), [':p1' => (string) ($minimum)]),
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
            'label' => strtr(setting('setup.requirements_checker.extension_1', 'امتداد :p1'), [':p1' => (string) ($extension)]),
            'value' => $loaded ? setting('setup.requirements_checker.extension_2', 'مثبَّت') : setting('setup.requirements_checker.extension_3', 'مش موجود'),
            'ok' => $loaded,
            'required' => $required,
            'fix' => $required
                ? strtr(setting('setup.requirements_checker.extension_4', 'فعّل الامتداد :p1 من إعدادات PHP في الاستضافة، أو اطلبه من الدعم الفنّيّ.'), [':p1' => (string) ($extension)])
                : setting('setup.requirements_checker.extension_5', 'اختياريّ — المنصّة تشتغل بدونه، لكن تفعيله يحسّن أداء بعض الميزات.'),
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
                'label' => strtr(setting('setup.requirements_checker.permissions_1', 'الكتابة على :p1'), [':p1' => (string) ($path)]),
                'value' => match (true) {
                    ! $exists => setting('setup.requirements_checker.permissions_2', 'المجلّد مش موجود'),
                    $writable => setting('setup.requirements_checker.permissions_3', 'مسموحة'),
                    default => setting('setup.requirements_checker.permissions_4', 'ممنوعة'),
                },
                'ok' => $writable,
                'required' => true,
                'fix' => $exists
                    ? strtr(setting('setup.requirements_checker.permissions_5', 'من مدير الملفّات في الاستضافة، اضبط صلاحيّة المجلّد :p1 على 775 (أو 755 لو الخادم بنفس المستخدم).'), [':p1' => (string) ($path)])
                    : strtr(setting('setup.requirements_checker.permissions_6', 'أنشئ المجلّد :p1 داخل مجلّد المشروع، واضبط صلاحيّته على 775.'), [':p1' => (string) ($path)]),
            ];
        }

        return $checks;
    }
}
