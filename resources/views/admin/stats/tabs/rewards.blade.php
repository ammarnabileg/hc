{{--
    تاب «تقرير أثر المكافآت» في الإحصائيّات (24.3-خامسًا · 12.9 · 12.8).

    مادّته من 12.9 حرفيًّا: «**تقرير الأثر** (إجماليّ الممنوح/المخصوم لكلّ
    عملة في فترة) — ضمن الإحصائيّات (12.8)». 🔒 والتاب لمالك المنصّة وحده
    (`manual_rewards.export` owner_only في StatsService::tabGuards()).
--}}
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.rewards.chart.series', 'الحركة اليوميّة الصافية') }}</h2>
        {!! $chart->line($data['series'], $data['series_prev'] ?? []) !!}
        @if ($period['compare'])
            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('stats.compare.hint', 'الخطّ المتقطّع = الفترة السابقة.') }}</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.rewards.chart.granted', 'الممنوح حسب العملة') }}</h2>
        {!! $chart->bars(array_map(fn ($r) => ['label' => $r['label'], 'value' => $r['granted']], $data['currencies'] ?? [])) !!}
    </div>

    {{-- «جدول تفصيليّ قابل للتصدير» لكلّ تابّ (24.3-خامسًا) — إجماليّ الممنوح/المخصوم لكلّ عملة حرفيًّا (12.9) --}}
    <div class="card p-4 lg:col-span-2">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.rewards.table.currencies', 'إجماليّ الممنوح والمخصوم لكلّ عملة') }}</h2>

        @if (empty($data['currencies']))
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('stats.empty.message', 'لا بيانات في هذه الفترة — جرّب فترة أوسع') }}</p>
        @else
            <div style="overflow-x: auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="color: var(--text-muted)">
                            <th class="text-start p-2">{{ setting('stats.rewards.col.currency', 'العملة') }}</th>
                            <th class="text-start p-2">{{ setting('stats.rewards.col.granted', 'الممنوح') }}</th>
                            <th class="text-start p-2">{{ setting('stats.rewards.col.deducted', 'المخصوم') }}</th>
                            <th class="text-start p-2">{{ setting('stats.rewards.col.net', 'الصافي') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['currencies'] as $row)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-2">{{ $row['label'] }}</td>
                                <td class="p-2">{{ $row['granted'] }}</td>
                                <td class="p-2">{{ $row['deducted'] }}</td>
                                <td class="p-2">{{ $row['net'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
