@php
    /**
     * لافتة الإتاحة (الدستور 5 · 2.17): **ماذا حدث + متى يفتح** بتوقيت المستخدم
     * المحلّيّ ومعها عدّاد تنازليّ. والعنصر المقفول لا يُخفى — يظهر بحالته وسببه (24.5).
     *
     * $availability: مخرجات AvailabilityService::forCourse()
     */
    $opensAt = $availability['opens_at'] ?? null;
    $opensIn = $availability['opens_in'] ?? null;
@endphp

@unless ($availability['open'])
    <div class="card p-4 mb-4">
        <div class="flex items-start gap-2 flex-wrap">
            {{-- اللون لا يحمل المعنى وحده — الشارة تحمل رمزًا ونصًّا (2.16) --}}
            <x-state-badge :state="$availability['state']" :label="setting('learning.lock.badge')" />
            <p class="text-sm flex-1 min-w-48">{{ $availability['reason'] }}</p>
        </div>

        @if ($opensIn !== null && $opensIn > 0)
            <div class="mt-3 flex items-center gap-2 flex-wrap text-sm">
                {{-- أيقونة الساعة: SVG بهويّة المنصّة لا مكتبة أيقونات (2.16-ج) --}}
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>

                <span style="color: var(--text-muted)">{{ setting('learning.availability.countdown_label') }}</span>
                <strong data-availability-countdown="{{ $opensIn }}"
                        style="color: var(--color-brand-400)">{{ setting('learning.availability.countdown_label') }}</strong>
            </div>
        @endif

        <p class="text-xs mt-2" style="color: var(--text-muted)">
            {{ setting('learning.availability.timezone_note') }}
            <strong style="color: var(--text)">{{ $availability['timezone'] }}</strong>
            ({{ $availability['offset'] }})
        </p>
    </div>
@endunless

@if (($availability['periods'] ?? collect())->isNotEmpty() || ($availability['daily'] ?? null))
    <details class="card p-4 mb-4">
        <summary class="cursor-pointer text-sm font-semibold select-none">
            {{ setting('learning.availability.schedule_title') }}
        </summary>

        @if ($availability['daily'])
            <p class="text-sm mt-3">
                {{ str_replace(
                    [':from', ':to'],
                    [$availability['daily']['open'], $availability['daily']['close']],
                    (string) setting('learning.availability.daily_line'),
                ) }}
            </p>
        @endif

        @if ($availability['periods']->isNotEmpty())
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($availability['periods'] as $period)
                    <li class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5"
                        style="background: var(--surface-sunken)">
                        <span>{{ $period->starts_on->translatedFormat('j F Y') }}
                            <span aria-hidden="true" style="color: var(--text-muted)">←</span>
                            {{ $period->ends_on->translatedFormat('j F Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </details>
@endif
