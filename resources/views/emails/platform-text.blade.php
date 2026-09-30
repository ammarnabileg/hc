{{ $heading }}

{{ $textBody ?? $bodyText }}
@if ($textBody === null && $code !== null && $code !== '')

{{ $code }}
@endif
@if ($textBody === null && $ctaLabel && $ctaUrl)

{{ $ctaLabel }}: {{ $ctaUrl }}
@endif
@if ($footer !== '')

{{ $footer }}
@endif

{{ config('app.name') }} · {{ setting('ux.footer.tagline', 'تعلّم يصنع أثرًا') }}
