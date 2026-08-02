@extends('exams.layouts.focus')

@section('title', setting('exams.labels.result', 'نتيجة الامتحان'))
@section('exam_title', $exam->title_ar)
@section('exam_meta', setting('exams.labels.result', 'نتيجة الامتحان'))

@section('content')
    @if ($timedOut)
        {{-- انتهاء الوقت ⟵ تسليم تلقائيّ برسالة واضحة (24.5) --}}
        <div class="card p-3 mb-4 flex items-center gap-2 text-sm">
            <x-state-badge state="warn" label="" />
            <span>{{ setting('exams.messages.time_up', 'خلص الوقت — سلّمنا إجاباتك تلقائيًّا.') }}</span>
        </div>
    @endif

    <div class="card p-6 text-center animate-fadeup">
        <div class="flex justify-center mb-3">
            <x-state-badge :state="$attempt->passed ? 'ok' : 'idle'"
                           :label="$attempt->passed ? setting('exams.labels.passed', 'ناجح') : setting('exams.labels.not_this_time', 'مش المرّة دي')" />
        </div>

        {{-- الرقم النهائيّ يظهر في كلّ الأحوال ولا يعلق العدّاد أبدًا (2.17-أ) --}}
        <div class="text-4xl font-extrabold" data-count-to="{{ (int) $attempt->score }}">{{ (int) $attempt->score }}</div>
        <p class="text-sm mt-1" style="color: var(--text-muted)">
            {{ setting('exams.labels.your_score', 'درجتك من 100') }} — {{ setting('exams.labels.pass_score', 'درجة النجاح') }} {{ $exam->pass_score }}%
        </p>

        <p class="mt-4 text-sm">
            @if ($attempt->passed)
                {{ str_replace('[الاسم]', $attempt->user->name, (string) setting('exams.messages.passed', 'مبروك يا [الاسم] 🎉 عدّيت الامتحان.')) }}
            @else
                {{-- الرسوب برسالة محايدة تشجّع ولا تعاتب (2.17-ج · 13.4-ق-و) --}}
                {{ setting('exams.messages.failed', 'مش المرّة دي. راجع الدروس وجرّب تاني — ومحاولتك الجاية متاحة حسب قواعد الامتحان.') }}
            @endif
        </p>

        @if ($attempt->passed && $certificate)
            <div class="card p-4 mt-5 text-start" style="background: var(--surface-sunken)">
                <div class="flex items-center gap-2 mb-2">
                    <x-state-badge state="honor" :label="setting('certificates.labels.issued', 'شهادتك صدرت')" />
                </div>
                <p class="text-sm" style="color: var(--text-muted)">
                    {{ setting('exams.labels.unlocked', 'اللي فتحه نجاحك') }}:
                    {{ setting('certificates.labels.certificate', 'شهادة') }} <strong style="color: var(--text)">{{ $certificate->code }}</strong>
                </p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('learning.certificates') }}" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('certificates.labels.my_certificates', 'شهاداتي') }}</a>
                    <a href="{{ route('verify.certificate', ['code' => $certificate->code]) }}"
                       class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface)">
                        {{ setting('certificates.labels.verify_page', 'صفحة التحقّق') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="mt-5 flex flex-wrap justify-center gap-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('learning.courses') ? route('learning.courses') : url('/dashboard') }}"
               class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                {{ setting('exams.labels.back_to_learning', 'ارجع لتدريباتي') }}
            </a>
            @if (! $attempt->passed)
                <a href="{{ route('exams.start', $exam) }}" class="rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-sunken)">{{ setting('exams.labels.try_again', 'جرّب تاني') }}</a>
            @endif
        </div>
    </div>
@endsection

@if ($celebrate)
    @push('modals')
        {{-- احتفال ذروة عند إصدار الشهادة (2.14-3): كونفيتي CSS بلا أصول جديدة، ويُغلَق بضغطة أو ESC --}}
        <div id="peak" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgb(0 0 0 / .7)">
            <div class="card p-6 text-center max-w-md animate-fadeup">
                <div class="text-3xl mb-2" aria-hidden="true"><x-icon name="training" size="16" /></div>
                <h2 class="text-xl font-extrabold" style="color: var(--color-state-honor)">
                    {{ str_replace('[الاسم]', $attempt->user->name, (string) setting('celebrations.certificate.title', 'مبروك يا [الاسم]')) }}
                </h2>
                <p class="text-sm mt-2" style="color: var(--text-muted)">
                    {{ setting('celebrations.certificate.message', 'شهادتك الجديدة صدرت — تقدر تشاركها دلوقتي.') }}
                </p>
                <div class="flex flex-wrap justify-center gap-2 mt-4">
                    <a href="{{ route('learning.certificates') }}" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">{{ setting('certificates.labels.share', 'شارك شهادتك') }}</a>
                    <button type="button" data-peak-close class="rounded-xl px-4 py-2 text-sm motion-standard"
                            style="background: var(--surface-sunken)">{{ setting('celebrations.labels.close', 'تمام') }}</button>
                </div>
            </div>
            <div id="peak-confetti" class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true"></div>
        </div>
    @endpush

    @push('head')
        <style>
            @keyframes confetti-fall {
                from { transform: translateY(-10vh) rotate(0deg); opacity: 1; }
                to   { transform: translateY(110vh) rotate(720deg); opacity: 0; }
            }
            .confetti-piece {
                position: absolute; inline-size: 8px; block-size: 14px; border-radius: 2px;
                animation: confetti-fall linear forwards;
            }
            /* ⭐ الكونفيتي لا يُلغى بتفضيل نظام التشغيل: إلغاؤه كان يمحو
               ذروة 2.9-6 العاطفيّة بصمت. التحكّم من إعداد المستخدم (app.css). */
        </style>
    @endpush

    @push('scripts')
        <script>
        (() => {
            const peak = document.getElementById('peak');
            const stage = document.getElementById('peak-confetti');
            if (!peak || !stage) return;

            const colors = ['#00d4b8', '#d4af37', '#45ecd7', '#e8f5f2'];
            for (let i = 0; i < {{ (int) setting('celebrations.confetti.pieces', 80) }}; i++) {
                const piece = document.createElement('span');
                piece.className = 'confetti-piece';
                piece.style.insetInlineStart = Math.random() * 100 + 'vw';
                piece.style.background = colors[i % colors.length];
                piece.style.animationDuration = (2 + Math.random() * 2).toFixed(2) + 's';
                piece.style.animationDelay = (Math.random() * 0.6).toFixed(2) + 's';
                stage.appendChild(piece);
            }

            // لا تعطّل المستخدم: تُغلَق بضغطة أو ESC وتنتهي تلقائيًّا (2.14-ب)
            const close = () => peak.remove();
            peak.addEventListener('click', (e) => { if (e.target === peak || e.target.closest('[data-peak-close]')) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            setTimeout(close, {{ (int) setting('celebrations.peak.auto_dismiss_ms', 9000) }});
        })();
        </script>
    @endpush
@endif
