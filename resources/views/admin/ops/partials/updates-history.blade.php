@can('updates.manage')
    <form method="post" action="{{ route('admin.ops.updates.version') }}" class="card p-4 mb-4 grid gap-3 md:grid-cols-[160px_1fr_auto] md:items-end">
        @csrf
        <x-form.input name="version" :label="setting('admin.ops.partials.updates_history.alisdar_aljdyd', 'الإصدار الجديد')" :value="$version" :hint="setting('admin.ops.partials.updates_history.bsygha_1_4_2', 'بصيغة 1.4.2')" />
        <x-form.input name="notes" :label="setting('admin.ops.partials.updates_history.mlahzat_alisdar', 'ملاحظات الإصدار')" :hint="setting('admin.ops.partials.updates_history.str_ywdh_iyh_ally_atghyr', 'سطر يوضّح إيه اللي اتغيّر.')" />
        <button class="rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
            {{ setting('admin.ops.partials.updates_history.tsjyl', 'تسجيل') }}
        </button>
        @if (setting('updates.forward_only', true))
            <p class="text-xs md:col-span-3" style="color: var(--text-muted)">{{ setting('admin.ops.partials.updates_history.alatjah_amama_fqt_mafysh_rjwa_lisdar_aqdm', 'الاتّجاه أمامًا فقط — مافيش رجوع لإصدار أقدم.') }}</p>
        @endif
    </form>
@endcan

@if (! $history || $history->isEmpty())
    <x-empty :message="setting('admin.ops.partials.updates_history.mafysh_isdarat_msjla_lsh_sjl_isdark_alhaly', 'مافيش إصدارات مسجّلة لسه — سجّل إصدارك الحاليّ.')" />
@else
    @can('version_history.export')
        <div class="mb-3">
            <a href="{{ route('admin.ops.updates.history.export') }}"
               class="rounded-xl px-3 py-2 text-sm inline-flex" style="background: var(--surface-raised)">{{ setting('admin.ops.partials.updates_history.tsdyr_csv', 'تصدير CSV') }}</a>
        </div>
    @endcan

    <div class="card overflow-hidden">
        <table class="hidden md:table w-full text-sm">
            <thead style="background: var(--surface-sunken)">
                <tr class="text-xs" style="color: var(--text-muted)">
                    <th class="text-start p-3">{{ setting('admin.ops.partials.updates_history.alisdar', 'الإصدار') }}</th>
                    <th class="text-start p-3">{{ setting('admin.ops.partials.updates_history.alnwa', 'النوع') }}</th>
                    <th class="text-start p-3">{{ setting('admin.ops.partials.updates_history.alhjrat', 'الهجرات') }}</th>
                    <th class="text-start p-3">{{ setting('admin.ops.partials.updates_history.mn_nfdh', 'مَن نفّذ') }}</th>
                    <th class="text-start p-3">{{ setting('admin.ops.partials.updates_history.altarykh', 'التاريخ') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $row)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="p-3 font-mono">{{ $row->version }}</td>
                        <td class="p-3">
                            <x-state-badge :state="match ($row->event) { 'migrate' => 'ok', 'rollback' => 'danger', default => 'idle' }"
                                           :label="match ($row->event) { 'migrate' => setting('admin.ops.partials.updates_history.trhyl', 'ترحيل'), 'rollback' => setting('admin.ops.partials.updates_history.astrjaa', 'استرجاع'), default => setting('admin.ops.partials.updates_history.isdar', 'إصدار') }" />
                        </td>
                        <td class="p-3">{{ $row->migrations_count }}</td>
                        <td class="p-3">{{ $row->performer_name ?? '—' }}</td>
                        <td class="p-3 text-xs" title="{{ $row->performed_at }}">
                            {{ $row->performed_at ? \Illuminate\Support\Carbon::parse($row->performed_at)->diffForHumans() : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- الموبايل: كروت رأسيّة بأهمّ 3 حقول (2.15-ج) --}}
        <div class="md:hidden">
            @foreach ($history as $row)
                <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-semibold">{{ $row->version }}</span>
                        <x-state-badge :state="match ($row->event) { 'migrate' => 'ok', 'rollback' => 'danger', default => 'idle' }"
                                       :label="match ($row->event) { 'migrate' => setting('admin.ops.partials.updates_history.trhyl', 'ترحيل'), 'rollback' => setting('admin.ops.partials.updates_history.astrjaa', 'استرجاع'), default => setting('admin.ops.partials.updates_history.isdar', 'إصدار') }" />
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $row->performer_name ?? '—' }} ·
                        {{ $row->performed_at ? \Illuminate\Support\Carbon::parse($row->performed_at)->diffForHumans() : '—' }}
                    </div>
                    @if ($row->notes)
                        <p class="text-xs mt-1">{{ $row->notes }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-5">{{ $history->links() }}</div>
@endif
