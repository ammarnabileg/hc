{{--
    ⭐⭐ صفحة الهبوط **المستقلّة** (12.2.3 `landing_pages.view`) — للبندل أو
    المنتج، ومنفصلة عن `store.bundle`/`store.product`. الشراء يبقى **هناك**:
    زرّ النداء هنا يوصّل لصفحة المتجر الحقيقيّة (`targetStoreUrl()`) لا يكرّر
    منطق التسعير/الشراء — تلك مسؤوليّة `store.product`/`store.bundle` وحدها.

    ⚠️ لا شهادات عملاء مفبركة ولا عدّادات وهميّة هنا كذلك (2.9 · 21.1-د) — نفس
    قاعدة `store/bundle.blade.php`.
--}}
@extends('layouts.app')

@section('title', $landingPage->title())
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($landingPage->promise()), 155))

@if (! $indexable)
    @section('noindex', '1')
@endif

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="text-center space-y-3 py-6">
            @if ($landingPage->hero_image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($landingPage->hero_image_path) }}" alt=""
                     class="rounded-2xl mx-auto max-h-80 object-cover" loading="lazy">
            @endif

            <h1 class="text-3xl font-extrabold">{{ $landingPage->title() }}</h1>

            @if ($landingPage->promise() !== '')
                <p class="text-lg" style="color: var(--text-muted)">{{ $landingPage->promise() }}</p>
            @endif

            @if ($landingPage->targetStoreUrl())
                <a href="{{ $landingPage->targetStoreUrl() }}"
                   class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ $landingPage->ctaLabel() }}</a>
            @endif
        </header>

        @if (! empty($landingPage->outcomes))
            <section class="card p-5">
                <h2 class="font-bold mb-3">{{ setting('store.landing_page.outcomes_title', 'هتقدر تعمل إيه؟') }}</h2>
                <ul class="space-y-2">
                    @foreach ($landingPage->outcomes as $line)
                        <li class="flex items-start gap-2">
                            <x-icon name="check" size="16" class="mt-0.5 shrink-0" />
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($landingPage->body)
            <section class="card p-5">
                <div class="prose max-w-none">{!! nl2br(e($landingPage->body)) !!}</div>
            </section>
        @endif

        @if (! empty($landingPage->faq))
            <section class="card p-5">
                <h2 class="font-bold mb-3">{{ setting('store.landing_page.faq_title', 'أسئلة شائعة') }}</h2>
                <div class="space-y-3">
                    @foreach ($landingPage->faq as $row)
                        <details>
                            <summary class="cursor-pointer font-semibold">{{ $row['q'] ?? '' }}</summary>
                            <p class="mt-1 text-sm" style="color: var(--text-muted)">{{ $row['a'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($landingPage->targetStoreUrl())
            <div class="text-center pb-6">
                <a href="{{ $landingPage->targetStoreUrl() }}"
                   class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ $landingPage->ctaLabel() }}</a>
            </div>
        @endif
    </div>
@endsection
