<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.training.alitmamat_abr_alftra', 'الإتمامات عبر الفترة') }}</h2>
        {!! $chart->line($data['series']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('admin.stats.tabs.training.alakthr_tathra_drop_off', 'الأكثر تعثّرًا (Drop-off)') }}</h2>
        {!! $chart->bars($data['dropoff']) !!}
        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.stats.tabs.training.tsjyl_bla_itmam_wdh_ally_byhtaj_tdkhl', 'تسجيلٌ بلا إتمام — وده اللي بيحتاج تدخّل.') }}</p>
    </div>
</div>
