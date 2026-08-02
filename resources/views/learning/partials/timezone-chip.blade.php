@php
    /**
     * شريحة «توقيتك» (الدستور 5): تقول للمستخدم بأيّ ساعةٍ تُحسَب مواعيده،
     * وتفتح تعديلًا يدويًّا — فالكشف تلقائيّ لكنّ القرار الأخير له هو.
     *
     * $clock: ['timezone','offset','source','now'] · $timezones: قائمة الاختيار
     */
    $sourceLabel = [
        'manual' => setting('availability.timezone.source_manual'),
        'auto' => setting('availability.timezone.source_auto'),
        'country' => setting('availability.timezone.source_country'),
        'platform' => setting('availability.timezone.source_platform'),
    ][$clock['source']] ?? '';
@endphp

<button type="button" data-modal-open="my-timezone"
        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs motion-standard"
        style="background: var(--surface-sunken); color: var(--text-muted)">
    {{-- أيقونة الكرة الأرضيّة: SVG مرسوم بهويّة المنصّة — بلا مكتبات (2.16-ج) --}}
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"
         stroke="currentColor" stroke-width="1.8">
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" stroke-linecap="round" />
    </svg>

    <span>{{ setting('availability.timezone.chip_label') }}</span>
    <strong style="color: var(--text)">{{ $clock['timezone'] }}</strong>
    <span>· {{ $clock['now']->translatedFormat('H:i') }}</span>
</button>

@push('modals')
    <x-modal id="my-timezone" :title="setting('availability.timezone.modal_title')">
        <p class="text-sm mb-3" style="color: var(--text-muted)">
            {{ setting('availability.timezone.modal_hint') }}
            @if ($sourceLabel)
                <br><span>{{ $sourceLabel }}</span>
            @endif
        </p>

        <form method="post" action="{{ route('timezone.update') }}" class="space-y-3">
            @csrf

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('availability.timezone.field_label') }}</span>
                <select name="timezone" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('availability.timezone.auto_option') }}</option>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}" @selected(auth()->user()->timezone === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
            </label>

            <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">
                {{ setting('availability.timezone.save_cta') }}
            </button>
        </form>
    </x-modal>
@endpush
