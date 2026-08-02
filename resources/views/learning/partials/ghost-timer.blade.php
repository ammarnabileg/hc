@php
    /**
     * Ghost Timer 👻 (الدستور 6): المتدرّب في طرف والشبح في الطرف الآخر،
     * والشبح يقترب كلّما قرب الموعد، ومع الخطر يهتزّ المتدرّب.
     * التنفيذ CSS بالكامل — بلا مكتبات، ونسبة التقدّم محسوبة في الخادم.
     * $deadline = مخرَج DeadlineService
     */
    $s = state_color($deadline['state']);
    $elapsed = max(0, min(100, (int) $deadline['elapsed_percent']));
    $shake = $deadline['state'] === 'danger';
@endphp

<div class="card p-3" aria-live="polite">
    <div class="flex items-center justify-between gap-2 mb-2">
        <span class="text-xs" style="color: var(--text-muted)">{{ setting('learning.ghost.title') }}</span>
        @include('learning.partials.deadline-chip', ['deadline' => $deadline])
    </div>

    @if ($deadline['has_deadline'])
        <div class="ghost-track" style="--ghost-pos: {{ $elapsed }}%">
            <span class="ghost-hero {{ $shake ? 'ghost-shake' : '' }}" aria-hidden="true">{{ setting('learning.ghost.hero_glyph') }}</span>
            <span class="ghost-line" aria-hidden="true"></span>
            <span class="ghost-ghost" style="color: var(--color-state-{{ $s['color'] }})" aria-hidden="true">{{ setting('learning.ghost.glyph') }}</span>
        </div>

        {{-- بديل نصّيّ: المعنى لا يعتمد على الرسم ولا على اللون وحده --}}
        <p class="text-xs mt-2" style="color: var(--text-muted)">
            {{ setting('learning.ghost.hint') }} {{ $deadline['label'] }}
        </p>
    @else
        <p class="text-xs" style="color: var(--text-muted)">{{ setting('learning.deadline.none_label') }}</p>
    @endif
</div>
