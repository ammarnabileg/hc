<div class="card p-4">
    <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.attendance.altsjyl_fy_alfaalyat', 'التسجيل في الفعاليّات') }}</h2>
    {!! $chart->line($data['series']) !!}
</div>
