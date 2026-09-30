@php
    /*
     | قالب البريد الموحّد: جداول وأنماط سطريّة (عملاء البريد لا تقرأ CSS خارجيًّا)،
     | والألوان من نفس رموز الهويّة (DesignTokens · الفاتح)، والنصوص كلّها ممرَّرة من
     | المرسِل الذي يقرؤها من setting() (2.13). خطّ المنصّة إن كان مثبَّتًا وإلّا خطّ النظام.
     */
    $tokens = app(\App\Services\Ui\DesignTokens::class)->variables();
    $c = fn (string $var, string $fallback) => $tokens[$var] ?? $fallback;
    $bg = $c('--surface', '#fcfbf8');
    $card = $c('--surface-raised', '#ffffff');
    $soft = $c('--surface-sunken', '#f3efe7');
    $line = $c('--border', '#dfddd5');
    $ink = $c('--text', '#171715');
    $muted = $c('--text-muted', '#65645f');
    $brand = $c('--color-brand-500', '#d9231b');
    $font = "Alexandria, 'Segoe UI', Tahoma, Arial, sans-serif";
    $appName = config('app.name');
    $tagline = (string) setting('ux.footer.tagline', 'تعلّم يصنع أثرًا');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; background:{{ $bg }}; font-family:{{ $font }}; color:{{ $ink }}; direction:rtl; text-align:right;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $bg }}; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:{{ $card }}; border:1px solid {{ $line }}; border-radius:12px;">
                <tr>
                    <td style="padding:24px 28px 8px; font-size:22px; font-weight:800; color:{{ $ink }};">
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:{{ $brand }}; vertical-align:middle; margin-inline-start:8px;"></span>{{ $appName }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 0; font-size:20px; font-weight:700; line-height:1.6; color:{{ $ink }};">{{ $heading }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 28px 0; font-size:15px; line-height:1.9; color:{{ $ink }};">{!! nl2br(e($bodyText)) !!}</td>
                </tr>
                @if ($code !== null && $code !== '')
                    <tr>
                        <td style="padding:20px 28px 0;" align="center">
                            <div dir="ltr" style="display:inline-block; padding:14px 28px; border-radius:12px; background:{{ $soft }}; border:1px solid {{ $line }}; font-size:32px; font-weight:800; letter-spacing:.35em; color:{{ $ink }};">{{ $code }}</div>
                        </td>
                    </tr>
                @endif
                @if ($ctaLabel && $ctaUrl)
                    <tr>
                        <td style="padding:24px 28px 0;">
                            <a href="{{ $ctaUrl }}" style="display:inline-block; padding:14px 24px; border-radius:10px; background:{{ $brand }}; color:#ffffff; font-weight:700; font-size:15px; text-decoration:none;">{{ $ctaLabel }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 28px 0; font-size:12px; line-height:1.8; color:{{ $muted }};" dir="ltr">
                            <span dir="rtl">{{ setting('ux.mail.link_fallback', 'لو الزرّ ما اشتغلش، انسخ الرابط ده:') }}</span><br>
                            <a href="{{ $ctaUrl }}" style="color:{{ $muted }}; word-break:break-all;">{{ $ctaUrl }}</a>
                        </td>
                    </tr>
                @endif
                @if ($footer !== '')
                    <tr>
                        <td style="padding:22px 28px 0; font-size:13px; line-height:1.8; color:{{ $muted }};">{!! nl2br(e($footer)) !!}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:22px 28px 24px;">
                        <div style="border-top:1px solid {{ $line }}; padding-top:14px; font-size:12px; color:{{ $muted }};">{{ $appName }} · {{ $tagline }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
