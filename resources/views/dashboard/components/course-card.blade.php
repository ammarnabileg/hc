@php
    /**
     * كارت تدريب جارٍ (14-ب · 24.5): حلقة تقدّم · القسم/الدرس الحاليّ ·
     * Ghost Timer مصغّر بلون الحالة ورمزها · XP المكتسب · الامتحان والشهادة · [إكمال].
     */
    $timer = $row['timer'];
@endphp

<article class="card p-4 min-w-0 animate-fadeup">
    <div class="flex items-start gap-4">
        @include('dashboard.components.progress-ring', ['percent' => $row['percent'], 'size' => 68])

        <div class="min-w-0 flex-1">
            <a href="{{ $row['course_url'] }}" class="font-bold hover:underline block truncate">{{ $row['course']->name_ar }}</a>

            <p class="text-xs mt-1 truncate" style="color: var(--text-muted)">
                @if ($row['lesson_title'])
                    {{ $row['section_title'] }} ← {{ $row['lesson_title'] }}
                @else
                    خلّصت كلّ الدروس
                @endif
            </p>

            {{-- Ghost Timer مصغّر: لون الحالة ورمزها معًا (2.16-ب) --}}
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-state-badge :state="$timer->state" :label="$timer->label" />
                <span class="text-xs" style="color: var(--text-muted)">{{ $row['completed_lessons'] }}/{{ $row['total_lessons'] }} درس</span>
            </div>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken); color: var(--text-muted)">
            <x-icon name="xp" size="16" /> {{ number_format($row['xp_earned']) }} XP
        </span>
        <x-state-badge :state="$row['exam']['state']" :label="$row['exam']['label']" />
        <x-state-badge :state="$row['certificate']['state']" :label="$row['certificate']['label']" />
    </div>

    <a href="{{ $row['next_lesson']['url'] ?? $row['course_url'] }}"
       class="btn mt-4 w-full inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">إكمال</a>
</article>
