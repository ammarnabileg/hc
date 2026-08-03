@extends('exams.layouts.focus')

@section('title', $exam->title_ar)
@section('exam_title', $exam->title_ar)
@section('exam_meta', setting('exams.labels.before_start', 'قبل ما تبدأ — اطّلع على الشروط'))

@php
    /*
     | ⭐ 4.2: «**بدون مدة انتظار** … **كل دخول = تذكرة**» — فالافتراضيّ **بلا سقف**،
     | و`$attemptsLeft === null` تعني بلا حدّ فلا تُقرأ صفرًا ولا تقفل الزرّ.
     | والسقف والانتظار يبقيان قابلَين لضبط المالك (2.13)، فإن ضبطهما ظهر أثرهما.
     */
    $unlimited = $attemptsLeft === null;
    $attemptsText = $unlimited
        ? setting('exams.labels.attempts_unlimited', 'بلا حدّ — كلّ دخول بتذكرة')
        : $attemptsLeft.' '.setting('exams.labels.of', 'من').' '.$attemptsLimit;
    $blocked = ! $affordable || (! $unlimited && $attemptsLeft < 1) || $cooldownUntil;
@endphp

@section('content')
    <div class="card p-5">
        <h2 class="font-bold mb-3">{{ setting('exams.labels.what_you_need', 'اللي محتاج تعرفه') }}</h2>
        <ul class="text-sm space-y-2" style="color: var(--text-muted)">
            <li>{{ setting('exams.labels.duration', 'مدّة الامتحان') }}:
                <strong style="color: var(--text)">{{ $exam->duration_minutes }} {{ setting('exams.labels.minute', 'دقيقة') }}</strong></li>
            <li>{{ setting('exams.labels.pass_score', 'درجة النجاح') }}:
                <strong style="color: var(--text)">{{ $exam->pass_score }}%</strong></li>
            <li>{{ setting('exams.labels.attempts', 'المحاولات المتبقّية') }}:
                <strong style="color: var(--text)">{{ $attemptsText }}</strong></li>
        </ul>
    </div>
@endsection

@push('modals')
    {{--
      بوب-أب ما قبل البدء (24.5): المدّة · عدد المحاولات · **السعر بالكوينز لامتحان المسار**
      والرصيد قبل/بعد + تأكيد. وهو ظاهرٌ بلا جافاسكربت كي لا تُقفَل الشاشة أبدًا.
    --}}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgb(0 0 0 / .55)"
         role="dialog" aria-modal="true" aria-labelledby="start-dialog-title">
        <div class="modal-shell card w-full max-w-lg animate-fadeup">
            <div class="modal-head px-5 py-4" style="border-bottom: 1px solid var(--border)">
                <h2 id="start-dialog-title" class="font-bold">{{ setting('exams.labels.confirm_title', 'تأكيد دخول الامتحان') }}</h2>
            </div>

            <div class="modal-body px-5 py-4 space-y-4">
                <dl class="text-sm space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <dt style="color: var(--text-muted)">{{ setting('exams.labels.duration', 'المدّة') }}</dt>
                        <dd class="font-semibold">{{ $exam->duration_minutes }} {{ setting('exams.labels.minute', 'دقيقة') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt style="color: var(--text-muted)">{{ setting('exams.labels.attempts', 'المحاولات المتبقّية') }}</dt>
                        <dd class="font-semibold">{{ $attemptsText }}</dd>
                    </div>

                    @if ($price > 0)
                        {{--
                          التكلفة بعملتها + الرصيد قبل/بعد (24.5):
                          امتحان التدريب **بتذكرة تُخصَم بمجرّد الدخول** (4.2)،
                          وامتحان شهادة المسار **بالكوينز** بسعره لكلّ مسار (16).
                        --}}
                        <div class="flex items-center justify-between gap-3">
                            <dt style="color: var(--text-muted)">{{ setting('exams.labels.price', 'سعر الدخول') }}</dt>
                            <dd class="font-semibold">{{ number_format($price) }} {{ $currencyLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt style="color: var(--text-muted)">{{ setting('exams.labels.balance_before', 'رصيدك قبل') }}</dt>
                            <dd class="font-semibold">{{ number_format($balance) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt style="color: var(--text-muted)">{{ setting('exams.labels.balance_after', 'رصيدك بعد') }}</dt>
                            <dd class="font-semibold" @class(['text-red-400' => ! $affordable])>{{ number_format($balanceAfter) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($expiringCertificates->isNotEmpty())
                    {{-- نصّ 13.4-ق-و المعتمَد: الشهادة القديمة تُسجَّل «منتهية» ولا تُمسَح --}}
                    <div class="card p-3 text-sm" style="background: var(--surface-sunken)">
                        <div class="flex items-center gap-2 mb-2"><x-state-badge state="warn" label="{{ setting('exams.labels.notice', 'انتبه') }}" /></div>
                        <p>{{ setting('exams.messages.reentry_notice', 'الامتحان ده بيثبت جاهزيّتك دلوقتي. أوّل ما تبدأ، شهادتك التأهيليّة القديمة هتتسجّل «منتهية» — مش هتتمسح، هتفضل في سجلّك بتاريخها، وبالنجاح هتصدرلك شهادة جديدة.') }}</p>
                    </div>
                @endif

                @if ($cooldownUntil)
                    <p class="text-sm" style="color: var(--text-muted)">
                        {{ setting('exams.labels.available_at', 'محاولتك الجاية متاحة') }}:
                        <strong style="color: var(--text)">{{ $cooldownUntil->format('Y/m/d — H:i') }}</strong>
                    </p>
                @elseif (! $unlimited && $attemptsLeft < 1)
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('exams.messages.no_attempts_left', 'خلصت محاولاتك في الامتحان ده.') }}</p>
                @elseif (! $affordable)
                    <p class="text-sm" style="color: var(--text-muted)">{{ $shortMessage }}</p>
                @endif
            </div>

            <div class="modal-head px-5 py-4 flex flex-wrap items-center gap-2" style="border-top: 1px solid var(--border)">
                @if (! $affordable && $canTopup && \Illuminate\Support\Facades\Route::has('wallet.topup'))
                    {{-- عدم كفاية الرصيد: [اشحن المحفظة] **داخل البوب-أب نفسه** بلا مغادرة الشاشة (24.5) --}}
                    <a href="{{ route('wallet.topup') }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('exams.labels.topup', 'اشحن المحفظة') }}
                    </a>
                @endif

                @unless ($blocked)
                    <form method="post" action="{{ route('exams.begin', $exam) }}">
                        @csrf
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('exams.labels.ready', 'أنا جاهز') }}
                        </button>
                    </form>
                @endunless

                <a href="{{ \Illuminate\Support\Facades\Route::has('learning.courses') ? route('learning.courses') : url('/dashboard') }}"
                   class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken); color: var(--text)">
                    {{ setting('exams.labels.not_now', 'مش دلوقتي') }}
                </a>
            </div>
        </div>
    </div>
@endpush
