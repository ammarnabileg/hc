{{--
  ⭐ لوحة مصادر الاكتساب (21.2-ح): المصدر ⟵ التسجيل ⟵ التفعيل ⟵ الشراء بـUTM
  — فلا يُصرَف على قناةٍ لا نعرف عائدها.
--}}
<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.almsdr_utm_source', 'المصدر (utm_source)') }}</th>
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.zyarat', 'زيارات') }}</th>
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.tsjyl', 'تسجيل') }}</th>
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.tfayl', 'تفعيل') }}</th>
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.shra', 'شراء') }}</th>
                <th class="text-start p-3">{{ setting('admin.stats.tabs.acquisition.althwyl', 'التحويل') }}</th>
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
                    {{ $row['registered'] }} {!! strtr(setting('admin.stats.tabs.acquisition.tsjyl_v1_tfayl_v2_shra', 'تسجيل · :v1 تفعيل · :v2 شراء'), [':v1' => e($row['activated']), ':v2' => e($row['purchased'])]) !!}
                </div>
            </div>
        @endforeach
    </div>
</div>

@if (empty($data['rows']))
    <x-empty :message="setting('admin.stats.tabs.acquisition.mafysh_rwabt_mwswma_butm_fy_alftra_dy', 'مافيش روابط موسومة بـUTM في الفترة دي.')" />
@endif
