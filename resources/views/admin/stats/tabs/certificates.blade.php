{{--
    تاب «الشهادات» في الإحصائيّات (24.3-خامسًا · 12.8).

    مادّته من 12.2.2 حرفيًّا: «`reports_certificates.view` … تقرير الشهادات:
    **معدّل الإصدار · الإلغاءات · حسب الاعتماد**».
    و«المنتهية» لا تُجمَع مع «الملغاة» (13.4-ق): الأولى انقضى العمل بها والثانية
    تزويرٌ مثبَت — وجمعُهما في رقمٍ واحد اتّهامٌ لأصحاب الأولى.
--}}
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.certificates.chart.series', 'الإصدار عبر الفترة') }}</h2>
        {!! $chart->line($data['series'], $data['series_prev'] ?? []) !!}
        @if ($period['compare'])
            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('stats.compare.hint', 'الخطّ المتقطّع = الفترة السابقة.') }}</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.certificates.chart.types', 'حسب نوع الشهادة') }}</h2>
        {!! $chart->bars($data['types'] ?? []) !!}
    </div>

    {{-- «جدول تفصيليّ قابل للتصدير» لكلّ تابّ (24.3-خامسًا) --}}
    <div class="card p-4 lg:col-span-2">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.certificates.table.accreditations', 'حسب جهة الاعتماد') }}</h2>

        @if (empty($data['accreditations']))
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('stats.empty.message', 'لا بيانات في هذه الفترة — جرّب فترة أوسع') }}</p>
        @else
            <div style="overflow-x: auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="color: var(--text-muted)">
                            <th class="text-start p-2">{{ setting('stats.certificates.col.accreditation', 'جهة الاعتماد') }}</th>
                            <th class="text-start p-2">{{ setting('stats.certificates.col.issued', 'شهادات صادرة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['accreditations'] as $row)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-2">{{ $row['label'] }}</td>
                                <td class="p-2">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
