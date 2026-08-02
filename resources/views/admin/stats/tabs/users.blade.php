<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">نموّ التسجيلات</h2>
        {{-- رسم SVG بأيدينا — ممنوع أيّ مكتبة خارجيّة --}}
        {!! $chart->line($data['growth'], $data['growth_prev']) !!}
        @if ($period['compare'])
            <p class="text-xs mt-1" style="color: var(--text-muted)">الخطّ المتقطّع = الفترة السابقة.</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">قمع التحويل ونقاط التسرّب</h2>
        {!! $chart->funnel($data['funnel']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">الاحتفاظ (Cohorts)</h2>
        {!! $chart->heatmap($data['cohorts']['rows'], $data['cohorts']['row_labels'], $data['cohorts']['col_labels']) !!}
        <p class="text-xs mt-1" style="color: var(--text-muted)">النسبة = مَن عاد من الفوج في الشهر ده.</p>
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">التوزيع الجغرافيّ</h2>
        {!! $chart->bars($data['geo']['rows'] ?? []) !!}
    </div>

    <div class="card p-4 lg:col-span-2">
        <h2 class="font-bold text-sm mb-2">أنشط الأوقات (يوم × ساعة)</h2>
        <div style="overflow-x: auto">
            {!! $chart->heatmap($data['hours']['matrix'], $data['hours']['row_labels'], $data['hours']['col_labels']) !!}
        </div>
    </div>
</div>
