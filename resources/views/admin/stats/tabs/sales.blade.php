<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.sales.aliyradat', 'الإيرادات') }}</h2>
        {!! $chart->line($data['series'], $data['series_prev']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.sales.aala_msadr_aldkhl', 'أعلى مصادر الدخل') }}</h2>
        {!! $chart->bars($data['sources']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.sales.alshhn_baltryqtyn', 'الشحن بالطريقتين') }}</h2>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.stats.tabs.sales.thwyl_ydwy', 'تحويل يدويّ') }}</span><strong>{{ $data['topup']['manual_total'] }}</strong></div>
            <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.stats.tabs.sales.bwaba_aldfa', 'بوّابة الدفع') }}</span><strong>{{ $data['topup']['gateway_total'] }}</strong></div>
            <div class="flex justify-between"><span style="color: var(--text-muted)">{{ setting('admin.stats.tabs.sales.nsba_njah_albwaba', 'نسبة نجاح البوّابة') }}</span><strong>{{ $data['topup']['gateway_success_rate'] }}%</strong></div>
            {{-- ⛔ متوسّط زمن المراجعة مؤشّر داخليّ ولا يُعلَن للمُرسِل (19.5-أ) --}}
            <div class="flex justify-between">
                <span style="color: var(--text-muted)">{{ setting('admin.stats.tabs.sales.mtwst_zmn_almrajaa_dakhly', 'متوسّط زمن المراجعة (داخليّ)') }}</span>
                <strong>{{ $data['topup']['avg_review_hours_internal'] }} {{ setting('admin.stats.tabs.sales.saaa', 'ساعة') }}</strong>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.sales.athr_amwla_alryfyral', 'أثر عمولة الريفيرال') }}</h2>
        <div class="text-2xl font-extrabold">{{ $data['referral_impact'] }}</div>
        <p class="text-xs mt-1" style="color: var(--text-muted)">
            {!! strtr(setting('admin.stats.tabs.sales.bnsba_v1_mn_aliadadat', 'بنسبة :v1% من الإعدادات.'), [':v1' => e(rtrim(rtrim(number_format((float) setting('referral.commission_percent', 7), 2), '0'), '.'))]) !!}
        </p>
    </div>
</div>

{{-- لا استردادات هنا (19.4) — والبديل تصحيحات أخطاء تقنيّة موثّقة --}}
<p class="text-xs mt-3" style="color: var(--text-muted)">
    {{ setting('admin.stats.tabs.sales.mafysh_amwd_astrdadat_lan_mafysh_astrjaa', 'مافيش عمود «استردادات» — لأنّ مافيش استرجاع نقديّ أصلًا؛ اللي بيتسجّل هو تصحيح الأخطاء التقنيّة.') }}
</p>
