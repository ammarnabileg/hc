<!DOCTYPE html>
<html lang="{{ $certificate->language }}" dir="{{ $certificate->language === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $data['certificate_name'] ?? $certificate->code }} — {{ $certificate->code }}</title>

    {{--
      البديل الطباعيّ للصورة (8): HTML خالص يطبعه المتصفّح PDF —
      **بلا أيّ مكتبة أو خطّ خارجيّ** (الشبكة محجوبة)، والخطّ من خطوط النظام.
    --}}
    <style>
        :root { --ink: #16241f; --muted: #56706a; --brand: #00806c; --honor: #a8862a; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #fff; color: var(--ink);
            font-family: 'Cairo', 'Noto Naskh Arabic', 'Segoe UI', system-ui, sans-serif;
        }
        .sheet {
            max-width: 1000px; margin: 0 auto; padding: 48px;
            border: 6px double var(--honor); border-radius: 8px; text-align: center;
        }
        .kicker { color: var(--muted); font-size: 14px; letter-spacing: .04em; }
        h1 { font-size: 34px; margin: 12px 0 4px; color: var(--honor); }
        .holder { font-size: 30px; font-weight: 800; margin: 18px 0 6px; }
        .subject { font-size: 22px; color: var(--brand); font-weight: 700; margin: 6px 0 18px; }
        .meta { display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; margin-top: 28px; font-size: 14px; color: var(--muted); }
        .meta strong { display: block; color: var(--ink); font-size: 15px; }
        .qr { margin-top: 24px; }
        .qr img { inline-size: 140px; block-size: 140px; }
        .status { margin-top: 18px; font-size: 14px; }
        .status[data-state="expired"] { color: var(--muted); }
        .status[data-state="revoked"] { color: #b91c1c; }
        .print-hint { text-align: center; margin: 16px auto 0; font-size: 13px; color: var(--muted); }
        @media print { .print-hint { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="sheet">
        <p class="kicker">{{ $data['accreditation_name'] ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة') }}</p>
        <h1>{{ setting('certificates.render.heading', 'شهادة معتمدة') }}</h1>
        <p class="kicker">{{ setting('certificates.render.subheading', 'تشهد المنصّة بأنّ') }}</p>

        <p class="holder">{{ $data['holder_name'] ?? '' }}</p>
        <p class="kicker">{{ setting('certificates.render.completion_text', 'قد أتمّ بنجاح') }}</p>
        <p class="subject">{{ $data['certificate_name'] ?? $certificate->certificate_type?->name_ar }}</p>

        <div class="meta">
            <span>{{ setting('certificates.labels.issued_at', 'تاريخ الإصدار') }}<strong>{{ $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}</strong></span>
            @if (! empty($data['country']))
                <span>{{ setting('certificates.labels.country', 'الدولة') }}<strong>{{ $data['country'] }}</strong></span>
            @endif
            <span>{{ setting('certificates.labels.code', 'الكود') }}<strong>#{{ $certificate->code }}</strong></span>
        </div>

        <div class="qr">
            <img src="{{ route('certificates.qr', $certificate->code) }}" alt="{{ setting('certificates.labels.qr_alt', 'رمز التحقّق') }}">
            <p class="kicker">{{ $verifyUrl }}</p>
        </div>

        @if ($certificate->status !== 'valid')
            <p class="status" data-state="{{ $certificate->status }}">
                @if ($certificate->status === 'expired')
                    {{ setting('certificates.status.expired_label', 'منتهية') }} —
                    {{ setting('certificates.status.expired_line', 'انتهى العمل بيها في') }}
                    {{ $certificate->expired_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}
                @else
                    {{ setting('certificates.status.revoked_label', 'ملغاة') }}
                @endif
            </p>
        @endif
    </div>

    <p class="print-hint">{{ setting('certificates.labels.print_hint', 'اطبع الصفحة أو احفظها PDF من متصفّحك.') }}</p>
</body>
</html>
