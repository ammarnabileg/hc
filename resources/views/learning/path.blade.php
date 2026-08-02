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

    <x-page-header :title="$path->name_ar"
                   :subtitle="$path->description_ar"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => setting('learning.paths.title'), 'url' => route('learning.paths')],
                       ['label' => $path->name_ar],
                   ]" />

    {{-- ⭐ لحظة الذروة إن جاءت من إكمال درس (2.14 · 4.1) --}}
    @if (session('celebration'))
        @include('learning.partials.celebration', ['celebration' => session('celebration')])
    @endif

    {{-- ⭐ هيدر المسار (3.3): صورة غلاف + وصف + **إجماليّ مدّة المسار** --}}
    <div class="card overflow-hidden mb-4">
        <div class="aspect-[21/9] w-full flex items-center justify-center" style="background: var(--surface-sunken)">
            @if ($cover)
                <img src="{{ $cover }}" alt="{{ $path->name_ar }}" class="w-full h-full object-cover" loading="lazy">
            @else
                <span style="color: var(--text-muted)"><x-icon name="path" size="44" /></span>
            @endif
        </div>

        <div class="p-4">
            @include('learning.partials.progress-bar', ['percent' => $progress['percent']])

            <div class="flex flex-wrap items-center gap-3 mt-3 text-xs" style="color: var(--text-muted)">
                <span>{{ $progress['completed'] }} / {{ $progress['total'] }} {{ setting('learning.paths.courses_unit') }}</span>

                {{-- إجماليّ مدّة المسار = مجموع دقائق دروس تدريباته (3.3) --}}
                @if ($totalMinutes > 0)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="clock" size="14" />
                        <span>{{ setting('learning.paths.total_duration_label', 'إجماليّ المدّة') }}:
                            {{ $hours > 0 ? $hours.' '.setting('learning.paths.hours_suffix', 'ساعة') : '' }}
                            {{ $minutes > 0 ? $minutes.' '.setting('learning.lesson.minutes_suffix') : '' }}</span>
                    </span>
                @endif

                {{-- ⭐ لمحة ترتيبك على صفحة المسار (3.4-46) --}}
                @if ($glimpse)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="trophy" size="14" />
                        <span>{{ setting('learning.paths.rank_label', 'ترتيبك') }} #{{ number_format($glimpse['rank']) }}
                            {{ setting('learning.paths.rank_of', 'من') }} {{ number_format($glimpse['total']) }}</span>
                    </span>
                @endif
            </div>
        </div>
    </div>

    <h2 class="text-lg font-bold mb-3">{{ setting('learning.paths.contents_title') }}</h2>

    {{-- Roadmap رأسيّ مرقّم: كلّ محطّة = كارت تدريب بترتيبه المعتمَد (3.3) --}}
    <div class="roadmap space-y-3">
        @foreach ($rows as $row)
            @php
                $course = $row['course'];
                $open = $row['availability']['open'];
                $courseCover = $course->cover_path ? \Illuminate\Support\Facades\Storage::url($course->cover_path) : null;
                // ⭐ محطّة فُتحت للتوّ ولم تُلمَس بعد ⟵ أنيميشن فتح المحطّة (3.4-23)
                $justUnlocked = $row['owned'] && $open && ! $row['completed'] && $row['summary']['percent'] === 0;
            @endphp

            <article class="roadmap-node card p-4"
                     data-done="{{ $row['completed'] ? 1 : 0 }}"
                     data-current="{{ ! $row['completed'] && $row['owned'] && $open ? 1 : 0 }}"
                     data-unlocked="{{ $justUnlocked ? 1 : 0 }}">
                <div class="flex items-start gap-3 flex-wrap">
                    <span class="text-xs rounded-full px-2 py-0.5 shrink-0"
                          style="background: var(--surface-sunken); color: var(--text-muted)">{{ $row['order'] }}</span>

                    {{-- ⭐ غلاف كارت التدريب وعليه رقم المحطّة (3.3) --}}
                    <div class="relative w-24 h-16 shrink-0 rounded-xl overflow-hidden flex items-center justify-center"
                         style="background: var(--surface-sunken)">
                        @if ($courseCover)
                            <img src="{{ $courseCover }}" alt="{{ $course->name_ar }}" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <span style="color: var(--text-muted)"><x-icon name="course" size="22" /></span>
                        @endif
                        <span class="absolute bottom-1 start-1 rounded-md px-1.5 text-[11px] font-extrabold tabular-nums"
                              style="background: var(--surface); color: var(--text)">{{ $row['order'] }}</span>
                    </div>

                    <div class="flex-1 min-w-40">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($row['owned'])
                                <a href="{{ route('learning.course', $course) }}" class="font-bold hover:underline">{{ $course->name_ar }}</a>
                            @else
                                <span class="font-bold">{{ $course->name_ar }}</span>
                            @endif

                            @if ($row['completed'])
                                <x-state-badge state="ok" :label="setting('learning.status.completed')" />
                            @elseif (! $row['owned'])
                                <x-state-badge state="idle" :label="setting('learning.paths.not_owned')" />
                            @endif

                            @if ($row['duration'] > 0)
                                <span class="text-xs inline-flex items-center gap-1" style="color: var(--text-muted)">
                                    <x-icon name="clock" size="13" />
                                    {{ $row['duration'] }} {{ setting('learning.lesson.minutes_suffix') }}
                                </span>
                            @endif
                        </div>

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
                                        {{ $friend['name'] }} — {{ $friend['percent'] }}%
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
                        <div class="flex flex-col gap-2 shrink-0">
                            <a href="{{ route('learning.course', $course) }}"
                               class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                               style="background: {{ $open ? 'var(--color-brand-500)' : 'var(--surface-sunken)' }};
                                      color: {{ $open ? '#04201c' : 'var(--text-muted)' }}">
                                {{ $row['completed'] ? setting('learning.cta.review') : ($open ? setting('learning.cta.continue') : setting('learning.cta.view_state')) }}
                            </a>

                            {{-- ⭐ زرّ «عرض الشهادة» على الكارت المكتمل (3.3) — وبعد صدورها فقط --}}
                            @if ($row['completed'] && $row['certificate']['exists'] && $row['certificate']['url'])
                                <a href="{{ $row['certificate']['url'] }}"
                                   class="inline-flex items-center justify-center gap-1 rounded-xl px-4 py-2 text-sm motion-standard"
                                   style="background: var(--surface-sunken); color: var(--text)">
                                    <x-icon name="certificate" size="15" />
                                    <span>{{ setting('learning.paths.certificate_cta', 'عرض الشهادة') }}</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- بلوك شهادة المسار — والزرّ بعد 100% فقط، وقبلها بارٌ صامت (24.5) --}}
    <section class="card p-4 mt-5">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-bold">{{ setting('learning.paths.certificate_block_title') }}</h3>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $progress['unlocked_exam'] ? setting('learning.paths.exam_ready_hint') : setting('learning.paths.exam_locked_hint') }}
                </p>
            </div>

            @if ($progress['unlocked_exam'] && $exam['exists'] && $exam['url'])
                <a href="{{ $exam['url'] }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('learning.paths.exam_cta') }} — {{ $exam['price'] }} {{ setting('learning.coins.suffix') }}
                </a>
            @elseif ($certificate['exists'])
                <x-state-badge :state="$certificate['state']" :label="$certificate['label']" />
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    {{-- عدّادات الكروت التنازليّة — نفس السكربت الموحّد (5 · 6) --}}
    @include('learning.partials.clock-scripts')
@endpush
