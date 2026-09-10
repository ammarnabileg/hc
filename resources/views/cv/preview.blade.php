<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ setting('cv.page.title', 'سيرتي الذاتيّة') }}</title>
    {{--
     | ⛔ لا خطّ من شبكةٍ خارجيّة. «القاهرة» مبنيّ داخل الحزمة أصلًا
     | (`@fontsource/cairo` في `app.css`) وتستعمله المنصّة كلّها — وكان هذا
     | القالب وحده يجلبه من CDN.
     |
     | والعطب ليس مخالفةً شكليّة: هذه **ورقة تُطبَع وتُسلَّم** (السيرة الذاتيّة
     | وشهادة الخبرة). فحين يتعذّر الوصول للـCDN — بلا إنترنت، أو خلف جدارٍ
     | ناريّ في شركة، أو لأنّ الخدمة محجوبة — يسقط الخطّ العربيّ **بصمت**
     | ويُطبَع المستند بخطٍّ بديلٍ لا يشبه هويّة المنصّة، أو بحروفٍ مكسورة.
     | ولا يكتشف ذلك أحدٌ إلّا صاحبُ الورقة بعد أن يكون قد أرسلها.
     --}}
    @vite(['resources/css/app.css'])
    <style>
        /* ورقة بالمقاس الحقيقيّ A4 — والمعاينة الحيّة تُعرَض بنفس المقاس (24.5) */
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9edec; font-family: 'Cairo', system-ui, sans-serif; }
        .sheet {
            /* position:relative دائمًا — لا بشرط العلامة المائيّة فقط — فهي المرساة
               التي تُبنى عليها الطبقة الزخرفيّة (Drag-drop المرحلة 1 · 12.7-ب)
               بـposition:absolute؛ راجع resources/views/cv/templates/partials/decor-layer.blade.php */
            position: relative;
            inline-size: 210mm; min-block-size: 297mm; margin: 0 auto; background: #fff; color: #16241f;
            padding: 14mm 14mm 16mm; box-shadow: 0 2px 18px rgb(0 0 0 / .12);
        }
        @media print {
            html, body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; }
        }

        /*
         * ⭐ الورقة **A4 حقيقيّة** عند الطباعة، و**مصغَّرة لتسع الشاشة** على
         * الموبايل. 210mm ≈ 794px، فعلى شاشة 375px كانت تخرج بتمرير أفقيّ —
         * وهو ممنوع نصًّا (2.15-ج).
         *
         * والتصغير بـ`scale` لا بتغيير المقاسات: تغييرها يجعل ما يراه المستخدم
         * غير ما سيُطبَع، والمعاينة عهدٌ بأنّ ما تراه هو ما تأخذه.
         *
         * والنسبة تأتي من سطرٍ من جافاسكربت لا من `calc`: قسمة طولٍ على عدد في
         * CSS تُنتج **طولًا** لا نسبةً مجرّدة، و`scale()` لا تقبل إلّا عددًا —
         * فالتعبير يسقط صامتًا وتبقى الورقة بمقاسها، ويقصّها الصندوق. أي أنّ
         * «الإصلاح» كان سيستبدل بالتمرير الأفقيّ **إخفاءَ نصف الورقة**.
         *
         * والافتراضيّ **1 بلا قصّ**: لو تعطّل السكربت رجعنا للتمرير — وهو أهون
         * من ورقةٍ ناقصة (2.1: التحسين تدريجيّ).
         */
        .sheet-scale-wrap { --sheet-scale: 1; }

        @media screen {
            /* التوسيط بالـFlex لا بالهوامش: صندوقٌ أعرض من حاويته يُوزَّع فائضه
               **بالتساوي على الجهتين** فيقع مركزه في مركز الشاشة — أمّا الهوامش
               التلقائيّة فتُسنِده لجهة البداية، وهي تختلف بين RTL وLTR فتخرج
               الورقة مزاحةً في العربيّة تحديدًا. */
            .sheet-scale-wrap { display: flex; justify-content: center; }

            .sheet-scale {
                flex: none;
                inline-size: 210mm;
                transform: scale(var(--sheet-scale));
                transform-origin: top center;
            }

            /* لا يُقَصّ إلّا حين يقع تصغيرٌ فعلًا — فلا يختفي شيء بلا سبب */
            .sheet-scale-wrap[data-scaled="true"] { overflow: hidden; }
        }

        .sheet h1 { font-size: 22pt; margin: 0 0 2mm; }
        .sheet h2 { font-size: 11pt; margin: 7mm 0 2mm; letter-spacing: .02em; }
        .sheet p, .sheet li, .sheet td { font-size: 10pt; line-height: 1.7; margin: 0; }
        .muted { color: #56706a; }
        .row { display: flex; justify-content: space-between; gap: 4mm; }
        .chips { display: flex; flex-wrap: wrap; gap: 2mm; }
        .chip { border: 1px solid #d5e0dd; border-radius: 999px; padding: 1mm 3mm; font-size: 9pt; }
        .entry { margin-block-end: 4mm; }
        /* الصورة الشخصيّة على الـCV — ولو غابت لا يُحجَز مكانها (9) */
        .sheet header.with-photo { display: flex; align-items: center; gap: 6mm; }
        .cv-photo { inline-size: 28mm; block-size: 28mm; border-radius: 50%; object-fit: cover; }
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

    <div class="sheet-scale-wrap" data-sheet-scale>
    <div @class(['wm' => (bool) $watermark, 'sheet-scale' => true])>
        @include($sheet['view'], [
            'user' => $sheet['user'],
            'data' => $sheet['data'],
            'pulled' => $sheet['pulled'],
            'decorLayers' => $sheet['decorLayers'] ?? [],
        ])

        @if ($watermark)
            <div class="wm-layer" data-cv-watermark aria-hidden="true" style="opacity: {{ $watermark['opacity'] / 100 }}">
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

    <script>
        /* مقاس الورقة الحقيقيّ 210mm ≈ 794px — نصغّرها لتسع الشاشة بلا تمرير أفقيّ */
        (function () {
            var wrap = document.querySelector('[data-sheet-scale]');
            var sheet = wrap && wrap.querySelector('.sheet');
            if (!wrap || !sheet) return;

            function fit() {
                var natural = sheet.offsetWidth || 794;
                var room = document.documentElement.clientWidth - 16;
                var scale = Math.min(1, room / natural);

                wrap.style.setProperty('--sheet-scale', scale);
                wrap.dataset.scaled = scale < 1 ? 'true' : 'false';
                // الصندوق يأخذ الارتفاع **بعد** التصغير فلا يبقى فراغٌ تحته
                wrap.style.blockSize = scale < 1 ? (sheet.offsetHeight * scale) + 'px' : '';
            }

            fit();
            window.addEventListener('resize', fit);
            /* الطباعة تعود للمقاس الحقيقيّ ثمّ يُعاد الضبط بعدها */
            window.addEventListener('beforeprint', function () { wrap.style.setProperty('--sheet-scale', 1); wrap.style.blockSize = ''; });
            window.addEventListener('afterprint', fit);
        })();
    </script>

    @if ($print)
        <script>
            /* [تحميل PDF]: محرّك طباعة المتصفّح — بلا أيّ مكتبة تُنزَّل من الشبكة */
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
