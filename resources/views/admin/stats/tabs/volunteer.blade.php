{{--
    تاب «التطوّع» في الإحصائيّات (24.3-خامسًا · 12.8).

    مادّته من 12.2.2 حرفيًّا: «`reports_volunteer.view` … تقرير التطوّع:
    **التسكين · المهامّ · SLA المستويات**» — والثلاثة هنا لا واحدٌ منها.
    ولافتاته كلّها إعدادات (2.13): الشاشة تُعاد تسميتها من اللوحة بلا نشر كود.
--}}
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.volunteer.chart.placement', 'طلبات التسكين عبر الفترة') }}</h2>
        {{-- رسم SVG بأيدينا — ممنوع أيّ مكتبة خارجيّة --}}
        {!! $chart->line($data['series'], $data['series_prev'] ?? []) !!}
        @if ($period['compare'])
            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('stats.compare.hint', 'الخطّ المتقطّع = الفترة السابقة.') }}</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.volunteer.chart.entities', 'المهامّ حسب الكيان') }}</h2>
        {!! $chart->bars($data['entities'] ?? []) !!}
    </div>

    {{-- «جدول تفصيليّ قابل للتصدير» لكلّ تابّ (24.3-خامسًا) --}}
    <div class="card p-4 lg:col-span-2">
        <h2 class="font-bold text-sm mb-2">{{ setting('stats.volunteer.table.sla', 'SLA مستويات التصعيد') }}</h2>

        @if (empty($data['sla']))
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('stats.empty.message', 'لا بيانات في هذه الفترة — جرّب فترة أوسع') }}</p>
        @else
            <div style="overflow-x: auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="color: var(--text-muted)">
                            <th class="text-start p-2">{{ setting('stats.volunteer.col.level', 'مستوى التصعيد') }}</th>
                            <th class="text-start p-2">{{ setting('stats.volunteer.col.closed', 'حالات مغلقة') }}</th>
                            <th class="text-start p-2">{{ setting('stats.volunteer.col.on_time', 'داخل النافذة') }}</th>
                            <th class="text-start p-2">{{ setting('stats.volunteer.col.rate', 'نسبة الالتزام %') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['sla'] as $row)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-2">{{ $row['level'] }}</td>
                                <td class="p-2">{{ $row['closed'] }}</td>
                                <td class="p-2">{{ $row['on_time'] }}</td>
                                <td class="p-2">{{ $row['rate'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
