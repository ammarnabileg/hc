<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ setting('attestations.sheet.title', 'إفادة من المنصّة') }} — {{ $holder->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e9edec; font-family: 'Cairo', system-ui, sans-serif; }
        .sheet { inline-size: 210mm; min-block-size: 297mm; margin: 0 auto; background: #fff; color: #16241f; padding: 16mm; box-shadow: 0 2px 18px rgb(0 0 0 / .12); }
        h1 { font-size: 20pt; margin: 0 0 2mm; }
        h2 { font-size: 11pt; margin: 7mm 0 2mm; }
        p, li { font-size: 10pt; line-height: 1.7; margin: 0; }
        .muted { color: #56706a; }
        ul { padding-inline-start: 5mm; margin: 0; }
        .band { display: flex; gap: 6mm; margin-block-start: 4mm; }
        .stat { border: 1px solid #d5e0dd; border-radius: 3mm; padding: 3mm 5mm; text-align: center; }
        .stat b { display: block; font-size: 15pt; }
        @media print { html, body { background: #fff; } .sheet { box-shadow: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <h1>{{ setting('attestations.sheet.title', 'إفادة من المنصّة') }}</h1>
    {{-- اسم الإفادة = اسم بيانات الشهادات والإفادات ومعه اللقب (2.5-ج · 9.1) --}}
    <p class="muted">{{ $record['holder']['holder_name'] ?: $holder->name }} · #{{ $holder->code }}</p>
    @if ($record['holder']['holder_address'])
        <p class="muted">{{ $record['holder']['holder_address'] }}</p>
    @endif

    <div class="band">
        <div class="stat"><b>{{ $record['courses']->count() }}</b>{{ setting('attestations.kpi.courses', 'تدريبات مكتملة') }}</div>
        <div class="stat"><b>{{ $record['certificates']->count() }}</b>{{ setting('attestations.kpi.certificates', 'شهادات سارية') }}</div>
        <div class="stat"><b>{{ $record['badges']->count() }}</b>{{ setting('attestations.kpi.badges', 'شارات') }}</div>
        <div class="stat"><b>{{ $record['xp'] }}</b>{{ setting('attestations.kpi.xp', 'نقاط الخبرة') }}</div>
    </div>

    @if ($record['courses']->isNotEmpty())
        <h2>{{ setting('attestations.sheet.courses_title', 'التدريبات المكتملة') }}</h2>
        <ul>
            @foreach ($record['courses'] as $course)
                <li>{{ $course['name'] }} <span class="muted">— {{ $course['completed_at']?->translatedFormat('F Y') }}</span></li>
            @endforeach
        </ul>
    @endif

    @if ($record['certificates']->isNotEmpty())
        <h2>{{ setting('attestations.sheet.certificates_title', 'الشهادات وروابط التحقّق') }}</h2>
        <ul>
            @foreach ($record['certificates'] as $certificate)
                <li>
                    {{ $certificate->certificate_type?->name_ar }}
                    <span class="muted">— {{ $certificate->code }}@if ($certificate->verify_url) · {{ $certificate->verify_url }}@endif</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($record['badges']->isNotEmpty())
        <h2>{{ setting('attestations.sheet.badges_title', 'الشارات') }}</h2>
        <ul>@foreach ($record['badges'] as $badge)<li>{{ $badge->name_ar }}</li>@endforeach</ul>
    @endif

    @if ($approved->isNotEmpty())
        <h2>{{ setting('attestations.sheet.recommendations_title', 'إفادات موثّقة') }}</h2>
        @foreach ($approved as $attestation)
            <p>«{{ $attestation->body }}»</p>
            <p class="muted">— {{ $attestation->from_user?->name ?? $attestation->from_name }}</p>
        @endforeach
    @endif

    <p class="muted" style="margin-block-start: 8mm">
        {{ setting('attestations.sheet.footer', 'كلّ ما في هذه الإفادة مولَّد من سجلّ المنصّة، ويمكن التحقّق منه بالأكواد أعلاه.') }}
    </p>
</div>

@if ($print)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
