<div class="card p-4">
    <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.wars.alakthr_laba', 'الأكثر لعبًا') }}</h2>
    {!! $chart->bars($data['top']) !!}
</div>
