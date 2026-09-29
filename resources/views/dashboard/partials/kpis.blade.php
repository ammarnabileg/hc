{{-- شريط KPI صفٌّ واحد مقسوم (حرفيًّا من `dashboard()` في المرجع) — يُعرَض مع التدريبات ومع الحالة الفارغة سواء --}}
<div class="kpi-grid">
    @foreach ($kpis as $kpi)
        <div class="kpi">
            <div class="kpi-label">
                @if (is_string($kpi['icon']) && preg_match('/^[a-z][a-z0-9_-]*$/', $kpi['icon']))
                    <x-icon :name="$kpi['icon']" size="18" />
                @endif
                {{ $kpi['label'] }}
            </div>
            <div class="kpi-value" data-count-to="{{ $kpi['value'] }}">{{ $kpi['value'] }}</div>
            @if ($kpi['hint'])
                <span class="small muted">{{ $kpi['hint'] }}</span>
            @endif
            @if ($kpi['state'])
                <div class="mt-2"><x-state-badge :state="$kpi['state']" /></div>
            @endif
        </div>
    @endforeach
</div>
