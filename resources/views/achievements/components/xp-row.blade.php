@php
    /** صفّ الليدر بورد (7.3) — أفاتار بلا هالة، والمركز برقمه لا باللون وحده. */
    $pinned = $pinned ?? false;
    $isTop3 = $row['rank'] <= 3;
    $delta = (int) $row['delta'];
@endphp

<div class="card p-3 flex items-center gap-3"
     @style(['border-color: var(--color-brand-500)' => $pinned])>

    <span class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-xl text-sm font-extrabold tabular-nums"
          style="background: var(--surface-sunken); color: {{ $isTop3 ? 'var(--color-state-honor)' : 'var(--text-muted)' }}">
        {{ $isTop3 ? '★' : '' }}{{ $row['rank'] }}
    </span>

    <x-avatar :user="$row['user']" size="10" />

    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-semibold">
            {{ $row['user']->name }}
            @if ($pinned)<span class="text-xs font-normal" style="color: var(--color-brand-400)">— ده إنت</span>@endif
        </div>
        <div class="text-xs" style="color: var(--text-muted)">#{{ $row['user']->code }}</div>
    </div>

    <div class="text-end">
        <div class="text-sm font-extrabold tabular-nums">{{ number_format($row['xp']) }}</div>
        <div class="text-[11px]" style="color: var(--text-muted)">XP</div>
    </div>

    {{-- فرق الفترة: الرمز مع اللون دائمًا (2.16-ب) --}}
    <div class="w-16 text-end text-xs tabular-nums"
         style="color: {{ $delta > 0 ? 'var(--color-state-ok)' : 'var(--text-muted)' }}">
        {{ $delta > 0 ? '● +'.number_format($delta) : '○ 0' }}
    </div>
</div>
