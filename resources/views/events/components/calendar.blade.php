@php
    /**
     * التقويم **مرسوم بأيدينا بشبكة RTL** — بلا أيّ مكتبة تقويم.
     * الأسبوع يبدأ بالسبت، والاتّجاه يأتي من dir="rtl" في الليَاوت.
     * وعلى الموبايل تتحوّل الشبكة إلى قائمة بلا تمرير أفقيّ (2.15-ج).
     */
    $month = $calendar['month'];
    $prev = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
    $today = \Carbon\CarbonImmutable::now($presenter->timezone(auth()->user()))->toDateString();
    $monthLabel = $month->translatedFormat('F Y');
@endphp

<div class="card p-3 md:p-4">

    <div class="flex items-center justify-between gap-2 mb-3">
        <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar', 'month' => $prev]) }}"
           class="btn rounded-xl px-3 py-2 text-sm motion-standard"
           style="background: var(--surface-sunken)" aria-label="الشهر السابق">›</a>

        <div class="font-bold">{{ $monthLabel }}</div>

        <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar', 'month' => $next]) }}"
           class="btn rounded-xl px-3 py-2 text-sm motion-standard"
           style="background: var(--surface-sunken)" aria-label="الشهر التالي">‹</a>
    </div>

    {{-- شبكة الشهر: من الديسكتوب فقط --}}
    <div class="hidden md:block">
        <div class="grid grid-cols-7 gap-1 mb-1 text-center text-xs" style="color: var(--text-muted)">
            @foreach ($weekdays as $day)
                <div class="py-1">{{ $day }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @foreach ($calendar['weeks'] as $week)
                @foreach ($week as $cell)
                    <div class="rounded-xl p-1.5 min-h-24 text-xs"
                         style="background: {{ $cell['date'] ? 'var(--surface-sunken)' : 'transparent' }};
                                border: 1px solid {{ $cell['date'] && $cell['date']->toDateString() === $today ? 'var(--color-brand-500)' : 'transparent' }}">
                        @if ($cell['date'])
                            <div class="mb-1 {{ $cell['date']->toDateString() === $today ? 'font-bold' : '' }}"
                                 style="color: {{ $cell['date']->toDateString() === $today ? 'var(--color-brand-500)' : 'var(--text-muted)' }}">
                                {{ $cell['date']->day }}
                            </div>

                            {{-- حبوب ملوّنة للفعاليّات (24.5) — ومعها رمز النوع لا اللون وحده (2.16-ب) --}}
                            @foreach ($cell['events'] as $item)
                                <a href="{{ route('events.show', $item->slug) }}"
                                   class="block truncate rounded-lg px-1.5 py-1 mb-1 motion-standard hover:opacity-90"
                                   style="background: color-mix(in srgb, var(--color-brand-500) 16%, transparent); color: var(--text)"
                                   title="{{ $item->title_ar }}">
                                    @include('events.components.icon', ['name' => $item->mode, 'box' => 12])
                                    {{ $item->title_ar }}
                                </a>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- الموبايل: التقويم يتحوّل لقائمة أيّام --}}
    <div class="md:hidden space-y-2">
        @php $hasAny = false; @endphp
        @foreach ($calendar['weeks'] as $week)
            @foreach ($week as $cell)
                @if ($cell['date'] && $cell['events']->isNotEmpty())
                    @php $hasAny = true; @endphp
                    <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                        <div class="text-xs mb-2" style="color: var(--text-muted)">
                            {{ $cell['date']->translatedFormat('l j F') }}
                        </div>
                        @foreach ($cell['events'] as $item)
                            <a href="{{ route('events.show', $item->slug) }}" class="block py-1 text-sm">
                                @include('events.components.icon', ['name' => $item->mode, 'box' => 14])
                                {{ $item->title_ar }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endforeach

        @unless ($hasAny)
            <p class="text-sm text-center py-6" style="color: var(--text-muted)">مفيش فعاليّات في الشهر ده.</p>
        @endunless
    </div>
</div>
