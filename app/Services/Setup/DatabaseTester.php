<?php

namespace App\Services\Setup;

use PDO;
use PDOException;

/**
 * اختبار الاتّصال بقاعدة البيانات **قبل** كتابة ‎.env‎ (2.2).
 * نجرّب بـPDO مباشرة بلا تحميل اتّصال Laravel، ونترجم خطأ السيرفر إلى
 * رسالة عربيّة تقول ماذا حدث وماذا تفعل (2.17-ب) — بلا أكواد تقنيّة جافّة.
 */
class DatabaseTester
{
    /** @return array{ok:bool,message:string} */
    public function test(array $credentials): array
    {
        $driver = SetupSettings::text('setup.database.driver', 'mysql');

        if (! extension_loaded('pdo_'.$driver)) {
            return [
                'ok' => false,
                'message' => 'امتداد pdo_'.$driver.' مش مفعَّل على الخادم، وبدونه مفيش اتّصال بقاعدة البيانات. فعّله من إعدادات PHP في الاستضافة وجرّب تاني.',
            ];
        }

        $timeout = (int) SetupSettings::number('setup.database.timeout_seconds', 5);

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s',
            $driver,
            $credentials['db_host'],
            (int) $credentials['db_port'],
            $credentials['db_database'],
        );

        try {
            new PDO($dsn, $credentials['db_username'], (string) ($credentials['db_password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => max(2, $timeout),
            ]);
        } catch (PDOException $exception) {
            return ['ok' => false, 'message' => $this->explain($exception, $credentials)];
        }

        return [
            'ok' => true,
            'message' => 'تمام — الاتّصال بقاعدة البيانات «'.$credentials['db_database'].'» نجح. تقدر تكمل.',
        ];
    }

    /** ترجمة خطأ الاتّصال إلى «ماذا حدث + ماذا تفعل» (2.17-ب) */
    private function explain(PDOException $exception, array $credentials): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, '1045') || str_contains($message, 'Access denied') => 'اسم المستخدم أو كلمة سرّ قاعدة البيانات مش مظبوطة. راجعهما من لوحة الاستضافة، وتأكّد إنّ المستخدم «'.$credentials['db_username'].'» مضاف لقاعدة البيانات «'.$credentials['db_database'].'» بكلّ الصلاحيّات.',
            str_contains($message, '1049') || str_contains($message, 'Unknown database') => 'قاعدة البيانات «'.$credentials['db_database'].'» مش موجودة. أنشئها من لوحة الاستضافة بنفس الاسم بالظبط، وارجع اضغط اختبار الاتّصال.',
            str_contains($message, '2002') || str_contains($message, 'Connection refused') || str_contains($message, 'refused') => 'مقدرناش نوصل لخادم قاعدة البيانات على «'.$credentials['db_host'].'». جرّب 127.0.0.1 أو المضيف اللي مكتوب في لوحة الاستضافة، وتأكّد من المنفذ.',
            str_contains($message, 'getaddrinfo') || str_contains($message, 'Unknown host') || str_contains($message, 'php_network_getaddresses') => 'اسم المضيف «'.$credentials['db_host'].'» مش معروف على الشبكة. انسخه من لوحة الاستضافة زيّ ما هو.',
            str_contains($message, 'timed out') || str_contains($message, '2006') => 'الاتّصال خد وقت طويل وانقطع. تأكّد إنّ الخادم شغّال وإنّ الجدار الناريّ سامح بالمنفذ '.$credentials['db_port'].'.',
            default => 'الاتّصال فشل ومقدرناش نحدّد السبب بدقّة. راجع بيانات الاتّصال في لوحة الاستضافة وجرّب تاني، ولو استمرّت المشكلة كلّم الدعم الفنّيّ للاستضافة.',
        };
    }
}
