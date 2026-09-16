@extends('layouts.app')
@section('title', $path->name_ar)

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        $cover = $path->cover_path ? \Illuminate\Support\Facades\Storage::url($path->cover_path) : null;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
    @endphp

    <nav class="text-xs mb-2 flex flex-wrap items-center gap-1" style="color: var(--text-muted)">
        <a href="{{ route('learning.courses') }}" class="hover:underline">{{ setting('learning.breadcrumb.root') }}</a>
        <span aria-hidden="true">‹</span>
        <a href="{{ route('learning.paths') }}" class="hover:underline">{{ setting('learning.paths.title') }}</a>
        <span aria-hidden="true">‹</span>
        <span>{{ $path->name_ar }}</span>
    </nav>

    {{-- ⭐ لحظة الذروة إن جاءت من إكمال درس (2.14 · 4.1) --}}
    @if (session('celebration'))
        @include('learning.partials.celebration', ['celebration' => session('celebration')])
    @endif

    {{--
      ⭐ Hero حرفيًّا من ملف الهويّة (`pathPage()`): صورة محفورة خافتة (أو غلاف
      المسار الحقيقيّ إن وُجد) + عنوان + وصف، وتحته صفّ Pills (عدد التدريبات ·
      المدّة · الترتيب) وبار تقدّم المسار على الجهة الأخرى.
    --}}
    <section class="hero">
        <img class="hero-art" src="{{ $cover ?? asset('images/identity/editorial-engraving.webp') }}" alt="">
        <div class="hero-copy">
            <span class="eyebrow">{{ setting('learning.paths.title') }}</span>
            <h1>{{ $path->name_ar }}</h1>
            @if ($path->description_ar)
                <p>{{ $path->description_ar }}</p>
            @endif
        </div>
    </section>

    <div class="spread mb-8" style="gap: 24px; flex-wrap: wrap">
        <div class="cluster">
            <span class="pill"><x-icon name="course" size="14" /> {{ $progress['total'] }} {{ setting('learning.paths.courses_unit') }}</span>
            @if ($totalMinutes > 0)
                <span class="pill">
                    <x-icon name="clock" size="14" />
                    {{ $hours > 0 ? $hours.' '.setting('learning.paths.hours_suffix', 'ساعة') : '' }}
                    {{ $minutes > 0 ? $minutes.' '.setting('learning.lesson.minutes_suffix') : '' }}
                </span>
            @endif
            @if ($glimpse)
                <span class="pill">
                    <x-icon name="trophy" size="14" />
                    {{ setting('learning.paths.rank_label', 'ترتيبك') }} #{{ number_format($glimpse['rank']) }}
                    {{ setting('learning.paths.rank_of', 'من') }} {{ number_format($glimpse['total']) }}
                </span>
            @endif
        </div>
        <div style="width: 240px">
            <div class="spread small mb-2">
                <span>{{ setting('learning.paths.progress_label', 'إكمال المسار') }}</span>
                <b>{{ (int) $progress['percent'] }}%</b>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="{{ (int) $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <span style="--value: {{ (int) $progress['percent'] }}%"></span>
            </div>
            <span class="small muted">{{ $progress['completed'] }} / {{ $progress['total'] }} {{ setting('learning.paths.courses_unit') }}</span>
        </div>
    </div>

    <h2 class="mb-4">{{ setting('learning.paths.contents_title') }}</h2>

    {{-- Roadmap رأسيّ مرقّم — حرفيًّا من ملف الهويّة (`.roadmap`/`.road-row`) --}}
    <div class="roadmap">
        @foreach ($rows as $row)
            @php
                $course = $row['course'];
                $open = $row['availability']['open'];
                $courseCover = $course->cover_path ? \Illuminate\Support\Facades\Storage::url($course->cover_path) : null;
                // ⭐ محطّة فُتحت للتوّ ولم تُلمَس بعد ⟵ أنيميشن فتح المحطّة (3.4-23)
                $justUnlocked = $row['owned'] && $open && ! $row['completed'] && $row['summary']['percent'] === 0;
                $roadState = $row['completed'] ? 'done' : ((! $row['completed'] && $row['owned'] && $open) ? 'current' : '');
            @endphp

            <article class="road-row {{ $roadState }}"
                     data-done="{{ $row['completed'] ? 1 : 0 }}"
                     data-current="{{ $roadState === 'current' ? 1 : 0 }}"
                     data-unlocked="{{ $justUnlocked ? 1 : 0 }}">
                <span class="step">
                    @if ($row['completed'])
                        <x-icon name="check" size="16" />
                    @else
                        <bdi>{{ str_pad((string) $row['order'], 2, '0', STR_PAD_LEFT) }}</bdi>
                    @endif
                </span>

                <div class="min-w-40">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($row['owned'])
                            <a href="{{ route('learning.course', $course) }}" class="font-bold hover:underline">{{ $course->name_ar }}</a>
                        @else
                            <span class="font-bold">{{ $course->name_ar }}</span>
                        @endif

                        @if (! $row['completed'] && ! $row['owned'])
                            <x-state-badge state="idle" :label="setting('learning.paths.not_owned')" />
                        @endif

                        @if ($row['duration'] > 0)
                            <span class="small muted inline-flex items-center gap-1">
                                <x-icon name="clock" size="13" />
                                {{ $row['duration'] }} {{ setting('learning.lesson.minutes_suffix') }}
                            </span>
                        @endif
                    </div>

                    @if ($row['completed'])
                        <x-state-badge state="ok" :label="setting('learning.status.completed')" />
                    @endif

                    @if ($course->description_ar)
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($course->description_ar, 120) }}</p>
                        @endif

                        @if ($row['owned'])
                            <div class="mt-2">
                                @include('learning.partials.progress-bar', ['percent' => $row['summary']['percent'], 'compact' => true])
                            </div>

                            {{-- ⭐ موعد الاستكمال (تاريخ) على الكارت الجارٍ (3.3) --}}
                            @if (! $row['completed'] && $row['deadline'] && $row['deadline']['has_deadline'])
                                <p class="text-xs mt-2 inline-flex items-center gap-1"
                                   style="color: var(--color-state-{{ state_color($row['deadline']['state'])['color'] }})"
                                   title="{{ $row['deadline']['deadline_at']?->format('Y-m-d H:i') }}">
                                    <x-icon name="calendar" size="13" />
                                    <span>{{ setting('learning.paths.due_label', 'موعد الاستكمال') }}:
                                        {{ $row['deadline']['deadline_at']?->translatedFormat(setting('learning.paths.due_format', 'j F Y')) }}</span>
                                    {{-- ⭐ عدّاد تنازليّ إنلاين على الكارت (3.3) --}}
                                    <span class="tabular-nums" data-availability-countdown="{{ $row['deadline']['seconds_left'] }}"></span>
                                </p>
                            @endif

                            {{-- ⭐ مكافأة الإكمال المبكر إنلاين على الكارت (3.3 · 7 · 2.9-4) --}}
                            @if (! $row['completed'] && $row['reward'] && $row['reward']['xp'] > 0)
                                <p class="text-xs mt-1 inline-flex items-center gap-1" style="color: var(--color-brand-400)">
                                    <x-icon name="xp" size="13" />
                                    <span>{{ setting('learning.paths.early_reward_label', 'لو أنهيت درسًا دلوقتي') }}
                                        +{{ $row['reward']['xp'] }} {{ setting('learning.xp.suffix') }}
                                        · +{{ $row['reward']['tickets'] }} {{ setting('learning.tickets.suffix', 'تذكرة') }}</span>
                                </p>

                                {{-- ⭐ نصف المهلة **للتذاكر وحدها** (7 · 7.1): متى تنزل التذاكر — تأطير خسارةٍ صادق (2.9-4) --}}
                                @if ($row['reward']['before_half'] && $row['reward']['half_at'])
                                    <p class="text-xs mt-0.5" style="color: var(--text-muted)">
                                        {{ setting('learning.paths.half_point_label', 'التذاكر بتنزل لواحدة بعد') }}
                                        <span class="tabular-nums" dir="ltr">{{ $row['reward']['half_at']->translatedFormat(setting('learning.paths.half_point_format', 'j M Y')) }}</span>
                                    </p>
                                @endif
                            @endif
                        @endif

                        {{-- ⭐ «انضم لـ N أكملوا هذا التدريب» (3.4-49) بحدّه الأدنى (2.9-7) --}}
                        @php $joined = app(App\Services\Engagement\SocialProof::class)->frame('course_completed', $row['social']['completed']); @endphp
                        @if ($joined['text'] !== '')
                            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $joined['text'] }}</p>
                        @endif

                        {{-- ⭐ تقدّم المدعوّين على المسار (3.4-48) --}}
                        @if ($row['friends'])
                            <p class="text-xs mt-1 flex items-center gap-2 flex-wrap" style="color: var(--text-muted)">
                                <x-icon name="people" size="13" />
                                @foreach ($row['friends'] as $friend)
                                    <span class="rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">
                                        {{ $friend['name'] }} · {{ $friend['percent'] }}%
                                    </span>
                                @endforeach
                            </p>
                        @endif

                        {{-- غير المتاح يظهر بحالته وسببه، لا يُخفى (24.5) --}}
                        @unless ($open)
                            <p class="text-xs mt-2 flex items-center gap-1"
                               style="color: var(--color-state-{{ state_color($row['availability']['state'])['color'] }})">
                                <x-icon name="lock" size="13" />
                                <span>{{ $row['availability']['reason'] }}</span>
                            </p>
                        @endunless
                    </div>

                @if ($row['owned'])
                    <div class="road-action">
                        <a href="{{ route('learning.course', $course) }}" class="btn {{ $open ? 'btn-p' : 'btn-g' }} inline-flex items-center justify-center">
                            {{ $row['completed'] ? setting('learning.cta.review') : ($open ? setting('learning.cta.continue') : setting('learning.cta.view_state')) }}
                        </a>

                        {{-- ⭐ زرّ «عرض الشهادة» على الكارت المكتمل (3.3) — وبعد صدورها فقط --}}
                        @if ($row['completed'] && $row['certificate']['exists'] && $row['certificate']['url'])
                            <a href="{{ $row['certificate']['url'] }}" class="btn text inline-flex items-center gap-1 mt-2">
                                <x-icon name="certificate" size="15" />
                                <span>{{ setting('learning.paths.certificate_cta', 'عرض الشهادة') }}</span>
                            </a>
                        @endif
                    </div>
                @else
                    <div class="road-action">
                        <x-state-badge state="idle" label="{{ setting('learning.paths.not_owned') }}" />
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    {{-- بلوك شهادة المسار — حرفيًّا من ملف الهويّة (spread + فاصل علويّ)، والزرّ بعد 100% فقط --}}
    <div class="spread mt-8 pt-6" style="border-top: 1px solid var(--line)">
        <div>
            <h3>{{ setting('learning.paths.certificate_block_title') }}</h3>
            <p class="small muted">
                {{ $progress['unlocked_exam'] ? setting('learning.paths.exam_ready_hint') : setting('learning.paths.exam_locked_hint') }}
            </p>
        </div>

        @if ($progress['unlocked_exam'] && $exam['exists'] && $exam['url'])
            <a href="{{ $exam['url'] }}" class="btn btn-p inline-flex items-center">
                {{ setting('learning.paths.exam_cta') }} · {{ $exam['price'] }} {{ setting('learning.coins.suffix') }}
            </a>
        @elseif ($certificate['exists'])
            <x-state-badge :state="$certificate['state']" :label="$certificate['label']" />
        @else
            <div style="width: 240px">
                <div class="progress" role="progressbar" aria-valuenow="{{ (int) $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="--value: {{ (int) $progress['percent'] }}%"></span>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    {{-- عدّادات الكروت التنازليّة — نفس السكربت الموحّد (5 · 6) --}}
    @include('learning.partials.clock-scripts')
@endpush
