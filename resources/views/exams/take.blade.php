@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'exams.messages.autosaved' => (string) setting('exams.messages.autosaved', 'اتحفظ ✓'),
        'exams.messages.offline_short' => (string) setting('exams.messages.offline_short', 'إجاباتك محفوظة — هنبعتها أوّل ما الشبكة ترجع.'),
        'exams.messages.time_up' => (string) setting('exams.messages.time_up', 'خلص الوقت — سلّمنا إجاباتك تلقائيًّا.'),
    ]);
@endphp

@extends('exams.layouts.focus')

@section('title', $exam->title_ar)
@section('exam_title', $exam->title_ar)

@section('exam_meta')
    {{-- عدد الأسئلة ورقم الحاليّ (24.5) --}}
    @if ($review)
        {{ setting('exams.labels.review', 'مراجعة قبل التسليم') }} — {{ $answered }}/{{ $total }}
    @else
        {{ setting('exams.labels.question', 'سؤال') }} {{ $index }} {{ setting('exams.labels.of', 'من') }} {{ $total }}
        · {{ setting('exams.labels.answered', 'المُجاب') }} {{ $answered }}
    @endif
@endsection

@section('exam_timer')
    {{-- العدّاد التنازليّ — وانتهاء الوقت يعني تسليمًا تلقائيًّا برسالة واضحة (24.5) --}}
    <div class="text-left shrink-0">
        <div id="exam-timer" class="text-lg font-extrabold tabular-nums" data-seconds="{{ $secondsLeft }}"
             data-warn="{{ (int) setting('exams.timer.warn_seconds', 60) }}">--:--</div>
        <div class="text-xs" style="color: var(--text-muted)">{{ setting('exams.labels.time_left', 'الوقت المتبقّي') }}</div>
    </div>
@endsection

@section('content')
    {{-- شريط تقدّم بالأسئلة (2.10.1: التركوازيّ للأفعال والتقدّم لا للحالة) --}}
    <div class="rounded-full overflow-hidden mb-5" style="background: var(--surface-sunken); height: 8px">
        <div class="h-full motion-standard"
             style="width: {{ $total > 0 ? round(($answered / $total) * 100) : 0 }}%; background: var(--color-brand-500)"></div>
    </div>

    <form id="exam-form" method="post" action="{{ route('exams.submit', $exam) }}" data-answer-url="{{ route('exams.answer', $exam) }}">
        @csrf

        @if ($review)
            {{-- شاشة المراجعة قبل التسليم: كلّ الأسئلة وإجاباتها في فورم واحد --}}
            <div class="space-y-3">
                @foreach ($questions as $i => $q)
                    @php $value = $answers[(string) $q->id] ?? null; @endphp
                    <div class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('exams.labels.question', 'سؤال') }} {{ $i + 1 }}</p>
                                <p class="text-sm">{{ $q->prompt }}</p>
                            </div>
                            <div class="shrink-0">
                                <x-state-badge :state="($value === null || $value === '') ? 'warn' : 'ok'"
                                               :label="($value === null || $value === '') ? setting('exams.labels.unanswered', 'بلا إجابة') : setting('exams.labels.answered_one', 'مُجاب')" />
                            </div>
                        </div>

                        <div class="mt-3">
                            @include('exams.partials.question-input', ['q' => $q, 'value' => $value, 'otpLength' => $otp_lengths[$q->id] ?? 1])
                        </div>

                        <a href="{{ route('exams.take', ['exam' => $exam, 'q' => $i + 1]) }}"
                           class="inline-block mt-2 text-xs hover:underline" style="color: var(--color-brand-400)">
                            {{ setting('exams.labels.open_question', 'افتح السؤال') }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('exams.labels.submit', 'سلّم الامتحان') }}
                </button>
                <a href="{{ route('exams.take', ['exam' => $exam, 'q' => 1]) }}"
                   class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('exams.labels.back_to_questions', 'ارجع للأسئلة') }}
                </a>
            </div>
        @else
            {{-- سؤال واحد في المرّة (24.5) --}}
            <div class="card p-5 animate-fadeup">
                <p class="text-xs mb-2" style="color: var(--text-muted)">
                    {{ setting('exams.labels.question', 'سؤال') }} {{ $index }} {{ setting('exams.labels.of', 'من') }} {{ $total }}
                </p>
                <p class="text-base font-semibold mb-4">{{ $question->prompt }}</p>

                @include('exams.partials.question-input', ['q' => $question, 'value' => $answers[(string) $question->id] ?? null, 'otpLength' => $otp_lengths[$question->id] ?? 1])

                <p id="save-hint" class="mt-3 text-xs" style="color: var(--text-muted)"></p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if ($index > 1)
                    <a href="{{ route('exams.take', ['exam' => $exam, 'q' => $index - 1]) }}"
                       class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                        {{ setting('exams.labels.previous', 'السابق') }}
                    </a>
                @endif

                @if ($index < $total)
                    <a href="{{ route('exams.take', ['exam' => $exam, 'q' => $index + 1]) }}"
                       class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('exams.labels.next', 'التالي') }}
                    </a>
                @endif

                <a href="{{ route('exams.take', ['exam' => $exam, 'q' => 'review']) }}"
                   class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('exams.labels.go_review', 'مراجعة والتسليم') }}
                </a>
            </div>
        @endif
    </form>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('exam-form');
    if (!form) return;

    const hint = document.getElementById('save-hint');
    const banner = document.getElementById('network-banner');
    const url = form.dataset.answerUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const pending = new Map();

    const say = (text) => { if (hint) hint.textContent = text; };
    const offline = (on) => banner?.classList.toggle('hidden', !on);

    // حفظ تدريجيّ: «اتحفظ ✓» مع كلّ تغيير — ولو الشبكة اتقطعت الإجابة تفضل في الطابور (2.17-ب)
    async function save(questionId, value) {
        pending.set(questionId, value);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ question_id: questionId, value: value }),
            });
            const data = await response.json();

            // انتهى الوقت أثناء الكتابة: نروح لشاشة النتيجة برسالة واضحة
            if (response.status === 409 && data.expired && data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            if (!response.ok) throw new Error('save failed');
            pending.delete(questionId);
            offline(false);
            say(data.message || @json($hcWords['exams.messages.autosaved']));
        } catch (e) {
            offline(true);
            say(@json($hcWords['exams.messages.offline_short']));
        }
    }

    // خانة OTP واحدة لسؤالٍ رقميّ = قيمتها وحدها؛ عدّة خانات لنفس السؤال (OTP) تُجمَع رقمًا واحدًا (4)
    form.querySelectorAll('[data-question]').forEach((field) => {
        const handler = () => {
            const id = field.dataset.question;
            const group = form.querySelectorAll('[data-question="' + id + '"]');
            const value = group.length > 1
                ? Array.from(group).map((el) => el.value ?? '').join('')
                : (field.value ?? '');
            save(parseInt(id, 10), value);
        };
        field.addEventListener('change', handler);
        field.addEventListener('blur', handler);
    });

    // خانات OTP: انتقال تلقائيّ بين الخانات — والفورم يعمل كاملًا بدونه (4)
    form.querySelectorAll('.otp-row').forEach((row) => {
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

    // إعادة المحاولة أوّل ما الشبكة ترجع — ويُستأنف من مكانه
    window.addEventListener('online', () => {
        offline(false);
        pending.forEach((value, questionId) => save(questionId, value));
    });
    window.addEventListener('offline', () => offline(true));

    // العدّاد التنازليّ — وعند الصفر: تسليم تلقائيّ برسالة واضحة (24.5)
    const timer = document.getElementById('exam-timer');
    if (!timer) return;

    let left = parseInt(timer.dataset.seconds, 10) || 0;
    const warn = parseInt(timer.dataset.warn, 10) || 60;
    let submitted = false;

    const render = () => {
        const safe = Math.max(0, left);
        const m = String(Math.floor(safe / 60)).padStart(2, '0');
        const s = String(safe % 60).padStart(2, '0');
        timer.textContent = `${m}:${s}`;
        timer.style.color = safe <= warn ? 'var(--color-state-danger)' : 'var(--text)';
    };

    render();
    const tick = setInterval(() => {
        left -= 1;
        render();
        if (left <= 0 && !submitted) {
            submitted = true;
            clearInterval(tick);
            alert(@json($hcWords['exams.messages.time_up']));
            form.submit();
        }
    }, 1000);
})();
</script>
@endpush
