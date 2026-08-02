{{-- لاندنج بيدج الباقة: Anchoring وميزان قيمة محسوب من العناصر وبونص بقيمته (18 · 22) --}}
@extends('layouts.app')

@use('App\Services\Store\Coins')

@section('title', $item->name_ar)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($item->description ?? '')), 155))

@if (! $indexable)
    {{-- الفهرسة تحترم إعداد النموّ وعلَم العنصر (21.1-هـ) --}}
    @section('noindex', '1')
@endif

@push('head')
    {{-- Schema.org: الباقة `Product` — صفحة مفهرسة تجيب زوّارًا (21.1-أ) --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    @php
        /*
         | ⭐ **لاندنج بيدج الباقة** (18 · القسم 22) — الصفحة الوحيدة التي يظهر
         | فيها Override أسعار العناصر («قاعدة السعر السياقيّ» 18).
         |
         | وكلّ رقم هنا **من الخادم**: سعر الباقة سعرها هي لا مجموع عناصرها،
         | والقيمة الإجماليّة **محسوبة من العناصر نفسها** لا من `original_value`
         | الذي يكتبه الأدمن بلا تحقّق — فلا «وفّرت 4580» ولا Dark Patterns (2.9).
         */
        $owned = $quote['owned'];
        $canBuy = auth()->check() && ! $owned;
        $cover = $item->cover_path ? \Illuminate\Support\Facades\Storage::url($item->cover_path) : null;
        $libraryUrl = \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : null;
        $buyLabel = (string) setting('store.bundle.cta_label', 'احصل على الباقة كاملة');

        $savings = (float) $quote['savings'];
        $listPrice = (float) $quote['list_price'];
        $percentOff = $listPrice > 0 ? (int) round($savings / $listPrice * 100) : 0;

        $faq = setting('store.bundle.faq', []);
        $faq = is_array($faq) ? $faq : [];
    @endphp

    <x-page-header :title="$item->name_ar"
                   :breadcrumbs="[
                       ['label' => 'المتجر', 'url' => route('store.index')],
                       ['label' => 'الباقات', 'url' => route('store.bundles')],
                       ['label' => $item->name_ar],
                   ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد وحيد (2.15-أ-2) --}}
            @if ($owned && $libraryUrl)
                <a href="{{ $libraryUrl }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">افتح من مكتبتي</a>
            @elseif ($canBuy)
                <button type="button" data-modal-open="purchase-sheet"
                        class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ $buyLabel }} — {{ Coins::label($quote['total'], $quote['currency']) }}
                </button>
            @elseif (! auth()->check())
                <a href="{{ route('login') }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">سجّل دخولك للشراء</a>
            @endif
        </x-slot:action>
    </x-page-header>

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @error('checkout')
        <x-toast :message="$message" state="danger" />
    @enderror

    {{-- ------------------------------------------------------------------ الهيرو --}}
    <section class="card overflow-hidden">
        <div class="grid gap-0 lg:grid-cols-5">
            <div class="lg:col-span-3 p-5 sm:p-7 flex flex-col gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                          style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-400)">
                        <x-icon name="bundle" size="14" />
                        <span>{{ setting('store.bundle.hero_badge', 'باقة متكاملة') }}</span>
                    </span>

                    @if ($owned)
                        <x-state-badge state="ok" label="تملكه بالفعل" />
                    @endif

                    {{-- «وفّرت X» = الفرق الحقيقيّ بين مجموع العناصر وسعر الباقة (18 · 2.9) --}}
                    @if ($savings > 0)
                        <span class="text-xs rounded-full px-3 py-1 inline-flex items-center gap-1"
                              style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">
                            <span aria-hidden="true">◉</span>
                            <span>{{ str_replace('{amount}', Coins::label($savings, $quote['currency']), setting('store.savings.text', 'وفّرت {amount}')) }}</span>
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold leading-9">{{ $item->name_ar }}</h1>

                <p class="text-sm leading-7" style="color: var(--text-muted)">
                    {{ $item->description ?? 'كلّ اللي تحتاجه في مكان واحد — بسعر واحد.' }}
                </p>

                {{-- ما يفتحه لك فورًا: لا يوجد نوع «هديّة» — كلّ محتوى مضمَّن يُفتَح بحكم الشراء (18) --}}
                <p class="text-sm inline-flex items-center gap-2">
                    <x-icon name="check" size="16" />
                    <span>{{ str_replace('{count}', (string) $includes->count(), setting('store.bundle.items_count_text', '{count} عناصر بتتفتح كلّها في مكتبتك فور الشراء')) }}</span>
                </p>

                <div class="flex items-baseline gap-3 flex-wrap">
                    <span class="text-3xl font-extrabold">
                        {{ $quote['total'] > 0 ? Coins::label($quote['total'], $quote['currency']) : setting('store.free_label', 'مجّانيّ') }}
                    </span>

                    {{-- Anchoring (18): السعر الطبيعيّ مشطوبًا مقابل سعر الباقة --}}
                    @if ($listPrice > $quote['total'])
                        <span class="text-base line-through" style="color: var(--text-muted)">{{ Coins::fmt($listPrice) }}</span>
                        <span class="text-xs rounded-full px-2 py-0.5"
                              style="background: var(--surface-sunken); color: var(--text-muted)">أقلّ بـ{{ $percentOff }}%</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if ($owned)
                        <p class="text-sm">{{ setting('store.bundle.owned_text', 'الباقة دي معاك بالفعل — كلّ عناصرها مفتوحة في مكتبتك.') }}</p>
                        @if ($libraryUrl)
                            <a href="{{ $libraryUrl }}"
                               class="btn inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">افتح من مكتبتي</a>
                        @endif
                    @elseif (auth()->check())
                        <button type="button" data-modal-open="purchase-sheet"
                                class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>

                        <span class="text-sm" style="color: var(--text-muted)">
                            رصيدك الآن: <b>{{ Coins::label($quote['balance_before'], $quote['currency']) }}</b>
                        </span>
                    @else
                        <a href="{{ route('login') }}"
                           class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c; min-height: 44px">سجّل دخولك للشراء</a>
                    @endif
                </div>

                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('store.bundle.access_text', 'اللي تشتريه بيفضل معاك في مكتبتك — من غير تجديد ولا اشتراك.') }}
                </p>
            </div>

            <div class="lg:col-span-2 min-h-[12rem] flex items-center justify-center" style="background: var(--surface-sunken)">
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $item->name_ar }}" class="w-full h-full object-cover">
                @else
                    <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="bundle" size="72" /></span>
                @endif
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------- ما يشمله + البونص + القيمة --}}
    @if ($includes->isNotEmpty())
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-1">{{ setting('store.includes.title', 'ما يشمله') }}</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted)">
                {{ setting('store.bundle.honest_note', 'القيمة الإجماليّة تحت محسوبة من أسعار العناصر نفسها دلوقتي — مش رقمًا مكتوبًا باليد.') }}
            </p>

            <ul class="space-y-4">
                @foreach ($includes as $line)
                    <li class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0 flex items-start gap-2">
                                <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon name="check" size="16" /></span>
                                <a href="{{ route('store.product', ['type' => $line['type'], 'slug' => $line['slug']]) }}"
                                   class="text-sm font-semibold hover:underline">{{ $line['title'] }}</a>
                            </span>

                            {{--
                                قاعدة السعر السياقيّ (18): Override العنصر لا يظهر
                                إلّا هنا — في صفحة الباقة نفسها — ومعه سعره الطبيعيّ مشطوبًا.
                            --}}
                            @if ($line['value'] > 0)
                                <span class="flex items-baseline gap-2 whitespace-nowrap">
                                    <span class="text-xs" style="color: var(--text-muted)">{{ Coins::label($line['value']) }}</span>
                                    @if ($line['has_override'] && $line['list_value'] > $line['value'])
                                        <span class="text-xs line-through" style="color: var(--text-muted)">{{ Coins::fmt($line['list_value']) }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        {{-- «🎁 بونص: [العنصر] بقيمة X — مجّانًا مع الباقة» بنصّ 18 حرفًا --}}
                        @if ($line['list_value'] > 0)
                            <p class="mt-1 ms-6 text-xs" style="color: var(--color-brand-400)">
                                {{ str_replace(
                                    ['{item}', '{amount}'],
                                    [$line['title'], Coins::label($line['list_value'])],
                                    setting('store.bundle.bonus_text', '🎁 بونص: {item} بقيمة {amount} — مجّانًا مع الباقة'),
                                ) }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- ⭐ ميزان القيمة: الإجماليّ المحسوب مقابل سعر الباقة، والفرق هو التوفير (18) --}}
            <div class="mt-5 pt-4 space-y-2 text-sm" style="border-top: 1px solid var(--border)">
                <div class="flex items-center justify-between gap-3">
                    <span style="color: var(--text-muted)">{{ setting('store.bundle.total_value_label', 'القيمة الإجماليّة') }}</span>
                    <span class="line-through" style="color: var(--text-muted)">{{ Coins::label($listPrice) }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 font-bold">
                    <span>{{ setting('store.bundle.price_label', 'سعر الباقة') }}</span>
                    <span>{{ Coins::label($quote['total'], $quote['currency']) }}</span>
                </div>

                @if ($savings > 0)
                    <div class="flex items-center justify-between gap-3" style="color: var(--color-state-honor)">
                        <span>{{ setting('store.bundle.savings_label', 'اللي بتوفّره') }}</span>
                        <span>◉ {{ Coins::label($savings, $quote['currency']) }}</span>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- أسئلة قبل الشراء --}}
    @if ($faq !== [])
        <section class="card p-5 mt-5">
            <h2 class="font-bold mb-3">{{ setting('store.bundle.faq_title', 'أسئلة قبل ما تشتري') }}</h2>

            <ul class="space-y-2">
                @foreach ($faq as $entry)
                    @continue (empty($entry['q']))
                    <li>
                        <details class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                            <summary class="cursor-pointer text-sm font-semibold" style="min-height: 44px; display: flex; align-items: center">{{ $entry['q'] }}</summary>
                            <p class="mt-2 text-sm leading-7" style="color: var(--text-muted)">{{ $entry['a'] ?? '' }}</p>
                        </details>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- --------------------------------------------------------- الإغلاق وسياسة الاسترجاع --}}
    <section class="card p-5 mt-5 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold">{{ setting('store.bundle.closing_title', 'جاهز تبدأ؟') }}</p>
            <a href="{{ route('store.refund-policy') }}" class="text-xs hover:underline" style="color: var(--text-muted)">
                {{ setting('store.refund.link_text', 'اقرأ سياسة عدم الاسترجاع') }}
            </a>
        </div>

        @if (! $owned && auth()->check())
            <button type="button" data-modal-open="purchase-sheet"
                    class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-bold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ $buyLabel }}</button>
        @endif
    </section>

    {{-- ⭐ الشراء نفسه بالبوب-أب المشترك — والسعر يُحسَب في الخادم ولا يُقبَل من الطلب --}}
    @if ($canBuy)
        @include('store.partials.purchase-sheet', ['type' => $type, 'item' => $item, 'quote' => $quote])
    @endif
@endsection

@section('mobile_action')
    @if ($quote['owned'])
        @if (\Illuminate\Support\Facades\Route::has('library.index'))
            <a href="{{ route('library.index') }}"
               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c">افتح من مكتبتي</a>
        @endif
    @elseif (auth()->check())
        <button type="button" data-modal-open="purchase-sheet"
                class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('store.bundle.cta_label', 'احصل على الباقة كاملة') }}
        </button>
    @endif
@endsection
