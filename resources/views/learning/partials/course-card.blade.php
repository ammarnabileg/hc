@php
    /**
     * كارت التدريب في «تدريباتي» (24.5): الغلاف · الاسم · بار التقدّم %
     * · القسم/الدرس الحاليّ · الديدلاين بعدّاد ملوّن برمزه · XP المكتسب
     * · شارة الامتحان · شارة الشهادة.
     * ⭐ ومنتهي الإتاحة يظهر بحالته وسبب القفل — لا يُخفى.
     */
    $course = $card['course'];
    $summary = $card['summary'];
    $availability = $card['availability'];
    $open = $availability['open'];
    $cover = $course->cover_path ? \Illuminate\Support\Facades\Storage::url($course->cover_path) : null;
    $statusLabel = [
        'not_started' => setting('learning.status.not_started'),
        'active' => setting('learning.status.active'),
        'completed' => setting('learning.status.completed'),
    ][$card['status']];
@endphp

<article class="card overflow-hidden animate-fadeup flex flex-col">
    <a href="{{ route('learning.course', $course) }}" class="block motion-standard hover:opacity-90">
        <div class="aspect-[16/9] w-full flex items-center justify-center"
             style="background: var(--surface-sunken)">
            @if ($cover)
                <img src="{{ $cover }}" alt="{{ $course->name_ar }}" class="w-full h-full object-cover" loading="lazy">
            @else
                <span class="text-3xl" aria-hidden="true">{{ setting('learning.icon.course') }}</span>
            @endif
        </div>
    </a>

    <div class="p-4 flex flex-col gap-3 flex-1">
        <div class="flex items-start justify-between gap-2">
            <a href="{{ route('learning.course', $course) }}" class="font-bold leading-6 hover:underline">
                {{ $course->name_ar }}
            </a>
            <x-state-badge :state="$card['status'] === 'completed' ? 'ok' : ($card['status'] === 'active' ? 'warn' : 'idle')"
                           :label="$statusLabel" />
        </div>

        @include('learning.partials.progress-bar', ['percent' => $summary['percent']])

        {{-- القسم/الدرس الحاليّ --}}
        @if ($summary['current_title'])
            <p class="text-xs truncate" style="color: var(--text-muted)">
                {{ $summary['section_title'] }} · {{ $summary['current_title'] }}
            </p>
        @endif

        {{-- سبب القفل مكتوب — المنتهي الإتاحة لا يُخفى (24.5) --}}
        @unless ($open)
            <p class="text-xs flex items-center gap-1" style="color: var(--color-state-{{ state_color($availability['state'])['color'] }})">
                <span aria-hidden="true">{{ setting('learning.icon.lock') }}</span>
                <span>{{ $availability['reason'] }}</span>
            </p>
        @endunless

        <div class="flex flex-wrap items-center gap-2 text-xs">
            @include('learning.partials.deadline-chip', ['deadline' => $card['deadline']])

            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                  style="background: var(--surface-sunken); color: var(--text-muted)">
                <span aria-hidden="true">{{ setting('learning.icon.xp') }}</span>
                <span>{{ (int) $card['enrollment']->xp_earned }} {{ setting('learning.xp.suffix') }}</span>
            </span>

            @if ($card['exam']['exists'])
                <x-state-badge :state="$card['exam']['state']" :label="$card['exam']['label']" />
            @endif

            @if ($card['certificate']['exists'])
                <x-state-badge :state="$card['certificate']['state']" :label="$card['certificate']['label']" />
            @endif
        </div>

        <div class="mt-auto pt-1">
            <a href="{{ route('learning.course', $course) }}"
               class="btn inline-flex items-center justify-center w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: {{ $open ? 'var(--color-brand-500)' : 'var(--surface-sunken)' }};
                      color: {{ $open ? '#04201c' : 'var(--text-muted)' }}">
                {{ $open ? setting('learning.cta.continue') : setting('learning.cta.view_state') }}
            </a>
        </div>
    </div>
</article>
