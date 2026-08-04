<div class="card p-4">
    <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.engagement.twzya_almstwyat', 'توزيع المستويات') }}</h2>
    {!! $chart->bars($data['levels']) !!}
</div>
