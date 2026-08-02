@extends('layouts.app')
@section('title', $lesson->title_ar)

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        $previousUrl = $neighbours['previous'] ? route('learning.lesson', [$course, $neighbours['previous']]) : null;
        $nextUrl = $neighbours['next'] ? route('learning.lesson', [$course, $neighbours['next']]) : null;
        $canComplete = ! $completed && $quiz_passed;
    @endphp

    <x-page-header :title="$lesson->title_ar"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => $course->name_ar, 'url' => route('learning.course', $course)],
                       ['label' => $lesson->title_ar],
                   ]">
        <x-slot:action>
            {{-- [التالي] هو الفعل الرئيسيّ، وسهم [السابق] بجواره (24.5) --}}
            @if ($previousUrl)
                <a href="{{ $previousUrl }}" class="hidden md:inline-flex items-center rounded-xl px-3 py-2 text-sm motion-standard"
                   style="background: var(--surface-raised)" aria-label="{{ setting('learning.cta.previous') }}">
                    {{ setting('learning.cta.previous') }}
                </a>
            @endif
            @if ($nextUrl)
                <a href="{{ $nextUrl }}" class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.next') }}</a>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            {{-- المشغّل: iframe يوتيوب بلا أيّ SDK خارجيّ — أو النصّ/المستند --}}
            @if ($embed_url)
                <div class="card overflow-hidden">
                    <div style="position: relative; padding-block-end: 56.25%">
                        <iframe src="{{ $embed_url }}" title="{{ $lesson->title_ar }}"
                                style="position: absolute; inset: 0; width: 100%; height: 100%; border: 0"
                                loading="lazy" allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                </div>
            @elseif ($lesson->type === 'video')
                {{-- خطأ تحميل الفيديو: بديل نصّيّ بدل شاشة فارغة (24.5) --}}
                <div class="card p-4">
                    <p class="text-sm">{{ setting('learning.video.missing_message') }}</p>
                </div>
            @endif

            @if ($lesson->content)
                <article class="card p-5 leading-8 text-sm" data-lesson-content>
                    {!! nl2br(e($lesson->content)) !!}
                </article>
            @endif

            {{-- شريط تقدّم القراءة/المشاهدة داخل الدرس (24.5) --}}
            <div class="sticky-bar card p-3">
                @include('learning.partials.progress-bar', [
                    'percent' => 0,
                    'label' => setting('learning.lesson.read_progress_label'),
                    'compact' => true,
                ])
            </div>

            {{-- المرفقات القابلة للتحميل (3) --}}
            @if ($attachments->isNotEmpty())
                <section class="card p-4">
                    <h2 class="font-bold mb-3">{{ setting('learning.attachments.title') }}</h2>
                    <ul class="space-y-2">
                        @foreach ($attachments as $file)
                            <li>
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk($file->disk)->url($file->path) }}"
                                   class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm motion-standard"
                                   style="background: var(--surface-sunken)" download>
                                    <span aria-hidden="true">{{ setting('learning.icon.attachment') }}</span>
                                    <span class="flex-1 min-w-0 truncate">{{ $file->name }}</span>
                                    <span class="text-xs" style="color: var(--text-muted)">
                                        {{ $file->size ? round($file->size / 1024) . ' ' . setting('learning.attachments.size_unit') : '' }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- بلوك أسئلة الدرس — وهو بوّابة الانتقال لا مجرّد إثراء (4.1) --}}
            @if ($questions->isNotEmpty())
                <section class="card p-4">
                    <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
                        <h2 class="font-bold">{{ setting('learning.questions.block_title') }}</h2>
                        <x-state-badge :state="$quiz_passed ? 'ok' : 'warn'"
                                       :label="$quiz_passed ? setting('learning.questions.passed_label') : setting('learning.questions.gate_label')" />
                    </div>

                    <p class="text-sm mb-3" style="color: var(--text-muted)">{{ setting('learning.questions.block_hint') }}</p>

                    <button type="button" data-modal-open="lesson-questions"
                            class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: {{ $quiz_passed ? 'var(--surface-sunken)' : 'var(--color-brand-500)' }};
                                   color: {{ $quiz_passed ? 'var(--text)' : '#04201c' }}">
                        {{ $quiz_passed ? setting('learning.questions.review_cta') : setting('learning.questions.open_cta') }}
                    </button>
                </section>
            @endif

            {{-- إكمال الدرس: سجلّ واحد لكلّ (مستخدم، درس) والقرار في الخادم --}}
            <section class="card p-4 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="font-bold">{{ setting('learning.lesson.complete_title') }}</h2>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        @if ($completed)
                            {{ setting('learning.lesson.already_done_message') }}
                        @elseif (! $quiz_passed)
                            {{ setting('learning.lock.quiz_reason') }}
                        @else
                            {{ setting('learning.xp.next_label') }} +{{ $next_xp }} {{ setting('learning.xp.suffix') }}
                        @endif
                    </p>
                </div>

                @if ($completed)
                    <x-state-badge state="ok" :label="setting('learning.lesson.done_badge')" />
                @elseif ($canComplete)
                    <form method="post" action="{{ route('learning.lesson.complete', [$course, $lesson]) }}">
                        @csrf
                        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('learning.lesson.complete_cta') }}
                        </button>
                    </form>
                @endif
            </section>
        </div>

        {{-- بانل قائمة الدروس قابل للطيّ — وعلى الموبايل Bottom Sheet (24.5) --}}
        <details class="lesson-panel lg:col-span-1" open>
            <summary class="card px-4 py-3 text-sm font-semibold cursor-pointer list-none flex items-center justify-between">
                <span>{{ setting('learning.lesson.panel_title') }}</span>
                <span class="text-xs" style="color: var(--text-muted)">{{ $outline['completed'] }}/{{ $outline['total'] }}</span>
            </summary>

            <div class="lesson-panel-body card p-3 mt-2 space-y-3">
                @include('learning.partials.progress-bar', ['percent' => $outline['percent'], 'compact' => true])

                @foreach ($outline['sections'] as $section)
                    <div>
                        <p class="text-xs font-bold mb-1" style="color: var(--text-muted)">{{ $section['title'] }}</p>
                        <ul class="space-y-1">
                            @foreach ($section['lessons'] as $row)
                                <li>
                                    @if ($row['unlocked'])
                                        <a href="{{ route('learning.lesson', [$course, $row['id']]) }}"
                                           class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm motion-standard"
                                           @style([
                                               'background: var(--surface-sunken)' => $row['id'] === $lesson->id,
                                           ])>
                                            <span aria-hidden="true">{{ $row['icon'] }}</span>
                                            <span class="flex-1 min-w-0 truncate">{{ $row['title'] }}</span>
                                            @if ($row['completed'])
                                                <span aria-label="{{ setting('learning.lesson.done_badge') }}">{{ setting('learning.icon.done') }}</span>
                                            @endif
                                        </a>
                                    @else
                                        <span class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm"
                                              style="color: var(--text-muted)" title="{{ $row['lock_reason'] }}">
                                            <span aria-hidden="true">{{ setting('learning.icon.lock') }}</span>
                                            <span class="flex-1 min-w-0 truncate">{{ $row['title'] }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </details>
    </div>
@endsection

@section('mobile_action')
    <div class="flex items-center gap-2">
        @if ($neighbours['previous'])
            <a href="{{ route('learning.lesson', [$course, $neighbours['previous']]) }}"
               class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm"
               style="background: var(--surface-raised)">{{ setting('learning.cta.previous') }}</a>
        @endif

        @if ($neighbours['next'])
            <a href="{{ route('learning.lesson', [$course, $neighbours['next']]) }}"
               class="btn flex-1 flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.cta.next') }}</a>
        @elseif ($canComplete)
            <form method="post" action="{{ route('learning.lesson.complete', [$course, $lesson]) }}" class="flex-1">
                @csrf
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('learning.lesson.complete_cta') }}</button>
            </form>
        @endif
    </div>
@endsection

@if ($questions->isNotEmpty())
    @push('modals')
        <x-modal id="lesson-questions" :title="setting('learning.questions.block_title')">
            @include('learning.partials.questions')
        </x-modal>
    @endpush
@endif

@push('scripts')
    <script>
        /* شريط تقدّم القراءة داخل الدرس — ويعرض 100% دائمًا عند بلوغ النهاية (2.17-أ) */
        (() => {
            const bar = document.querySelector('.sticky-bar [role="progressbar"] > div');
            const article = document.querySelector('[data-lesson-content]');
            if (!bar) return;

            const update = () => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                const ratio = max > 0 ? Math.min(1, window.scrollY / max) : 1;
                bar.style.width = Math.round(ratio * 100) + '%';
            };
            window.addEventListener('scroll', update, { passive: true });
            update();
            if (!article) bar.style.width = '100%';
        })();

        /* بانل الدروس: مفتوح على الديسكتوب، ومطويّ على الموبايل يفتح كـBottom Sheet (24.5) */
        (() => {
            const panel = document.querySelector('.lesson-panel');
            if (panel && window.matchMedia('(max-width: 767px)').matches) panel.open = false;
        })();

        /* خانات OTP: انتقال تلقائيّ بين الخانات — والفورم يعمل كاملًا بدونه */
        document.querySelectorAll('[data-otp-form] .otp-row').forEach((row) => {
            const boxes = [...row.querySelectorAll('.otp-box')];
            boxes.forEach((box, index) => {
                box.addEventListener('input', () => {
                    box.value = box.value.replace(/\D/g, '').slice(0, 1);
                    if (box.value && boxes[index + 1]) boxes[index + 1].focus();
                });
                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !box.value && boxes[index - 1]) boxes[index - 1].focus();
                });
            });
        });
    </script>
@endpush
