@php
    /** الفعاليّات القادمة (13.3) — بموعدها الحقيقيّ، بلا عدّاد ضغط ولا ندرة مزيّفة (2.9) */
    $title = (string) setting('home.events.title', 'الفعاليّات القادمة');
    $dateFormat = (string) setting('home.events.date_format', 'l j F — H:i');
    $modes = [
        'online' => (string) setting('home.events.mode_online', 'أونلاين'),
        'offline' => (string) setting('home.events.mode_offline', 'حضوريّ'),
        'hybrid' => (string) setting('home.events.mode_hybrid', 'مختلط'),
    ];
@endphp

@if ($events->isNotEmpty())
    <section class="mb-6" aria-labelledby="home-events-title">
        <h2 id="home-events-title" class="text-lg md:text-xl font-extrabold mb-3">{{ $title }}</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($events as $event)
                <article class="card p-4 animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span style="color: var(--color-brand-500)">
                            @include('home.partials.icon', ['name' => 'event', 'size' => 18])
                        </span>
                        <span class="rounded-full px-2 py-0.5 text-xs"
                              style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $modes[$event->mode] ?? $event->mode }}
                        </span>
                    </div>

                    <h3 class="font-bold text-sm break-words">{{ $event->title_ar }}</h3>
                    <p class="mt-1 text-xs" style="color: var(--text-muted)">
                        {{ $event->starts_at?->translatedFormat($dateFormat) }}
                    </p>

                    <a href="{{ route('register') }}"
                       class="btn inline-flex items-center justify-center w-full mt-3 rounded-xl px-3 py-2 text-xs font-bold motion-standard"
                       style="background: color-mix(in srgb, var(--color-brand-500) 16%, transparent); color: var(--color-brand-500)">
                        {{ setting('home.events.cta', 'سجّل واحجز مكانك') }}
                    </a>
                </article>
            @endforeach
        </div>
    </section>
@endif
