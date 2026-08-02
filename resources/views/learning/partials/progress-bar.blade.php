@php
    /**
     * بار التقدّم — تركوازيّ لأنّه عنصر منصّة لا حالة (2.16).
     * $percent · $label (اختياريّ) · $compact
     */
    $percent = max(0, min(100, (int) ($percent ?? 0)));
    $compact = $compact ?? false;
@endphp

<div class="w-full">
    @unless ($compact)
        <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-muted)">
            <span>{{ $label ?? setting('learning.progress.label') }}</span>
            <span class="font-semibold" style="color: var(--text)">{{ $percent }}%</span>
        </div>
    @endunless

    <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)"
         role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="{{ $label ?? setting('learning.progress.label') }}">
        <div class="h-full motion-standard" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
    </div>
</div>
