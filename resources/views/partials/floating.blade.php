@php
    use App\Services\Engagement\PositiveMessages;

    /**
     * العناصر العائمة في **كلّ الصفحات** (2.6).
     *
     * سهم العودة لأعلى مبنيّ في `home.partials.surprise` مع أيقونة الرسائل
     * الإيجابيّة وسلوك التكديس بينهما — فلا نبني سهمًا ثانيًا، ونكتفي بإدراج
     * الطبقة نفسها في التخطيط العامّ حتى تظهر على كلّ صفحة لا على الرئيسيّة وحدها.
     *
     * والمتغيّرات تُحسَب هنا فقط إن لم يمرّرها المتحكّم — فلا يتغيّر سلوك
     * الصفحات التي تحسبها بنفسها.
     */
    $floatingSurprise = $surprise ?? null;
    $floatingOffer = $offerTicket ?? null;

    if ($floatingSurprise === null && $floatingOffer === null) {
        $positive = app(PositiveMessages::class);

        $floatingSurprise = $positive->shouldShowIcon()
            ? $positive->forContext((string) setting('engagement.positive.surprise_context', 'surprise'))
            : null;

        $floatingOffer = (bool) auth()->check();
    }
@endphp

@include('home.partials.surprise', [
    'surprise' => $floatingSurprise,
    'offerTicket' => (bool) $floatingOffer,
])
