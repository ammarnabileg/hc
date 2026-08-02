{{--
  ⭐ لوحة مصادر الاكتساب (21.2-ح): المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء بـUTM
  — فلا يُصرَف على قناةٍ لا نعرف عائدها.
--}}
<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">المصدر (utm_source)</th>
                <th class="text-start p-3">زيارات</th>
                <th class="text-start p-3">تسجيل</th>
                <th class="text-start p-3">تفعيل</th>
                <th class="text-start p-3">شراء</th>
                <th class="text-start p-3">التحويل</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['rows'] as $row)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">{{ $row['source'] }}</td>
                    <td class="p-3">{{ $row['visits'] }}</td>
                    <td class="p-3">{{ $row['registered'] }}</td>
                    <td class="p-3">{{ $row['activated'] }}</td>
                    <td class="p-3">{{ $row['purchased'] }}</td>
                    <td class="p-3">{{ $row['registered'] > 0 ? round($row['purchased'] / $row['registered'] * 100, 1) : 0 }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="md:hidden">
        @foreach ($data['rows'] as $row)
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="font-semibold">{{ $row['source'] }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $row['registered'] }} تسجيل · {{ $row['activated'] }} تفعيل · {{ $row['purchased'] }} شراء
                </div>
            </div>
        @endforeach
    </div>
</div>

@if (empty($data['rows']))
    <x-empty message="مافيش روابط موسومة بـUTM في الفترة دي." />
@endif
