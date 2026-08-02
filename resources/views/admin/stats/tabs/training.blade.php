<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">الإتمامات عبر الفترة</h2>
        {!! $chart->line($data['series']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">الأكثر تعثّرًا (Drop-off)</h2>
        {!! $chart->bars($data['dropoff']) !!}
        <p class="text-xs mt-1" style="color: var(--text-muted)">تسجيلٌ بلا إتمام — وده اللي بيحتاج تدخّل.</p>
    </div>
</div>
