{{--
  نظرة عامّة — تقسيمة حرفيّة من ملف الهويّة المرجعيّ (`dashboard()` في app.js):
  شريط KPI صفٌّ واحد مقسوم ← صفوف تدريباتٍ جارية (لا كروت شبكة) ← قسم
  «أقرب المواعيد» بجوار الشبح (Ghost Timer) في عمودين. لا تركيب من عندنا.
--}}

<div class="kpi-grid">
    @foreach ($kpis as $kpi)
        <div class="kpi">
            <div class="kpi-label">
                @if (is_string($kpi['icon']) && preg_match('/^[a-z][a-z0-9_-]*$/', $kpi['icon']))
                    <x-icon :name="$kpi['icon']" size="18" />
                @endif
                {{ $kpi['label'] }}
            </div>
            <div class="kpi-value" data-count-to="{{ $kpi['value'] }}">{{ $kpi['value'] }}</div>
            @if ($kpi['hint'])
                <span class="small muted">{{ $kpi['hint'] }}</span>
            @endif
            @if ($kpi['state'])
                <div class="mt-2"><x-state-badge :state="$kpi['state']" /></div>
            @endif
        </div>
    @endforeach
</div>

<section class="mt-8">
    <div class="spread mb-4">
        <h2>{{ setting('dashboard.overview.active_courses_title', 'تدريباتي الجارية') }}</h2>
        @if (\Illuminate\Support\Facades\Route::has('learning.courses'))
            <a href="{{ route('learning.courses') }}" class="btn text">
                {{ setting('dashboard.overview.all_courses_link', 'كلّ تدريباتي') }} <x-icon name="left" size="16" />
            </a>
        @endif
    </div>

    @if ($courses->isEmpty())
        <x-empty :message="setting('dashboard.overview.courses_empty_message', 'خلّصت كلّ تدريباتك الجارية، تحفة')"
                 :action="setting('dashboard.overview.courses_empty_action', 'تصفّح المتجر')"
                 :href="\Illuminate\Support\Facades\Route::has('store.index') ? route('store.index') : url('/')" />
    @else
        <div class="stagger">
            @foreach ($courses as $i => $row)
                @php $isLocked = false; @endphp
                <article class="course-row" style="--i: {{ $i }}">
                    <div class="thumb"><x-icon name="course" size="30" /></div>

                    <div class="min-w-0">
                        <h3 class="truncate">{{ $row['course']->name_ar }}</h3>
                        <p class="small truncate">
                            @if ($row['lesson_title'])
                                {{ $row['section_title'] }} ← {{ $row['lesson_title'] }}
                            @else
                                {{ setting('dashboard.course_card.all_lessons_done', 'خلّصت كلّ الدروس') }}
                            @endif
                        </p>
                        <div class="small muted mt-2">
                            {{ number_format($row['xp_earned']) }} XP ·
                            {{ str_replace([':done', ':total'], [$row['completed_lessons'], $row['total_lessons']], (string) setting('dashboard.course_card.lessons_progress', ':done/:total درس')) }}
                        </div>
                    </div>

                    <div class="course-progress">
                        <div class="spread small mb-2">
                            <span>{{ setting('dashboard.overview.progress_label', 'التقدم') }}</span>
                            <b>{{ (int) $row['percent'] }}%</b>
                        </div>
                        <div class="progress" role="progressbar" aria-valuenow="{{ (int) $row['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <span style="--value: {{ (int) $row['percent'] }}%"></span>
                        </div>
                    </div>

                    <div class="course-status">
                        <x-state-badge :state="$row['timer']->state" :label="$row['timer']->label" data-countdown="{{ $row['timer']->deadline?->toIso8601String() }}" />
                        <p class="small mt-2">
                            <x-state-badge :state="$row['exam']['state']" :label="$row['exam']['label']" />
                        </p>
                    </div>

                    <a href="{{ $row['next_lesson']['url'] ?? $row['course_url'] }}" class="icon-button course-open"
                       aria-label="{{ setting('dashboard.course_card.continue_action', 'إكمال') }} {{ $row['course']->name_ar }}">
                        <x-icon name="left" size="18" />
                    </a>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="mt-8">
    <div class="spread mb-4">
        <h2>{{ setting('dashboard.overview.deadlines_title', 'أقرب المواعيد') }}</h2>
        <span class="small muted">{{ setting('dashboard.overview.deadlines_hint', 'حسب توقيتك المحلي') }}</span>
    </div>

    @if ($deadlines->isEmpty())
        <div class="panel small muted">{{ setting('dashboard.overview.deadlines_empty', 'مفيش موعد قريب، خُد وقتك.') }}</div>
    @else
        <div class="grid2">
            <div class="stack" style="gap: 16px">
                @foreach ($deadlines as $row)
                    <div class="cluster">
                        <x-icon name="calendar" size="20" />
                        <div class="min-w-0">
                            <h3 class="truncate">{{ $row['course']->name_ar }}</h3>
                            <p class="small">{{ str_replace(':percent', $row['percent'], (string) setting('dashboard.overview.deadline_percent', ':percent% مكتمل')) }}</p>
                        </div>
                        <span class="small muted" style="margin-inline-start: auto" data-countdown="{{ $row['timer']->deadline?->toIso8601String() }}">{{ $row['timer']->label }}</span>
                    </div>
                @endforeach
            </div>
            <div>
                @include('learning.partials.ghost-timer', ['deadline' => $ghostDeadline])
            </div>
        </div>
    @endif
</section>
