@php
    /** قيمة المنصّة: بلوكات قصيرة — سطر لكلّ شرح ولا حشو (2.15-أ-8) */
    $title = (string) setting('home.value.title', 'ليه المنصّة دي؟');
@endphp

@if ($valueBlocks)
    <section class="mb-6" aria-labelledby="home-value-title">
        <h2 id="home-value-title" class="text-lg md:text-xl font-extrabold mb-3">{{ $title }}</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($valueBlocks as $block)
                <article class="card p-4 md:p-5 animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <span class="inline-flex items-center justify-center rounded-xl mb-3"
                          style="width:38px;height:38px;background: var(--surface-sunken); color: var(--color-brand-500)">
                        @include('home.partials.icon', ['name' => 'check', 'size' => 20])
                    </span>
                    <h3 class="font-bold text-sm">{{ $block['title'] }}</h3>
                    @if ($block['body'])
                        <p class="mt-1 text-sm" style="color: var(--text-muted)">{{ $block['body'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
