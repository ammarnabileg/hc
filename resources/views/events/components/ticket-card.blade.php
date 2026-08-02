@php
    /**
     * التذكرة القابلة للنشر (13.3): كود فريد مقروء بخطّ كبير.
     * **بلا مكتبة QR** — التشيك-إن الأوفلاين يتمّ بقراءة الكود نصًّا أو بفتح رابط التشيك-إن،
     * والتحقّق النهائيّ خادميّ في كلّ الأحوال.
     */
    $event = $registration->event ?? $event;
    $local = $presenter->localStart($event, $registration->user ?? auth()->user());
    $checkinUrl = route('events.show', $event->slug).'#checkin';
@endphp

<div class="card overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between gap-2"
         style="background: color-mix(in srgb, var(--color-brand-500) 12%, transparent)">
        <div class="flex items-center gap-2 font-bold">
            @include('events.components.icon', ['name' => 'ticket', 'box' => 18])
            تذكرتك
        </div>
        <x-state-badge state="ok" :label="$registration->attended ? 'حضور مؤكَّد' : 'مؤكَّدة'" />
    </div>

    <div class="px-5 py-5 text-center">
        <div class="text-sm mb-1" style="color: var(--text-muted)">{{ $event->title_ar }}</div>
        <div class="text-xs mb-4" style="color: var(--text-muted)">
            {{ $local->format('Y-m-d · H:i') }} — {{ $presenter->timezone($registration->user ?? auth()->user()) }}
        </div>

        {{-- الكود بخطّ كبير: يُقرأ بالعين وبالكاميرا بلا أيّ اعتماد خارجيّ --}}
        <div class="rounded-2xl py-5 px-3 mb-4" style="background: var(--surface-sunken); border: 1px dashed var(--border)">
            <div class="text-2xl md:text-3xl font-extrabold tracking-widest" style="color: var(--color-brand-500)">
                {{ $registration->ticket_code }}
            </div>
            <div class="text-xs mt-2" style="color: var(--text-muted)">كود التذكرة — اعرضه عند الاستقبال</div>
        </div>

        @if ($registration->attend_mode)
            <div class="text-xs mb-3" style="color: var(--text-muted)">
                نمط الحضور: {{ $presenter->modeLabel($registration->attend_mode) }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-2">
            @include('events.components.copy', ['text' => $registration->ticket_code, 'label' => 'نسخ الكود', 'tone' => 'ghost'])

            <a href="{{ route('events.ics', $event->slug) }}"
               class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @include('events.components.icon', ['name' => 'calendar']) أضِف لتقويمي
            </a>

            <a href="{{ route('events.ticket', $event->slug) }}"
               class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                @include('events.components.icon', ['name' => 'share']) شارك تذكرتك
            </a>
        </div>

        <a href="{{ $checkinUrl }}" class="block text-xs mt-3 hover:underline" style="color: var(--text-muted)">
            رابط التشيك-إن: {{ $checkinUrl }}
        </a>
    </div>
</div>
