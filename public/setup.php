<?php

use App\Services\Setup\EnvWriter;
use App\Services\Setup\SetupPaths;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/*
|--------------------------------------------------------------------------
| نقطة بداية التنصيب — domain-name.com/setup.php (الدستور 2.2)
|--------------------------------------------------------------------------
| هذا الملفّ يشتغل **قبل** أن يكون التطبيق جاهزًا، فهو بـPHP خام بلا حاويّة
| ولا واجهات Laravel: يتأكّد إنّ الأساسيّات موجودة، يقلع Laravel، ثمّ يحوّل
| للمعالج على /setup. ولو التنصيب تمّ (وجود storage/installed.lock) يرفض
| العمل ويردّ 404 — فلا نعترف أصلًا بوجود معالج على الخادم.
*/

define('SETUP_ENTRY_START', microtime(true));

$base = dirname(__DIR__);

/** صفحة ردّ مستقلّة بالعربيّة: بلا Blade وبلا أيّ مكتبة خارجيّة */
function setup_entry_page(int $status, string $title, string $message, string $hint = ''): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');

    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $hint = $hint === '' ? '' : '<p style="margin:12px 0 0;font-size:14px;color:#94a3b8">'.htmlspecialchars($hint, ENT_QUOTES, 'UTF-8').'</p>';

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{$title}</title>
    </head>
    <body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#e2e8f0;font-family:system-ui,'Segoe UI',Tahoma,sans-serif">
    <main style="max-width:520px;padding:28px;margin:16px;border:1px solid #1e293b;border-radius:18px;background:#0f172a">
    <h1 style="margin:0 0 10px;font-size:20px">{$title}</h1>
    <p style="margin:0;font-size:15px;line-height:1.9;color:#cbd5e1">{$message}</p>
    {$hint}
    </main>
    </body>
    </html>
    HTML;

    exit;
}

// ------------------------------------------------ 1) التنصيب تمّ؟ الباب مقفول
if (is_file($base.'/storage/installed.lock')) {
    setup_entry_page(
        404,
        'المنصّة متنصّبة خلاص',
        'صفحة التنصيب اتقفلت بعد ما التنصيب تمّ بنجاح — وده مقصود عشان أمان المنصّة.',
        'لو محتاج تنصيب جديد، احذف ملفّ storage/installed.lock من مدير الملفّات وارجع افتح الصفحة دي.',
    );
}

// ------------------------------------------------ 2) ملفّات المشروع كاملة؟
if (! is_file($base.'/vendor/autoload.php')) {
    setup_entry_page(
        500,
        'ملفّات المنصّة ناقصة',
        'مجلّد vendor مش موجود، ومن غيره المنصّة ماتقدرش تشتغل. ارفع المشروع كاملًا بمجلّد vendor زيّ ما هو من النسخة الجاهزة.',
        'لو رفعت المشروع مضغوطًا، اتأكّد إنّ فكّ الضغط خلص من غير أخطاء.',
    );
}

require $base.'/vendor/autoload.php';

// ------------------------------------------------ 3) بيئة صالحة للإقلاع
$paths = new SetupPaths($base);
$env = new EnvWriter($paths);

if (! $env->ensureExists()) {
    setup_entry_page(
        500,
        'مش قادرين نكتب ملفّ الإعدادات',
        'المنصّة محتاجة تنشئ ملفّ ‎.env‎ في مجلّد المشروع، والصلاحيّات الحاليّة مش سامحة.',
        'من مدير الملفّات في الاستضافة، اضبط صلاحيّة مجلّد المشروع على 775 وارجع حدّث الصفحة.',
    );
}

// مفتاح التطبيق شرط لإقلاع أيّ طلب (الكوكيز مشفّرة)؛ ويُولَّد غيره عند الإنهاء
if (! $env->get('APP_KEY') && ! $env->generateAppKey()) {
    setup_entry_page(
        500,
        'مش قادرين نجهّز مفتاح التطبيق',
        'مقدرناش نكتب مفتاح التشفير في ملفّ ‎.env‎.',
        'اضبط صلاحيّة الكتابة على الملفّ ‎.env‎ (644) ومجلّد المشروع (775) وارجع حدّث الصفحة.',
    );
}

// ------------------------------------------------ 4) إقلاع Laravel
try {
    /** @var Application $app */
    $app = require_once $base.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
} catch (Throwable $exception) {
    setup_entry_page(
        500,
        'المنصّة مش قادرة تقلع',
        'حصل خطأ وإحنا بنجهّز المنصّة قبل ما نبدأ التنصيب.',
        (bool) ($_ENV['APP_DEBUG'] ?? false) === true
            ? 'رسالة الخادم: '.$exception->getMessage()
            : 'راجع إصدار PHP وصلاحيّات مجلّد storage، ولو استمرّت المشكلة كلّم الدعم الفنّيّ للاستضافة.',
    );
}

// ------------------------------------------------ 5) للمعالج
$directory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/setup.php'))), '/');

header('X-Robots-Tag: noindex, nofollow');
header('Location: '.$directory.'/setup', true, 302);

exit;
