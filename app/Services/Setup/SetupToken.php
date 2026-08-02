<?php

namespace App\Services\Setup;

use Illuminate\Support\Str;

/**
 * توكن التنصيب (2.2 — أمان): الخادم عامّ ولحظة الرفع مكشوفة،
 * فيُولَّد توكن عند أوّل فتح ويُطلَب إدخاله قبل أيّ خطوة — فلا يسبقك أحد للتنصيب.
 */
class SetupToken
{
    public function __construct(private readonly SetupPaths $paths) {}

    /** يولّد التوكن عند أوّل فتح، ويرجع null لو مقدرش يكتبه (storage مقفول) */
    public function ensure(): ?string
    {
        if ($existing = $this->current()) {
            return $existing;
        }

        $length = (int) SetupSettings::number('setup.token.length', 32);
        $token = Str::lower(Str::random(max(16, $length)));

        if (! is_dir(dirname($this->paths->token()))) {
            return null;
        }

        return @file_put_contents($this->paths->token(), $token.PHP_EOL) === false ? null : $token;
    }

    public function current(): ?string
    {
        if (! is_file($this->paths->token())) {
            return null;
        }

        $token = trim((string) @file_get_contents($this->paths->token()));

        return $token === '' ? null : $token;
    }

    public function matches(string $candidate): bool
    {
        $token = $this->current();

        return $token !== null && hash_equals($token, trim($candidate));
    }

    /** بعد الإنهاء: التوكن ما بقاش له لزوم فلا نتركه على الخادم */
    public function forget(): void
    {
        if (is_file($this->paths->token())) {
            @unlink($this->paths->token());
        }
    }

    public function file(): string
    {
        return $this->paths->relative($this->paths->token());
    }
}
