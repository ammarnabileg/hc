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

        /* ⭐ علامة مائيّة = لوجو المنصّة (أو اسمها) على المعاينة المجّانيّة قبل الخصم (9).
           طبقةٌ فوق الورقة لا تُخزَّن نسخةً موسومة — نفس مبدأ `PageWatermark` (20.3). */
        .wm { position: relative; }
        .wm-layer {
            position: absolute; inset: 0; pointer-events: none; overflow: hidden;
            display: flex; flex-wrap: wrap; align-content: space-around; justify-content: space-around;
            gap: 8mm; padding: 10mm; z-index: 2;
        }
        .wm-layer > * { transform: rotate(-28deg); font-size: 15pt; font-weight: 700; color: #16241f; }
        .wm-layer img { inline-size: 34mm; block-size: auto; }
        .wm-notice {
            max-inline-size: 210mm; margin: 4mm auto 0; padding: 3mm 4mm; border-radius: 4mm;
            background: #fff; color: #16241f; font-size: 10pt; line-height: 1.7;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 3mm;
        }
        .wm-notice a { background: #16241f; color: #fff; text-decoration: none; border-radius: 3mm; padding: 2mm 5mm; }
        @media print { .wm-notice { display: none; } }
    </style>
</head>
<body>
    @php
        $watermark = $watermark ?? null;
        $notice = $notice ?? null;
        $confirmUrl = $confirmUrl ?? null;
    @endphp

    <div @class(['wm' => (bool) $watermark])>
        @include($sheet['view'], [
            'user' => $sheet['user'],
            'data' => $sheet['data'],
            'pulled' => $sheet['pulled'],
        ])

        @if ($watermark)
            <div class="wm-layer" aria-hidden="true" style="opacity: {{ $watermark['opacity'] / 100 }}">
                @for ($i = 0; $i < $watermark['repeat']; $i++)
                    @if ($watermark['logo'])
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($watermark['logo']) }}" alt="">
                    @else
                        <span>{{ $watermark['text'] }}</span>
                    @endif
                @endfor
            </div>
        @endif
    </div>

    @if ($notice)
        {{-- ماذا حدث + ماذا تفعل، في سطر واحد (2.17-ب) --}}
        <div class="wm-notice" role="status">
            <span>{{ $notice }}</span>
            @if ($confirmUrl)
                <a href="{{ $confirmUrl }}">{{ setting('cv.export.confirm_label', 'أكّد وحمّل النسخة النظيفة') }}</a>
            @endif
        </div>
    @endif

    @if ($print)
        <script>
            /* [تحميل PDF]: محرّك طباعة المتصفّح — بلا أيّ مكتبة تُنزَّل من الشبكة */
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
