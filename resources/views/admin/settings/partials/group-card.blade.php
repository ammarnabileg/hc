@php
    /**
     * كارت مجموعة إعدادات — **مولِّد عامّ** لا شاشة يدويّة لكلّ مجموعة (2.13 · 2.15).
     * الحقل يُختار من نوع القيمة في `field.blade.php`: نصّ · رقم · منطقيّ · قائمة · JSON.
     *
     * $group · $rows · $registry · $endpoint · $open (مفتوح افتراضيًّا؟)
     */
    $open = $open ?? false;
    $ownerOnly = collect($rows)->contains(fn ($row) => (bool) $row->is_owner_only);
@endphp

<details class="card p-4" data-group-card="{{ $group }}" {{ $open ? 'open' : '' }}>
    <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
        <div class="min-w-0">
            <div class="text-sm font-semibold flex items-center gap-2">
                {{-- أيقونة SVG مرسومة بهويّة المنصّة — بلا أيّ مكتبة أيقونات --}}
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="shrink-0"
                     style="color: var(--color-brand-500)">
                    <path d="M3 5.5 8 10l5-4.5" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ $registry->groupLabel($group) }}
                @if ($ownerOnly)<span title="فيها مفاتيح لمالك المنصّة وحده"><x-icon name="lock" size="16" /></span>@endif
            </div>
            <div class="text-xs" style="color: var(--text-muted)">{{ $registry->groupHint($group) }}</div>
        </div>

        <span class="text-xs shrink-0 rounded-full px-2 py-1"
              style="background: var(--surface-sunken); color: var(--text-muted)">{{ count($rows) }}</span>
    </summary>

    <div class="mt-4 space-y-4">
        @foreach ($rows as $setting)
            @include('admin.settings.partials.field', [
                'setting' => $setting,
                'registry' => $registry,
                'endpoint' => $endpoint,
            ])
        @endforeach
    </div>
</details>
