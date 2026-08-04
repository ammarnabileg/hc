<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.users.nmw_altsjylat', 'نموّ التسجيلات') }}</h2>
        {{-- رسم SVG بأيدينا — ممنوع أيّ مكتبة خارجيّة --}}
        {!! $chart->line($data['growth'], $data['growth_prev']) !!}
        @if ($period['compare'])
            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.stats.tabs.users.alkht_almtqta_alftra_alsabqa', 'الخطّ المتقطّع = الفترة السابقة.') }}</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.users.qma_althwyl_wnqat_altsrb', 'قمع التحويل ونقاط التسرّب') }}</h2>
        {!! $chart->funnel($data['funnel']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.users.alahtfaz_cohorts', 'الاحتفاظ (Cohorts)') }}</h2>
        {!! $chart->heatmap($data['cohorts']['rows'], $data['cohorts']['row_labels'], $data['cohorts']['col_labels']) !!}
        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.stats.tabs.users.alnsba_mn_aad_mn_alfwj_fy_alshhr_dh', 'النسبة = مَن عاد من الفوج في الشهر ده.') }}</p>
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.users.altwzya_aljghrafy', 'التوزيع الجغرافيّ') }}</h2>
        {!! $chart->bars($data['geo']['rows'] ?? []) !!}
    </div>

    <div class="card p-4 lg:col-span-2">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.users.ansht_alawqat_ywm_saaa', 'أنشط الأوقات (يوم × ساعة)') }}</h2>
        <div style="overflow-x: auto">
            {!! $chart->heatmap($data['hours']['matrix'], $data['hours']['row_labels'], $data['hours']['col_labels']) !!}
        </div>
    </div>
</div>
