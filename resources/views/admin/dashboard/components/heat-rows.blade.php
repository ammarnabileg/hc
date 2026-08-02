@php
    /**
     * صفوف حراريّة مرسومة بأيدينا — **بلا أيّ مكتبة رسوم** (2.16-ج).
     *
     * والحرارة **رقمٌ ونسبةٌ مكتوبان** جوار الشريط: اللون لا يحمل المعنى وحده
     * (2.16)، فمن لا يميّز الألوان يقرأ الرقم نفسه.
     * وتُستهلَك للخريطة الجغرافيّة (12.3-13) ولـDrop-off التدريبات (12.3-15).
     */
    $rows = $rows ?? [];
    $unit = $unit ?? '';
@endphp

<section class="card p-4 min-w-0">
    <h3 class="font-bold text-sm">{{ $title }}</h3>

    @if ($rows === [])
        <p class="mt-4 text-sm" style="color: var(--text-muted)">{{ $empty }}</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($rows as $row)
                <li class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $row['label'] }}</span>
                        <span class="text-xs whitespace-nowrap" style="color: var(--text-muted)">
                            {{ number_format($row['value']) }} {{ $unit }} · {{ $row['percent'] }}%
                        </span>
                    </div>

                    <div class="mt-1 h-2 w-full rounded-full overflow-hidden" style="background: var(--surface-sunken)"
                         role="img" aria-label="{{ $row['label'] }}: {{ $row['value'] }} {{ $unit }}">
                        <div class="h-full rounded-full"
                             style="width: {{ max($row['percent'], 2) }}%; background: var(--color-brand-500)"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
