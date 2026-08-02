@php
    /**
     * ⭐ البكسل والأحداث الثمانية من المتصفّح (21.3-أ).
     *
     * القاعدة الحاكمة قبل أيّ سطر: **لا يُحقَن شيء إلّا بموافقة صريحة** (21.3-د)،
     * و**مفتاح `ads.tracking.enabled` يوقف التتبّع كلّه** لا البانر وحده.
     * فإن سقط أيّ شرط، هذا القالب يخرج **فارغًا تمامًا** — لا سكربت ولا نداء.
     *
     * ⭐ ولكلّ حدثٍ **Event ID** هو نفسه الذي أرسله الخادم عبر Conversions API،
     *    فلا يُحتسَب الحدث مرّتين مهما وصل من القناتين.
     */
    $adConsent = app(\App\Services\Ads\Consent::class);
    $trackingOn = $adConsent->trackingEnabled() && $adConsent->allowsAds();

    $metaId = trim((string) setting('ads.pixel.meta_id', ''));
    $googleId = trim((string) setting('ads.pixel.google_id', ''));

    // التقاط أحداث هذا الطلب (خريطة المسار + مصالحة التحويلات) وسحب طابور المتصفّح
    $adQueue = $trackingOn ? app(\App\Services\Ads\AdSignals::class)->capture() : [];
    $adCatalog = \App\Services\Ads\AdEvents::catalog();
@endphp

@if ($trackingOn && ($metaId !== '' || $googleId !== ''))
    @php
        $browserEvents = collect($adQueue)->map(fn ($row) => [
            'meta' => $adCatalog[$row['name']]['meta'] ?? null,
            'google' => $adCatalog[$row['name']]['google'] ?? null,
            'uid' => $row['uid'],
            'payload' => (object) ($row['payload'] ?? []),
        ])->filter(fn ($row) => $row['meta'] !== null)->values();
    @endphp

    <script>
        window.hcAdEvents = @json($browserEvents);
    </script>

    @if ($metaId !== '')
        <script>
            !function (f, b, e, v, n, t, s) {
                if (f.fbq) return; n = f.fbq = function () {
                    n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
                };
                if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
                t = b.createElement(e); t.async = !0; t.src = v;
                s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
            }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

            fbq('init', @json($metaId));
            fbq('track', 'PageView');

            // ⭐ نفس Event ID الذي أرسله الخادم — مفتاح إزالة التكرار (21.3-أ)
            (window.hcAdEvents || []).forEach(function (row) {
                fbq('track', row.meta, row.payload, { eventID: row.uid });
            });
        </script>
    @endif

    @if ($googleId !== '')
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', @json($googleId));

            (window.hcAdEvents || []).forEach(function (row) {
                if (!row.google) return;
                gtag('event', row.google, Object.assign({ transaction_id: row.uid }, row.payload));
            });
        </script>
    @endif
@endif
