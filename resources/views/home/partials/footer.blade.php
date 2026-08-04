@php
    /** تذييل بسيط — روابط عامّة موجودة فعلًا فقط، فلا رابط مكسور في صفحة مفهرسة */
    $note = (string) setting('home.footer.note', 'منصّة تعلّم وتطوّع عربيّة — بنتعلّم ونشتغل جنب بعض.');
    $links = [];

    if (\Illuminate\Support\Facades\Route::has('verify.certificate')) {
        $links[] = ['label' => (string) setting('home.footer.verify', 'التحقّق من شهادة'), 'url' => route('verify.certificate')];
    }
    if (\Illuminate\Support\Facades\Route::has('ambassadors.index')) {
        $links[] = ['label' => (string) setting('home.footer.ambassadors', 'لوحة السفراء'), 'url' => route('ambassadors.index')];
    }
@endphp

<footer class="pt-6 mt-2 text-center" style="border-top: 1px solid var(--border)">
    <p class="text-sm">{{ $note }}</p>

    @if ($links)
        <nav class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs" aria-label="{{ setting('home.footer.aria_label_1', 'روابط عامّة') }}">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="underline" style="color: var(--text-muted)">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    @endif

    <p class="mt-3 text-xs" style="color: var(--text-muted)">{{ config('app.name') }}</p>
</footer>
