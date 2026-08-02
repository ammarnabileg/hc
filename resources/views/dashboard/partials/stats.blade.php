{{-- تاب «إحصائيّاتي» (14-ج): خمسة رسوم مرسومة SVG بأيدينا بلا أيّ مكتبة خارجيّة --}}

{{-- فلتر الفترة العامّ داخل هذا التاب وحده — لا فلاتر على رأس الصفحة (24.5) --}}
<div class="flex items-center gap-2 mb-4">
    <span class="text-xs" style="color: var(--text-muted)">الفترة</span>
    @foreach ($rangeOptions as $option)
        <a href="{{ route('dashboard', ['tab' => 'stats', 'days' => $option]) }}"
           class="rounded-full px-3 py-1.5 text-xs motion-standard"
           style="{{ $days === $option
               ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
               : 'background: var(--surface-raised); color: var(--text)' }}">
            {{ $option }} يوم
        </a>
    @endforeach
</div>

<div class="grid gap-4 xl:grid-cols-2">
    @include('dashboard.components.chart-area', ['points' => $xpSeries])
    @include('dashboard.components.chart-donut', ['donut' => $donut])
    @include('dashboard.components.chart-heatmap', ['heatmap' => $heatmap])
    @include('dashboard.components.chart-radar', ['radar' => $radar])

    <div class="xl:col-span-2">
        @include('dashboard.components.chart-bars', ['bars' => $ticketBars])
    </div>
</div>
