<?php

namespace App\Services\Setup;

/**
 * كتابة ملفّ ‎.env‎ من الويب (2.2 — بلا تيرمينال).
 * مكتوب بـPHP خام بلا أيّ اعتماد على حاويّة Laravel، لأنّه يُستدعى أيضًا من
 * ‎public/setup.php‎ **قبل** أن يكون التطبيق جاهزًا للإقلاع.
 */
class EnvWriter
{
    public function __construct(private readonly SetupPaths $paths) {}

    public function exists(): bool
    {
        return is_file($this->paths->env());
    }

    /** ينشئ ‎.env‎ من ‎.env.example‎ عند أوّل تشغيل — وإلّا بحدّ أدنى صالح للإقلاع */
    public function ensureExists(): bool
    {
        if ($this->exists()) {
            return true;
        }

        if (is_file($this->paths->envExample())) {
            return @copy($this->paths->envExample(), $this->paths->env());
        }

        return $this->save("APP_NAME=\"المنصّة\"\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\n");
    }

    /**
     * مفتاح التطبيق: بدونه لا يقلع أيّ طلب (الكوكيز مشفّرة)، فنضمن وجوده
     * قبل فتح المعالج، ونولّد غيره **جديدًا** عند الإنهاء.
     */
    public function generateAppKey(): string
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $this->put(['APP_KEY' => $key]);

        return $key;
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $contents = (string) @file_get_contents($this->paths->env());

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return null;
        }

        return trim(trim(trim($matches[1]), '"'), "'");
    }

    /** تعديل/إضافة مفاتيح مع الحفاظ على باقي الملفّ كما هو */
    public function put(array $values): bool
    {
        $this->ensureExists();

        $contents = (string) @file_get_contents($this->paths->env());

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace('$', '\\$', $line), $contents, 1)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        return $this->save($contents);
    }

    /** القيم التي فيها مسافة أو رمز خاصّ تُحاط بعلامتَي اقتباس وإلّا يكسر الملفّ */
    private function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|"|#|\$|\'/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"';
        }

        return $value;
    }

    /** كتابة ذرّيّة: ملفّ مؤقّت ثمّ استبدال — فلا ينكتب ‎.env‎ ناقصًا أبدًا */
    private function save(string $contents): bool
    {
        $temporary = $this->paths->env().'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($temporary, $contents) === false) {
            return false;
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $this->paths->env())) {
            @unlink($temporary);

            return false;
        }

        return true;
    }
}
