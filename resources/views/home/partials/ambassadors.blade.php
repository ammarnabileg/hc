@php
    /**
     * سفراؤنا (7.6.1 · 21.1-ج): لقب فقط بلا شارة، وبأرقام دعوات **مفعَّلة** حقيقيّة.
     * ولا نعرض إلّا مَن بلغ عتبةً فعلًا — فلا لوحة فاضية تُملأ بأسماء وهميّة (2.9-7).
     */
    $title = (string) setting('home.ambassadors.title', 'سفراء المنصّة');
    $subtitle = (string) setting('home.ambassadors.subtitle', 'ناس دعت أصحابها فكبر المكان بيهم — واللقب بيتحسب بالدعوات المفعّلة بس.');
@endphp

@if ($ambassadors->isNotEmpty())
    <section class="mb-6" aria-labelledby="home-ambassadors-title">
        <div class="flex items-end justify-between gap-3 flex-wrap mb-3">
            <div>
                <h2 id="home-ambassadors-title" class="text-lg md:text-xl font-extrabold">{{ $title }}</h2>
                <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $subtitle }}</p>
            </div>
            <a href="{{ route('ambassadors.index') }}" class="text-xs underline" style="color: var(--color-brand-500)">
                {{ setting('home.ambassadors.more', 'اللوحة كاملة') }}
            </a>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($ambassadors as $ambassador)
                @include('home.partials.ambassador-card', ['ambassador' => $ambassador, 'rank' => $loop->iteration])
            @endforeach
        </div>
    </section>
@endif
