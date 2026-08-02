<?php

namespace App\Services\Setup;

/**
 * قراءة إعدادات المعالج بالقاعدة الذهبيّة (2.13): لا رقم ولا نصّ محروق.
 * الفارق الوحيد عن ‎setting()‎ أنّ المعالج يعمل **قبل** وجود قاعدة البيانات،
 * فأيّ فشل في القراءة يرجع للقيمة الافتراضيّة بدل أن يكسر الشاشة.
 */
final class SetupSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return setting($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function text(string $key, string $default): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    public static function number(string $key, int|float $default): int|float
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? $value + 0 : $default;
    }

    public static function flag(string $key, bool $default): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    /**
     * قائمة (امتدادات · مسارات) مخزّنة كـJSON أو مفصولة بفواصل.
     *
     * @param  list<string>  $default
     * @return list<string>
     */
    public static function list(string $key, array $default): array
    {
        $value = self::get($key, $default);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', trim($value));
        }

        if (! is_array($value) || $value === []) {
            return $default;
        }

        return array_values(array_filter(array_map('strval', $value), fn ($item) => $item !== ''));
    }
}
