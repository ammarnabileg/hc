@props(['iso2' => '', 'width' => 22])

{{--
    علم الدولة **مرسومًا SVG داخل الصفحة** (2.5-ب: «Select لأكواد الدول بالأعلام»).
    ⛔ لا مكتبة أيقونات · ⛔ لا صورة من شبكة خارجيّة · ⛔ لا إيموجي علمٍ يرسمه النظام.
    والرسم من `App\Services\Geo\FlagLibrary` — بيانات هندسة في الحزمة نفسها.
--}}
{!! app(\App\Services\Geo\FlagLibrary::class)->svg((string) $iso2, (int) $width) !!}
