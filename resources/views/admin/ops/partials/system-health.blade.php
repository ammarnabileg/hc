{{-- حالة النظام كلّه = أسوأ مؤشّر فيه، فلا يُخفي المتوسّطُ عطبًا --}}
<div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <x-state-badge :state="$overall"
                       :label="match ($overall) { 'ok' => 'النظام سليم', 'warn' => 'محتاج نظرة', default => 'محتاج إجراء' }" />
        <span class="text-xs" style="color: var(--text-muted)">
            {{ $lastCheck ? 'آخر فحص: ' . \Illuminate\Support\Carbon::parse($lastCheck)->diffForHumans() : 'لسه مافيش فحص مسجَّل' }}
        </span>
    </div>

    @can('system_health.view')
        <form method="post" action="{{ route('admin.ops.system.health') }}">
            @csrf
            <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised); min-height: 44px">تشغيل فحص صحّة</button>
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
    العتبات كلّها من الإعدادات — عدّلها من
    <a class="underline" href="{{ route('admin.settings.index', ['tab' => 'backups']) }}">تاب النسخ وصحّة النظام</a>.
</p>
