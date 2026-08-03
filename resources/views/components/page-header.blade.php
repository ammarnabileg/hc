@props(['title' => '', 'subtitle' => null, 'action' => null, 'breadcrumbs' => [], 'pinnable' => true])

@php
    /*
     | ⭐ التثبيت (Pin) — البديل المعتمَد عن «آخر ما زرت» المرفوض (2.15-د):
     | يثبّت المستخدم الصفحات التي يستخدمها كثيرًا فتظهر أعلى السايد بار.
     | والزرّ في هيدر الصفحة لأنّه المكان الوحيد الحاضر في كلّ شاشة.
     */
    $pinRoute = request()->route()?->getName();
    $pinnedRoutes = collect(auth()->user()?->pinned_pages ?? [])->pluck('route')->all();
    $isPinned = $pinRoute && in_array($pinRoute, $pinnedRoutes, true);
    $showPin = $pinnable && auth()->check() && $pinRoute && \Illuminate\Support\Facades\Route::has($pinRoute);
@endphp

<header class="mb-5">
    @if ($breadcrumbs)
        {{-- Breadcrumb في كلّ شاشة داخليّة (2.15-د) --}}
        <nav class="text-xs mb-2 flex flex-wrap items-center gap-1" style="color: var(--text-muted)">
            @foreach ($breadcrumbs as $crumb)
                @if (! $loop->last)
                    <a href="{{ $crumb['url'] ?? '#' }}" class="hover:underline">{{ $crumb['label'] }}</a>
                    <span aria-hidden="true">‹</span>
                @else
                    <span>{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-2 min-w-0">
            @if ($showPin)
                <button type="button" data-pin-toggle="{{ $pinRoute }}" data-pin-label="{{ $title }}"
                        data-pinned="{{ $isPinned ? '1' : '0' }}"
                        class="shrink-0 inline-flex items-center justify-center rounded-xl motion-standard"
                        style="min-width: 44px; min-height: 44px; color: {{ $isPinned ? 'var(--color-brand-500)' : 'var(--text-muted)' }}"
                        aria-pressed="{{ $isPinned ? 'true' : 'false' }}"
                        aria-label="{{ $isPinned ? 'فكّ تثبيت الصفحة' : 'ثبّت الصفحة أعلى السايد بار' }}"
                        title="{{ $isPinned ? 'مثبَّتة' : 'ثبّت الصفحة' }}">
                    {{-- أيقونة دبّوس SVG مرسومة داخل المشروع (2.16-ج) --}}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="{{ $isPinned ? 'currentColor' : 'none' }}"
                         stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M9 3h6l-1 6 3 3v2H7v-2l3-3-1-6z" />
                        <path d="M12 14v7" fill="none" />
                    </svg>
                </button>
            @endif

            <div class="min-w-0">
                <h1 class="text-xl md:text-2xl font-extrabold">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) — وبجواره سويتش
             «وضع متقدّم» فيكون حاضرًا في **كلّ صفحة** كما تنصّ 2.15-أ-9 --}}
        {{-- `flex-wrap`: الفعل الرئيسيّ + سويتش «وضع متقدّم» معًا يتجاوزان عرض
             الموبايل، وبلا التفافٍ يدفعان الصفحة أفقيًّا (2.15-ج) --}}
        <div class="flex flex-wrap items-center gap-2">
            @if ($action)
                {{ $action }}
            @endif
            <x-advanced-toggle />
        </div>
    </div>
</header>
