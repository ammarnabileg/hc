@php
    /** صفّ في لوحة الأبطال — والأفاتار بلا هالة (24.5). */
    $pinned = $pinned ?? false;
    $isTop3 = $row['rank'] <= 3;
@endphp

<div @class(['card p-3 flex items-center gap-3'])
     @style([
         'border-color: var(--color-brand-500)' => $pinned,
         'background: var(--surface-raised)' => true,
     ])>

    {{-- المركز — وأوّل ثلاثة بتمييز هادئ (ذهبيّ للشرف لا كحالة تشغيليّة) --}}
    <span class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-xl text-sm font-extrabold tabular-nums"
          style="background: var(--surface-sunken); color: {{ $isTop3 ? 'var(--color-state-honor)' : 'var(--text-muted)' }}">
        {{ $isTop3 ? '★' : '' }}{{ $row['rank'] }}
    </span>

    <x-avatar :name="$row['name']" size="10" />

    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-semibold">
            {{ $row['name'] }}
            @if ($pinned)<span class="text-xs font-normal" style="color: var(--color-brand-400)">{{ setting('challenges.champion_row.you_label', '— ده إنت') }}</span>@endif
        </div>
        <div class="text-xs" style="color: var(--text-muted)">
            #{{ $row['code'] }} {{ str_replace([':wins', ':played'], [$row['wins'], $row['played']], (string) setting('challenges.champion_row.record', '· :wins فوز من :played')) }}
        </div>
    </div>

    <div class="text-end">
        <div class="text-sm font-extrabold tabular-nums">{{ (int) $row['points'] }}</div>
        <div class="text-[11px]" style="color: var(--text-muted)">{{ setting('challenges.champion_row.points_word', 'نقطة') }}</div>
    </div>
</div>
