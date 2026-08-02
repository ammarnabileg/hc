@extends('layouts.app')
@section('title', setting('learning.quiz.title'))

@push('head')
    @include('learning.partials.styles')
@endpush

@section('content')
    @php
        /**
         * اختبار الدرس (4.1) — شاشة واحدة بثلاث مراحل:
         * الحلّ ⟵ معاينة الإجابات ⟵ النتيجة (الصحّ والغلط) ثمّ الإعادة بعد الانتظار.
         * وسؤال واحد لكلّ شاشة يعني هنا: مهمّة واحدة لكلّ مرحلة (2.15-أ-1).
         */
        $lessonUrl = route('learning.lesson', [$course, $lesson]);
        $correctCount = (int) ($attempt?->correct_count ?? 0);
        $totalCount = (int) ($attempt?->total_count ?? $questions->count());
    @endphp

    <x-page-header :title="setting('learning.quiz.title')"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => $course->name_ar, 'url' => route('learning.course', $course)],
                       ['label' => $lesson->title_ar, 'url' => $lessonUrl],
                       ['label' => setting('learning.quiz.title')],
                   ]">
        <x-slot:action>
            <a href="{{ $lessonUrl }}" class="hidden md:inline-flex items-center rounded-xl px-3 py-2 text-sm motion-standard"
               style="background: var(--surface-raised)">{{ setting('learning.quiz.back_to_lesson') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="max-w-2xl">
        {{-- ================================================= مرحلة الحلّ --}}
        @if ($stage === 'answer')
            <p class="text-sm mb-3" style="color: var(--text-muted)">{{ setting('learning.quiz.answer_hint') }}</p>

            <form id="quiz-form" method="post" action="{{ route('learning.lesson.quiz.preview', [$course, $lesson]) }}"
                  class="space-y-3" data-otp-form>
                @csrf

                @foreach ($questions as $index => $question)
                    <section class="card p-4">
                        <p class="text-xs mb-1" style="color: var(--text-muted)">
                            {{ setting('learning.quiz.question_label') }} {{ $index + 1 }}
                            {{ setting('learning.quiz.of_label') }} {{ $questions->count() }}
                        </p>
                        <p class="text-sm font-semibold mb-3">{{ $question->prompt }}</p>

                        @include('learning.partials.quiz-input')
                    </section>
                @endforeach

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('learning.quiz.preview_cta') }}
                </button>
            </form>

        {{-- =========================================== مرحلة المعاينة --}}
        @elseif ($stage === 'preview')
            <p class="text-sm mb-3" style="color: var(--text-muted)">{{ setting('learning.quiz.preview_hint') }}</p>

            <div class="space-y-3">
                @foreach ($questions as $index => $question)
                    @php $given = trim((string) ($answers[(string) $question->id] ?? '')); @endphp
                    <section class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs mb-1" style="color: var(--text-muted)">
                                    {{ setting('learning.quiz.question_label') }} {{ $index + 1 }}
                                </p>
                                <p class="text-sm">{{ $question->prompt }}</p>
                            </div>
                            <x-state-badge :state="$given === '' ? 'warn' : 'ok'"
                                           :label="$given === '' ? setting('learning.quiz.unanswered_label') : setting('learning.quiz.answered_label')" />
                        </div>

                        <p class="text-sm font-semibold mt-2 break-words">
                            {{ $given === '' ? setting('learning.quiz.no_answer_placeholder') : $given }}
                        </p>
                    </section>
                @endforeach
            </div>

            <div class="flex items-center gap-2 flex-wrap mt-4">
                <form id="quiz-submit-form" method="post" action="{{ route('learning.lesson.quiz.submit', [$course, $lesson]) }}">
                    @csrf
                    <button class="btn rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('learning.quiz.submit_cta') }}
                    </button>
                </form>

                <a href="{{ route('learning.lesson.quiz', [$course, $lesson]) }}"
                   class="rounded-xl px-4 py-3 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('learning.quiz.edit_cta') }}
                </a>
            </div>

        {{-- ============================================ مرحلة النتيجة --}}
        @else
            <section class="card p-5 mb-3">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="font-bold">
                            {{ ($attempt?->passed ?? $quiz_passed)
                                ? setting('learning.quiz.passed_title')
                                : setting('learning.quiz.failed_title') }}
                        </h2>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ setting('learning.quiz.score_label') }} {{ $correctCount }}/{{ $totalCount }}
                        </p>
                    </div>
                    <x-state-badge :state="($attempt?->passed ?? $quiz_passed) ? 'ok' : 'warn'"
                                   :label="($attempt?->passed ?? $quiz_passed)
                                       ? setting('learning.questions.passed_label')
                                       : setting('learning.quiz.retry_badge')" />
                </div>
            </section>

            <div class="space-y-3">
                @foreach ($questions as $index => $question)
                    @php $ok = (bool) ($results[$question->id] ?? false); @endphp
                    <section class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs mb-1" style="color: var(--text-muted)">
                                    {{ setting('learning.quiz.question_label') }} {{ $index + 1 }}
                                </p>
                                <p class="text-sm">{{ $question->prompt }}</p>
                                <p class="text-sm font-semibold mt-2 break-words">
                                    {{ trim((string) ($answers[(string) $question->id] ?? '')) ?: setting('learning.quiz.no_answer_placeholder') }}
                                </p>
                            </div>
                            {{-- الصحّ والغلط برمزٍ ونصّ لا بلونٍ وحده (2.16) --}}
                            <x-state-badge :state="$ok ? 'ok' : 'danger'"
                                           :label="$ok ? setting('learning.quiz.correct_label') : setting('learning.quiz.wrong_label')" />
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-4">
                @if ($attempt?->passed ?? $quiz_passed)
                    <a href="{{ $lessonUrl }}"
                       class="btn inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('learning.quiz.back_to_lesson') }}
                    </a>
                @elseif ($wait_seconds > 0)
                    {{-- حاجز الانتظار (4.1-4): زرّ الإعادة **يُخفى** ولا يُعطَّل، والخادم يرفض قبله --}}
                    <div class="card p-4 flex items-center gap-2" data-quiz-wait="{{ $wait_seconds }}"
                         data-quiz-retry-url="{{ route('learning.lesson.quiz', [$course, $lesson]) }}">
                        @include('learning.partials.icon', ['name' => 'clock', 'box' => 18])
                        <p class="text-sm">
                            {{ setting('learning.quiz.wait_message') }}
                            <span class="font-extrabold tabular-nums" data-quiz-countdown>{{ $wait_seconds }}</span>
                            {{ setting('learning.quiz.seconds_suffix') }}
                        </p>
                    </div>
                @else
                    <a href="{{ route('learning.lesson.quiz', [$course, $lesson]) }}"
                       class="btn inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('learning.quiz.retry_cta') }}
                    </a>
                @endif
            </div>
        @endif
    </div>
@endsection

@section('mobile_action')
    {{-- فعل رئيسيّ واحد في متناول الإبهام (2.15-ج) — ويختلف بحسب المرحلة --}}
    @if ($stage === 'answer')
        <button form="quiz-form" class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('learning.quiz.preview_cta') }}
        </button>
    @elseif ($stage === 'preview')
        <button form="quiz-submit-form" class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('learning.quiz.submit_cta') }}
        </button>
    @elseif (($attempt?->passed ?? $quiz_passed) || $wait_seconds <= 0)
        <a href="{{ ($attempt?->passed ?? $quiz_passed)
                ? route('learning.lesson', [$course, $lesson])
                : route('learning.lesson.quiz', [$course, $lesson]) }}"
           class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">
            {{ ($attempt?->passed ?? $quiz_passed)
                ? setting('learning.quiz.back_to_lesson')
                : setting('learning.quiz.retry_cta') }}
        </a>
    @endif
@endsection

@push('scripts')
    <script>
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

        /* عدّاد انتظار الإعادة (4.1): عرضٌ فقط — الحاجز الحقيقيّ في الخادم */
        (() => {
            const box = document.querySelector('[data-quiz-wait]');
            if (!box) return;

            const label = box.querySelector('[data-quiz-countdown]');
            let left = parseInt(box.dataset.quizWait, 10) || 0;

            const tick = setInterval(() => {
                left -= 1;
                if (label) label.textContent = Math.max(0, left);
                if (left <= 0) {
                    clearInterval(tick);
                    window.location.href = box.dataset.quizRetryUrl;
                }
            }, 1000);
        })();
    </script>
@endpush
