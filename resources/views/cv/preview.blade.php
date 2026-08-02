<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ setting('cv.page.title', 'سيرتي الذاتيّة') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet">
    <style>
        /* ورقة بالمقاس الحقيقيّ A4 — والمعاينة الحيّة تُعرَض بنفس المقاس (24.5) */
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9edec; font-family: 'Cairo', system-ui, sans-serif; }
        .sheet {
            inline-size: 210mm; min-block-size: 297mm; margin: 0 auto; background: #fff; color: #16241f;
            padding: 14mm 14mm 16mm; box-shadow: 0 2px 18px rgb(0 0 0 / .12);
        }
        @media print {
            html, body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; }
        }
        .sheet h1 { font-size: 22pt; margin: 0 0 2mm; }
        .sheet h2 { font-size: 11pt; margin: 7mm 0 2mm; letter-spacing: .02em; }
        .sheet p, .sheet li, .sheet td { font-size: 10pt; line-height: 1.7; margin: 0; }
        .muted { color: #56706a; }
        .row { display: flex; justify-content: space-between; gap: 4mm; }
        .chips { display: flex; flex-wrap: wrap; gap: 2mm; }
        .chip { border: 1px solid #d5e0dd; border-radius: 999px; padding: 1mm 3mm; font-size: 9pt; }
        .entry { margin-block-end: 4mm; }
        ul { padding-inline-start: 5mm; margin: 0; }
    </style>
</head>
<body>
    @include($sheet['view'], [
        'user' => $sheet['user'],
        'data' => $sheet['data'],
        'pulled' => $sheet['pulled'],
    ])

    @if ($print)
        <script>
            /* [تحميل PDF]: محرّك طباعة المتصفّح — بلا أيّ مكتبة تُنزَّل من الشبكة */
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
