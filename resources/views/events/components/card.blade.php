@php
    /** بطاقة الفعاليّة (13.3): غلاف · عنوان · شارة النوع · العدّاد · المكان/الرابط · حالة تسجيلي */
    $registered = ($myRegistrations ?? collect())->has($event->id);
    $ended = $presenter->hasEnded($event);
    $full = $presenter->isFull($event);
    $free = (float) $event->price_coins <= 0 && (float) $event->price_tickets <= 0;
    $local = $presenter->localStart($event, auth()->user());
@endphp

<a href="{{ route('events.show', $event->slug) }}"
   class="card p-4 flex flex-col gap-3 animate-fadeup motion-standard hover:opacity-95">

    <div class="min-w-0 flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h3 class="font-bold leading-6 truncate">{{ $event->title_ar }}</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted)">
                {{ $local->format('Y-m-d') }} · {{ $local->format('H:i') }}
            </div>
        </div>
        <x-state-badge :state="$presenter->state($event)" :label="$presenter->stateLabel($event)" />
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        {{-- شارة النوع بأيقونتها المرسومة (2.16-ج) --}}
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5"
              style="background: var(--surface-sunken); color: var(--text-muted)">
            @include('events.components.icon', ['name' => $event->mode])
            {{ $presenter->modeLabel($event->mode) }}
        </span>

        @if ($event->category)
            <span class="rounded-full px-2 py-0.5" style="background: var(--surface-sunken); color: var(--text-muted)">
                {{ $event->category }}
            </span>
        @endif

        <span class="rounded-full px-2 py-0.5"
              style="background: color-mix(in srgb, var(--color-brand-500) 14%, transparent); color: var(--color-brand-500)">
            {{ $free ? setting('events.card.price_free', 'مجّانيّ') : trim(
                ((float) $event->price_coins > 0 ? str_replace(':coins', (string) (int) $event->price_coins, (string) setting('events.card.price_coins', ':coins كوينز')).' ' : '')
                .((float) $event->price_tickets > 0 ? str_replace(':tickets', (string) (int) $event->price_tickets, (string) setting('events.card.price_tickets', ':tickets تذكرة')) : '')
            ) }}
        </span>
    </div>

    <div class="text-xs truncate" style="color: var(--text-muted)">
        @include('events.components.icon', ['name' => $event->mode === 'offline' ? 'pin' : 'link'])
        {{ $event->mode === 'offline'
            ? ($event->location ?: setting('events.card.location_tbd', 'المكان يتحدّد قريبًا'))
            : setting('events.card.join_link_soon', 'رابط الانضمام يفتح قبل الموعد') }}
    </div>

    <div class="flex items-center justify-between gap-2 mt-auto pt-2" style="border-top: 1px solid var(--border)">
        @include('events.components.countdown', ['event' => $event, 'presenter' => $presenter])

        @if ($registered)
            <span class="text-xs font-semibold" style="color: var(--color-state-ok)">● {{ setting('events.card.registered', 'مسجَّل') }}</span>
        @elseif ($ended)
            <span class="text-xs" style="color: var(--text-muted)">○ {{ setting('events.card.ended', 'انتهت') }}</span>
        @elseif ($full)
            <span class="text-xs" style="color: var(--color-state-warn)">▲ {{ setting('events.card.full', 'اكتمل العدد') }}</span>
        @else
            <span class="text-xs font-semibold" style="color: var(--color-brand-500)">{{ setting('events.card.register_cta', 'سجّل') }} ‹</span>
        @endif
    </div>
</a>
