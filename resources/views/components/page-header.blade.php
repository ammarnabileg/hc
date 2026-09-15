@props(['title' => '', 'subtitle' => null, 'action' => null, 'breadcrumbs' => [], 'pinnable' => true, 'eyebrow' => null])

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

{{--
  ⭐ تقسيمة `.page-head` حرفيًّا من ملف الهويّة المرجعيّ (`head()` في app.js):
  عنوان علويّ (eyebrow) + H1 + سطر فرعيّ على جهة، وفعل رئيسيّ واحد على الجهة
  الأخرى. والتثبيت/سويتش «وضع متقدّم» ليسا في المرجع — بقيا بنفس وظيفتهما
  في شريطٍ رفيعٍ من `.icon-button`/`.hc-switch` فوق العنوان (تعليمات المالك).
--}}
<header class="mb-2">
    @if ($breadcrumbs)
        {{-- Breadcrumb في كلّ شاشة داخليّة (2.15-د) --}}
        <nav class="text-xs mb-2 flex flex-wrap items-center gap-1" style="color: var(--text-muted)">
            @foreach ($breadcrumbs as $crumb)
                @if (! $loop->last)
                    <a href="{{ $crumb['url'] ?? '#' }}"
                       class="hover:underline inline-flex items-center justify-center"
                       style="min-inline-size: var(--touch-min, 44px); min-block-size: var(--touch-min, 44px)">{{ $crumb['label'] }}</a>
                    <span aria-hidden="true">‹</span>
                @else
                    <span>{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="spread mb-3" style="gap: 8px">
        <div class="cluster" style="gap: 4px">
            @if ($showPin)
                <button type="button" data-pin-toggle="{{ $pinRoute }}" data-pin-label="{{ $title }}"
                        data-pinned="{{ $isPinned ? '1' : '0' }}" class="icon-button"
                        aria-pressed="{{ $isPinned ? 'true' : 'false' }}"
                        style="color: {{ $isPinned ? 'var(--color-brand-500)' : 'var(--text-muted)' }}"
                        aria-label="{{ $isPinned ? (string) setting('ux.page_header.aria_label_expr_1', 'فكّ تثبيت الصفحة') : (string) setting('ux.page_header.aria_label_expr_2', 'ثبّت الصفحة أعلى السايد بار') }}"
                        title="{{ $isPinned ? (string) setting('ux.page_header.title_expr_1', 'مثبَّتة') : (string) setting('ux.page_header.title_expr_2', 'ثبّت الصفحة') }}">
                    <x-icon name="pin" size="18" />
                </button>
            @endif
        </div>
        <x-advanced-toggle />
    </div>

    <div class="page-head">
        <div class="min-w-0">
            <span class="eyebrow">{{ $eyebrow ?? setting('ux.page_head.eyebrow', 'رحلتك التعليمية') }}</span>
            <h1>{{ $title }}</h1>
            @if ($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>

        {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
        @if ($action)
            <div class="cluster">{{ $action }}</div>
        @endif
    </div>
</header>
