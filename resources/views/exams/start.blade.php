@extends('exams.layouts.focus')

@section('title', $exam->title_ar)
@section('exam_title', $exam->title_ar)
@section('exam_meta', setting('exams.labels.before_start', 'قبل ما تبدأ — اطّلع على الشروط'))

@section('content')
    <div class="card p-5">
        <h2 class="font-bold mb-3">{{ setting('exams.labels.what_you_need', 'اللي محتاج تعرفه') }}</h2>
        <ul class="text-sm space-y-2" style="color: var(--text-muted)">
            <li>{{ setting('exams.labels.duration', 'مدّة الامتحان') }}: <strong style="color: var(--text)">{{ $exam->duration_minutes }} {{ setting('exams.labels.minute', 'دقيقة') }}</strong></li>
            <li>{{ setting('exams.labels.pass_score', 'درجة النجاح') }}: <strong style="color: var(--text)">{{ $exam->pass_score }}%</strong></li>
            <li>{{ setting('exams.labels.attempts', 'المحاولات') }}: <strong style="color: var(--text)">{{ $attemptsLeft }} {{ setting('exams.labels.of', 'من') }} {{ $exam->attempts_allowed }}</strong></li>
        </ul>
    </div>

    {{-- بوب-أب ما قبل البدء: المدّة · المحاولات · السعر بالكوينز والرصيد قبل/بعد + تأكيد (24.5) --}}
    @include('exams.partials.dialog', [
        'title' => setting('exams.labels.confirm_title', 'تأكيد دخول الامتحان'),
        'close' => null,
        'slot' => view('exams.partials.start-body', [
            'exam' => $exam,
            'price' => $price,
            'balance' => $balance,
            'balanceAfter' => $balanceAfter,
            'affordable' => $affordable,
            'attemptsLeft' => $attemptsLeft,
            'cooldownUntil' => $cooldownUntil,
            'expiringCertificates' => $expiringCertificates,
        ]),
        'footer' => view('exams.partials.start-footer', [
            'exam' => $exam,
            'affordable' => $affordable,
            'attemptsLeft' => $attemptsLeft,
            'cooldownUntil' => $cooldownUntil,
        ]),
    ])
@endsection
