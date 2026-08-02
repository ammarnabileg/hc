@props(['title' => '', 'subtitle' => null, 'action' => null, 'breadcrumbs' => []])

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
        <div>
            <h1 class="text-xl md:text-2xl font-extrabold">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $subtitle }}</p>
            @endif
        </div>
        {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
        @if ($action)
            <div class="flex items-center gap-2">{{ $action }}</div>
        @endif
    </div>
</header>
