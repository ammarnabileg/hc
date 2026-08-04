@php
    /**
     * عدّاد الديدلاين الملوّن — لون نافذة صاحبه (23-3.9-6):
     * أخضر متّسع · أصفر اقترب · أحمر فات. ومع اللون رمزٌ دائمًا (2.16-ب).
     *
     * الاستعمال: @include('volunteer.components.deadline-counter', ['task' => $task])
     *            @include('volunteer.components.deadline-counter', ['at' => $date, 'state' => 'warn'])
     */
    $dcTask = $task ?? null;
    $dcAt = $at ?? $dcTask?->deadline_at;
    $dcState = $state ?? ($dcTask ? app(\App\Services\Volunteer\Tasks\TaskWorkflow::class)->counterState($dcTask) : 'idle');
    $dcSymbols = state_color($dcState);
    $dcOutside = $dcAt && ! app(\App\Services\Volunteer\Tasks\ActivityWindow::class)->contains(\Illuminate\Support\Carbon::parse($dcAt));
@endphp

@if ($dcAt)
    @php $dcDate = \Illuminate\Support\Carbon::parse($dcAt); @endphp
    <span class="inline-flex items-center gap-1 text-xs cursor-help"
          style="color: var(--color-state-{{ $dcSymbols['color'] }})"
          title="{{ $dcDate->format('Y-m-d H:i') }}{{ $dcOutside ? ' — '.app(\App\Services\Volunteer\Tasks\ActivityWindow::class)->outsideHint() : '' }}">
        <span aria-hidden="true">{{ $dcSymbols['icon'] }}</span>
        {{-- تواريخ نسبيّة والتاريخ الكامل بالـHover (2.15-د) --}}
        <span>{{ $dcDate->diffForHumans(['options' => 0]) }}</span>
    </span>
@else
    <span class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.components_deadline_counter.text', 'بلا ديدلاين') }}</span>
@endif
