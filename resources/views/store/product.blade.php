@extends('layouts.app')

@use('App\Services\Store\Coins')

@section('title', $item->name_ar)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($item->description_ar ?? $item->description ?? '')), 155))

@if (! $indexable)
    {{-- الفهرسة تحترم إعداد النموّ وعلَم العنصر (21.1-هـ) --}}
    @section('noindex', '1')
@endif

{{-- ⭐ صورة OG بقالب نوع الرابط: تدريب أو مسار (21.1-أ · 12.14) --}}
@if (in_array($type, ['course', 'path'], true))
    @section('og_image', route('growth.og.'.$type, $item->slug))
@endif

@push('head')
    {{-- Schema.org: التدريب `Course` وغيره `Product` — لصفحة مفهرسة تجيب زوّارًا (21.1-أ) --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    @php
        $owned = $quote['owned'];
        $canBuy = auth()->check() && $quote['sellable'] && ! $owned;
        $cover = $item->cover_path ? \Illuminate\Support\Facades\Storage::url($item->cover_path) : null;
        $libraryUrl = \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : null;
    @endphp

    <x-page-header :title="$item->name_ar"
                   :breadcrumbs="[['label' => 'المتجر', 'url' => route('store.index')], ['label' => $item->name_ar]]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد وحيد (2.15-أ-2) --}}
            @if ($owned)
                @if ($libraryUrl)
                    <a href="{{ $libraryUrl }}"
                       class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">افتح من مكتبتي</a>
                @endif
            @elseif ($canBuy)
                <button type="button" data-modal-open="purchase-sheet"
                        class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    شراء بالكوينز — {{ Coins::label($quote['total']) }}
                </button>
            @elseif (! auth()->check() && $quote['sellable'])
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

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="card overflow-hidden">
                <div class="aspect-[16/9] w-full flex items-center justify-center" style="background: var(--surface-sunken)">
                    @if ($cover)
                        <img src="{{ $cover }}" alt="{{ $item->name_ar }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-5xl" aria-hidden="true">{{ $type === 'bundle' ? '📦' : ($type === 'course' ? '🎓' : '📘') }}</span>
                    @endif
                </div>

                <div class="p-5 space-y-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($owned)
                            <x-state-badge state="ok" label="تملكه بالفعل" />
                        @endif
                        @if ($quote['savings'] > 0)
                            <span class="text-xs rounded-full px-2 py-0.5 inline-flex items-center gap-1"
                                  style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-400)">
                                <span aria-hidden="true">✂</span>
                                <span>{{ str_replace('{amount}', Coins::label($quote['savings']), setting('store.savings.text', 'وفّرت {amount}')) }}</span>
                            </span>
                        @endif
                    </div>

                    <p class="text-sm leading-7" style="color: var(--text-muted)">
                        {{ $item->description_ar ?? $item->description ?? 'لسّه مافيش وصف للعنصر ده.' }}
                    </p>
                </div>
            </div>

            {{-- المعاينة / صفحات العيّنة (20.3 · 21.1-أ) --}}
            @if ($previewNote)
                <div class="card p-5">
                    <h2 class="font-bold mb-2">معاينة قبل الشراء</h2>
                    <p class="text-sm" style="color: var(--text-muted)">{{ $previewNote }}</p>

                    {{-- ⭐ الوعد له باب: مسار درسٍ عامّ بلا تسجيل (21.1-أ) --}}
                    @if ($type === 'course' && app(\App\Services\Growth\LessonPreview::class)->lessons($item)->isNotEmpty())
                        <a href="{{ route('growth.preview.course', $item->slug) }}"
                           class="btn inline-flex items-center justify-center mt-3 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('growth.preview.open_label', 'شوف الدرس') }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- ما يشمله --}}
            @if ($includes->isNotEmpty())
                <div class="card p-5">
                    <h2 class="font-bold mb-3">ما يشمله</h2>
                    <ul class="space-y-2">
                        @foreach ($includes as $line)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <a href="{{ route('store.product', ['type' => $line['type'], 'slug' => $line['slug']]) }}"
                                   class="hover:underline">{{ $line['title'] }}</a>
                                @if ($line['value'] > 0)
                                    {{-- قيمة العنصر الطبيعيّة كبونص (18) — رقم حقيقيّ من بياناته --}}
                                    <span class="text-xs" style="color: var(--text-muted)">بقيمة {{ Coins::label($line['value']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="card p-5 space-y-3">
                @if (! $quote['sellable'])
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('store.path.note_text', 'يُفتَح ضمن الباقات') }}</p>
                    <a href="{{ route('store.bundles') }}" class="text-sm hover:underline" style="color: var(--color-brand-400)">شوف الباقات</a>
                @else
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold">
                            {{ $quote['total'] > 0 ? Coins::label($quote['total']) : setting('store.free_label', 'مجّانيّ') }}
                        </span>
                        @if ($quote['list_price'] > $quote['total'])
                            <span class="text-sm line-through" style="color: var(--text-muted)">{{ Coins::fmt($quote['list_price']) }}</span>
                        @endif
                    </div>

                    <div class="text-sm" style="color: var(--text-muted)">
                        رصيدك الآن: <b>{{ Coins::label($quote['balance_before']) }}</b>
                    </div>

                    @if ($owned)
                        <p class="text-sm">{{ setting('store.owned_text', 'ده معاك بالفعل — تلاقيه في مكتبتك.') }}</p>
                        @if ($libraryUrl)
                            <a href="{{ $libraryUrl }}" class="text-sm hover:underline" style="color: var(--color-brand-400)">افتح من مكتبتي</a>
                        @endif
                    @elseif (auth()->check())
                        <button type="button" data-modal-open="purchase-sheet"
                                class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">شراء بالكوينز</button>

                        {{--
                            السلّة **اختياريّة وثانويّة** (17): الفعل الرئيسيّ يظلّ الشراء المباشر
                            بلا مغادرة الصفحة (24.5 · 2.15)، وهذه لمَن يجمع أكثر من عنصر.
                        --}}
                        @if ($quote['sellable'] && setting('store.cart.enabled', true))
                            <form method="post" action="{{ route('store.cart.add') }}">
                                @csrf
                                <input type="hidden" name="type" value="{{ $type }}">
                                <input type="hidden" name="slug" value="{{ $item->slug }}">
                                <button type="submit"
                                        class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm motion-standard"
                                        style="background: var(--surface-sunken); color: var(--text)">
                                    {{ setting('store.cart.add_label') }}
                                </button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}"
                           class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">سجّل دخولك للشراء</a>
                    @endif

                    <a href="{{ route('store.refund-policy') }}" class="block text-xs hover:underline" style="color: var(--text-muted)">
                        {{ setting('store.refund.link_text', 'اقرأ سياسة عدم الاسترجاع') }}
                    </a>
                @endif
            </div>
        </aside>
    </div>

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
    @elseif (auth()->check() && $quote['sellable'])
        <button type="button" data-modal-open="purchase-sheet"
                class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">شراء بالكوينز</button>
    @endif
@endsection
