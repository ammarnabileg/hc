@php
    /**
     * صفّ الليدر بورد (7.3) — أفاتار بلا هالة، والمركز برقمه لا باللون وحده.
     *
     * ⭐ العمود الرئيسيّ صار **XP الفترة** لأنّ اللوحة تُرتَّب به الآن (7.3)،
     * والتراكميّ يبقى سياقًا تحته. و**الدولة تظهر في الصفّ** كما ينصّ الدستور.
     */
    $pinned = $pinned ?? false;
    $isTop3 = $row['rank'] <= 3;
    $delta = (int) $row['delta'];
    $country = $row['user']->country?->name_ar;
@endphp

<div class="card p-3 flex items-center gap-3"
     @style(['border-color: var(--color-brand-500)' => $pinned])>

    <span class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-xl text-sm font-extrabold tabular-nums"
          style="background: var(--surface-sunken); color: {{ $isTop3 ? 'var(--color-state-honor)' : 'var(--text-muted)' }}">
        {{ $row['rank'] }}
    </span>

    <x-avatar :user="$row['user']" size="10" />

    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-semibold">
            {{ $row['user']->name }}
            @if ($pinned)<span class="text-xs font-normal" style="color: var(--color-brand-400)">— {{ setting('leaderboard.you_label', 'ده إنت') }}</span>@endif
        </div>
        <div class="text-xs truncate" style="color: var(--text-muted)">
            {{ $country ? $country.' · ' : '' }}#{{ $row['user']->code }}
        </div>
    </div>

    <div class="text-end">
        <div class="text-sm font-extrabold tabular-nums">{{ number_format($delta) }}</div>
        <div class="text-[11px]" style="color: var(--text-muted)">{{ setting('leaderboard.xp_label', 'XP') }}</div>
    </div>

    {{-- الرصيد التراكميّ سياقًا — الرمز مع اللون دائمًا (2.16-ب) --}}
    <div class="w-20 text-end text-xs tabular-nums" style="color: var(--text-muted)"
         title="{{ setting('leaderboard.lifetime_label', 'الرصيد الكلّيّ') }}">
        ● {{ number_format((int) $row['xp']) }}
    </div>
</div>
