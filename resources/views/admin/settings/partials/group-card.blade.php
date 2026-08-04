@php
    /**
     * كارت مجموعة إعدادات — **مولِّد عامّ** لا شاشة يدويّة لكلّ مجموعة (2.13 · 2.15).
     * الحقل يُختار من نوع القيمة في `field.blade.php`: نصّ · رقم · منطقيّ · قائمة · JSON.
     *
     * ⭐ **مطويّ لا يُحمَّل إلّا عند فتحه** (2.15-ب): الرأس يعرض الاسم والوصف
     * وعدد المفاتيح (عدّةُ SQL)، والحقول تصل دفعةً دفعة من `admin.settings.batch`.
     * ولا مفتاح يُحذَف ولا يُخفى (2.13) — «حمّل المزيد» يبلغ آخر مفتاح في المجموعة.
     *
     * $group · $count · $registry · $tab · $search · $batch · $endpoint
     * $open (مفتوح ومحمَّلة دفعتُه الأولى؟) · $rows (دفعة أوّليّة إن كان مفتوحًا)
     * $offset (بداية الدفعة الأوّليّة — دفعةُ المفتاح القادم من البحث)
     */
    $open = $open ?? false;
    $rows = $rows ?? collect();
    $offset = $offset ?? 0;
    $loaded = $offset + count($rows);
@endphp

<details class="card p-4" data-group-card="{{ $group }}"
         data-tab="{{ $tab }}" data-group="{{ $group }}"
         data-q="{{ $search }}" data-total="{{ $count }}"
         data-loaded="{{ $open ? $loaded : 0 }}"
         data-start="{{ $open ? $offset : 0 }}"
         {{ $open ? 'open' : '' }}>
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
                @if ($ownerOnly ?? false)<span title="{{ setting('admin.settings.partials.group_card.fyha_mfatyh_lmalk_almnsa_whdh', 'فيها مفاتيح لمالك المنصّة وحده') }}"><x-icon name="lock" size="16" /></span>@endif
            </div>
            <div class="text-xs" style="color: var(--text-muted)">{{ $registry->groupHint($group) }}</div>
        </div>

        <span class="text-xs shrink-0 rounded-full px-2 py-1"
              style="background: var(--surface-sunken); color: var(--text-muted)">{{ $count }}</span>
    </summary>

    <div class="mt-4 space-y-4" data-group-fields>
        @if ($open)
            @include('admin.settings.partials.group-fields', [
                'rows' => $rows,
                'registry' => $registry,
                'endpoint' => $endpoint,
            ])
        @endif
    </div>

    {{-- الحالات الأربع (2.15): تحميل بهيكل شكلِ الصفّ · خطأ بسطرٍ وإعادة · فارغة · وبلا صلاحيّة تُخفى المجموعة أصلًا --}}
    <div class="mt-4 space-y-3 hidden" data-group-skeleton aria-hidden="true">
        @for ($i = 0; $i < 3; $i++)
            <div class="animate-shimmer rounded-xl" style="height: 4.5rem; background: var(--surface-sunken)"></div>
        @endfor
    </div>

    <div class="mt-3 text-xs hidden" data-group-error style="color: var(--color-state-warn)"></div>

    <div class="mt-4 flex items-center gap-3 {{ $count > $batch ? '' : 'hidden' }}" data-group-more-row>
        <button type="button" class="rounded-xl px-3 py-2 text-sm" data-group-more
                style="background: var(--surface-raised); color: var(--text)">{{ setting('admin.settings.partials.group_card.hml_almzyd', 'حمّل المزيد') }}</button>
        <span class="text-xs" data-group-progress style="color: var(--text-muted)"></span>
    </div>
</details>
