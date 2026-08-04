{{-- حالة النظام كلّه = أسوأ مؤشّر فيه، فلا يُخفي المتوسّطُ عطبًا --}}
<div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <x-state-badge :state="$overall"
                       :label="match ($overall) { 'ok' => setting('admin.ops.partials.system_health.alnzam_slym', 'النظام سليم'), 'warn' => setting('admin.ops.partials.system_health.mhtaj_nzra', 'محتاج نظرة'), default => setting('admin.ops.partials.system_health.mhtaj_ijra', 'محتاج إجراء') }" />
        <span class="text-xs" style="color: var(--text-muted)">
            {{ $lastCheck ? setting('admin.ops.partials.system_health.akhr_fhs', 'آخر فحص: ') . \Illuminate\Support\Carbon::parse($lastCheck)->diffForHumans() : setting('admin.ops.partials.system_health.lsh_mafysh_fhs_msjl', 'لسه مافيش فحص مسجَّل') }}
        </span>
    </div>

    @can('system_health.view')
        <form method="post" action="{{ route('admin.ops.system.health') }}">
            @csrf
            <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised); min-height: 44px">{{ setting('admin.ops.partials.system_health.tshghyl_fhs_sha', 'تشغيل فحص صحّة') }}</button>
        </form>
    @endcan
</div>

{{-- كلّ مؤشّر بلون **ورمز** — واللون وحده لا يحمل المعنى (2.16-ب) --}}
<div class="grid gap-3 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
    @foreach ($report as $row)
        <div class="card p-4 animate-fadeup">
            <div class="flex items-start justify-between gap-2">
                <span class="text-sm" style="color: var(--text-muted)">{{ $row['label'] }}</span>
                <x-state-badge :state="$row['state']" label="" />
            </div>
            <div class="mt-2 text-sm font-bold break-words">{{ $row['value'] }}</div>
            <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $row['hint'] }}</div>

            @if ($row['key'] === 'disk' && isset($row['percent']))
                <div class="mt-3 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                    <div class="h-full" style="width: {{ min(100, (int) $row['percent']) }}%;
                        background: var(--color-state-{{ state_color($row['state'])['color'] }})"></div>
                </div>
            @endif
        </div>
    @endforeach
</div>

<p class="text-xs mt-4" style="color: var(--text-muted)">
    {{ setting('admin.ops.partials.system_health.alatbat_klha_mn_aliadadat_adlha_mn', 'العتبات كلّها من الإعدادات — عدّلها من') }}
    <a class="underline" href="{{ route('admin.settings.index', ['tab' => 'backups']) }}">{{ setting('admin.ops.partials.system_health.tab_alnskh_wsha_alnzam', 'تاب النسخ وصحّة النظام') }}</a>.
</p>
