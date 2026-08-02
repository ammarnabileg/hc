@php
    /**
     * الديدلاين بعدّاد ملوّن **برمزه** — ولا لون بلا رمز أبدًا (2.16-ب)،
     * وعلى الموبايل الرمز أهمّ من اللون فيظهر بجوار الرقم دائمًا.
     * $deadline = مخرَج DeadlineService
     */
    $s = state_color($deadline['state']);
    $full = $deadline['deadline_at']?->translatedFormat('l j F Y — H:i');
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs whitespace-nowrap"
      style="background: color-mix(in srgb, var(--color-state-{{ $s['color'] }}) 15%, transparent);
             color: var(--color-state-{{ $s['color'] }})"
      @if ($full) title="{{ $full }}" @endif>
    <span aria-hidden="true">{{ $deadline['icon'] }}</span>
    <span>{{ $deadline['label'] }}</span>
</span>
