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
                'message' => strtr(setting('setup.database_tester.test_1', 'امتداد pdo_:p1 مش مفعَّل على الخادم، وبدونه مفيش اتّصال بقاعدة البيانات. فعّله من إعدادات PHP في الاستضافة وجرّب تاني.'), [':p1' => (string) ($driver)]),
            ];
        }

        $timeout = max(2, (int) SetupSettings::number('setup.database.timeout_seconds', 5));

        /*
         | ⭐ فحص وصول خامّ بمهلة مضمونة **قبل** PDO: `PDO::ATTR_TIMEOUT` لا يضبط
         | مرحلة الاتّصال الأولى (TCP connect) بثباتٍ على كلّ نظام/عميل — فجدارٌ
         | ناريّ يُسقط الحزم صامتًا (DROP لا REJECT) يترك PDO معلّقًا لمهلة
         | النظام الافتراضيّة (قد تتجاوز الدقيقة) فتعلَّق الصفحة كلّها بلا ردّ
         | ولا رسالة (2.17-ب: كلّ فشلٍ له سببٌ واضح — لا تعليقٌ صامت). و`fsockopen`
         | يحترم مهلته فعليًّا على مستوى نظام التشغيل، فيضمن سقفًا حقيقيًّا.
         */
        $probe = @fsockopen($credentials['db_host'], (int) $credentials['db_port'], $errno, $errstr, $timeout);

        if ($probe === false) {
            return ['ok' => false, 'message' => $this->explainProbeFailure($errno, $errstr, $credentials, $timeout)];
        }

        fclose($probe);

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
                PDO::ATTR_TIMEOUT => $timeout,
            ]);
        } catch (PDOException $exception) {
            return ['ok' => false, 'message' => $this->explain($exception, $credentials)];
        }

        return [
            'ok' => true,
            'message' => strtr(setting('setup.database_tester.test_2', 'تمام — الاتّصال بقاعدة البيانات «:p1» نجح. تقدر تكمل.'), [':p1' => (string) ($credentials['db_database'])]),
        ];
    }

    /**
     * ⭐ ترجمة فشل فحص الوصول الخامّ (2.17-ب) — قبل أن يصل الأمر لـPDO أصلًا.
     * نميّز «رفض صريح» (منفذٌ مقفول على خادمٍ يردّ فعلًا) عن «لا ردّ إطلاقًا»
     * (جدارٌ ناريّ يُسقط الحزم صامتًا) لأنّ العلاج مختلف تمامًا لكلٍّ منهما.
     */
    private function explainProbeFailure(int $errno, string $errstr, array $credentials, int $timeout): string
    {
        if ($errno === 111 || str_contains($errstr, 'refused')) {
            return strtr(setting('setup.database_tester.explain_3', 'مقدرناش نوصل لخادم قاعدة البيانات على «:p1». جرّب 127.0.0.1 أو المضيف اللي مكتوب في لوحة الاستضافة، وتأكّد من المنفذ.'), [':p1' => (string) ($credentials['db_host'])]);
        }

        if (str_contains($errstr, 'getaddrinfo') || str_contains($errstr, 'Unknown host') || str_contains($errstr, 'Name or service not known')) {
            return strtr(setting('setup.database_tester.explain_4', 'اسم المضيف «:p1» مش معروف على الشبكة. انسخه من لوحة الاستضافة زيّ ما هو.'), [':p1' => (string) ($credentials['db_host'])]);
        }

        // ⭐ لا رفضٌ صريح ولا خطأ DNS — الحزم اتسقطت صامتًا (جدارٌ ناريّ عادةً)
        return strtr(setting('setup.database_tester.unreachable_1', 'مقدرناش نوصل لخادم قاعدة البيانات على «:p1» على المنفذ :p2 خلال :p3 ثانية. تأكّد إنّ الخادم شغّال، وإنّ الجدار الناريّ سامح بالاتّصال من نفس السيرفر على هذا المنفذ.'), [':p1' => (string) ($credentials['db_host']), ':p2' => (string) ((int) $credentials['db_port']), ':p3' => (string) $timeout]);
    }

    /** ترجمة خطأ الاتّصال إلى «ماذا حدث + ماذا تفعل» (2.17-ب) */
    private function explain(PDOException $exception, array $credentials): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, '1045') || str_contains($message, 'Access denied') => strtr(setting('setup.database_tester.explain_1', 'اسم المستخدم أو كلمة سرّ قاعدة البيانات مش مظبوطة. راجعهما من لوحة الاستضافة، وتأكّد إنّ المستخدم «:p1» مضاف لقاعدة البيانات «:p2» بكلّ الصلاحيّات.'), [':p1' => (string) ($credentials['db_username']), ':p2' => (string) ($credentials['db_database'])]),
            str_contains($message, '1049') || str_contains($message, 'Unknown database') => strtr(setting('setup.database_tester.explain_2', 'قاعدة البيانات «:p1» مش موجودة. أنشئها من لوحة الاستضافة بنفس الاسم بالظبط، وارجع اضغط اختبار الاتّصال.'), [':p1' => (string) ($credentials['db_database'])]),
            str_contains($message, '2002') || str_contains($message, 'Connection refused') || str_contains($message, 'refused') => strtr(setting('setup.database_tester.explain_3', 'مقدرناش نوصل لخادم قاعدة البيانات على «:p1». جرّب 127.0.0.1 أو المضيف اللي مكتوب في لوحة الاستضافة، وتأكّد من المنفذ.'), [':p1' => (string) ($credentials['db_host'])]),
            str_contains($message, 'getaddrinfo') || str_contains($message, 'Unknown host') || str_contains($message, 'php_network_getaddresses') => strtr(setting('setup.database_tester.explain_4', 'اسم المضيف «:p1» مش معروف على الشبكة. انسخه من لوحة الاستضافة زيّ ما هو.'), [':p1' => (string) ($credentials['db_host'])]),
            str_contains($message, 'timed out') || str_contains($message, '2006') => strtr(setting('setup.database_tester.explain_5', 'الاتّصال خد وقت طويل وانقطع. تأكّد إنّ الخادم شغّال وإنّ الجدار الناريّ سامح بالمنفذ :p1.'), [':p1' => (string) ($credentials['db_port'])]),
            default => setting('setup.database_tester.explain_6', 'الاتّصال فشل ومقدرناش نحدّد السبب بدقّة. راجع بيانات الاتّصال في لوحة الاستضافة وجرّب تاني، ولو استمرّت المشكلة كلّم الدعم الفنّيّ للاستضافة.'),
        };
    }
}
