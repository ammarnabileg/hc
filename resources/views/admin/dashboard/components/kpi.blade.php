@php
    /**
     * كارت KPI إداريّ: الرقم + سهم ▲▼ ونسبة التغيّر + **تلوين صحّة المؤشّر** (12.3-2/18).
     * واللون لا يحمل المعنى وحده — معه رمز دائمًا من قاموس 2.16.
     *
     * ⭐ **الكارت قابل للنقر** (12.3-4): ينقل للشاشة التفصيليّة لهذا المؤشّر.
     * وحين لا يملك المستخدم تلك الشاشة يصير الكارت رقمًا بلا رابط — فلا نَعِد
     * ببابٍ مقفول (2.15-أ-7).
     */
    $delta = $card['delta'] ?? null;
    $arrow = $delta === null ? '' : ($delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▬'));
    $url = $card['url'] ?? null;
    $tag = $url ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($url) href="{{ $url }}" aria-label="{{ strtr(setting('admin.dashboard.components.kpi.v1_afth_altfasyl', ':v1 — افتح التفاصيل'), [':v1' => e($card['label'])]) }}" @endif
    class="card p-4 animate-fadeup min-w-[13rem] sm:min-w-0 block motion-standard {{ $url ? 'hover:opacity-90' : '' }}"
    style="min-height: 44px" data-kpi-card="{{ $card['key'] ?? '' }}">

    <div class="flex items-center justify-between">
        <span class="text-sm" style="color: var(--text-muted)">{{ $card['label'] }}</span>
        {{-- الأيقونة SVG من قاموس المنصّة — ممنوع أيّ مكتبة أيقونات (2.16-ج) --}}
        <span aria-hidden="true" style="color: var(--color-brand-500)"><x-icon :name="$card['icon']" size="18" /></span>
    </div>

    {{-- عدّاد تصاعديّ — والرقم النهائيّ **مخدَّم من الخادم داخل الوسم** فيظهر مهما حصل (2.17-أ) --}}
    <div class="mt-2 text-2xl font-extrabold"
         data-count-to="{{ number_format($card['value']) }}">{{ number_format($card['value']) }}</div>

    <div class="mt-2 flex items-center gap-2 flex-wrap">
        @if ($compare && $delta !== null)
            {{-- الأرقام الثانويّة بالـHover لا بمساحة دائمة (2.15-د) --}}
            <span class="text-xs cursor-help" style="color: var(--text-muted)"
                  title="{{ strtr(setting('admin.dashboard.components.kpi.alftra_alsabqa_v1', 'الفترة السابقة: :v1'), [':v1' => e(number_format($card['previous']))]) }}">
                {{ $arrow }} {{ abs($delta) }}%
            </span>
        @endif

        @if (! empty($card['state']))
            <x-state-badge :state="$card['state']" :label="match ($card['state']) {
                'ok' => setting('admin.dashboard.components.kpi.saad', 'صاعد'),
                'danger' => setting('admin.dashboard.components.kpi.habt', 'هابط'),
                default => setting('admin.dashboard.components.kpi.thabt', 'ثابت'),
            }" />
        @endif
    </div>

    <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $card['hint'] }}</div>

    @if ($url)
        <div class="mt-2 text-xs" style="color: var(--color-brand-400)">{{ setting('admin.dashboard.components.kpi.afth_altfasyl', 'افتح التفاصيل ←') }}</div>
    @endif
</{{ $tag }}>
