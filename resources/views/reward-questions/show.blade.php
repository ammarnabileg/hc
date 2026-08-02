@extends('layouts.app')

@section('title', setting('reward_questions.page_title', 'سؤال المكافأة'))

@section('content')
    {{--
      سؤال المكافأة (12.10-أ): السؤال **وفوقه تايمر الديدلاين**.
      ⭐ الإجابة الصحيحة غير موجودة في هذه الصفحة إطلاقًا — التصحيح في الخادم وحده.
    --}}
    <x-page-header
        :title="setting('reward_questions.page_title', 'سؤال المكافأة')"
        :subtitle="setting('reward_questions.page_intro', 'جاوب صحّ قبل ما الوقت يخلص وتكسب مكافأتك فورًا.')" />

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm animate-fadeup">{{ session('status') }}</div>
    @endif

    <section class="card p-5">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <span class="flex items-center gap-2">
                <x-state-badge :state="$state['state']" :label="$state['label']" />
                <span class="text-xs" style="color: var(--text-muted)">
                    المكافأة: {{ $question->reward_xp }} XP · {{ $question->reward_tickets }} تذكرة
                </span>
            </span>

            @if ($state['open'] && $state['seconds_left'] !== null && setting('reward_questions.show_timer', true))
                {{-- تايمر نازل فوق السؤال: ندرة صادقة تحفّز الإجابة الفوريّة (2.9) --}}
                <span class="text-lg font-extrabold font-mono" data-countdown="{{ $state['seconds_left'] }}">
                    {{ gmdate('H:i:s', $state['seconds_left']) }}
                </span>
            @endif
        </div>

        <h2 class="text-lg font-bold mb-4">{{ $question->prompt }}</h2>

        @if ($mine)
            {{-- إجابة واحدة لكلّ مستخدم — ولا صرف مكرّر (12.10-أ) --}}
            <div class="rounded-xl p-4" style="background: var(--surface-sunken)">
                <p class="text-sm">{{ setting('reward_questions.already_message', 'جاوبت على السؤال ده قبل كده — مكافأتك اتصرفت مرّة واحدة.') }}</p>
                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    إجابتك: <strong>{{ $mine->answer }}</strong>
                    @if ($mine->is_correct)
                        · +{{ $mine->xp_awarded }} XP · +{{ $mine->tickets_awarded }} تذكرة
                    @endif
                </p>
            </div>
        @elseif (! $state['open'])
            <x-empty :message="setting('reward_questions.closed_text', 'انتهى وقت الإجابة')" />
        @else
            <form method="post" action="{{ route('reward-questions.answer', $question->token) }}" class="space-y-3">
                @csrf

                @if ($question->type === 'choice' && $options !== [])
                    <div class="space-y-2">
                        @foreach ($options as $option)
                            <label class="flex items-center gap-3 rounded-xl px-3 py-2 cursor-pointer"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border)">
                                <input type="radio" name="answer" value="{{ $option }}" required>
                                <span class="text-sm">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <label class="block text-sm">إجابتك
                        <input type="{{ $question->type === 'number' ? 'number' : 'text' }}" name="answer" required
                               step="any" class="w-full rounded-lg px-3 py-2 mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                @endif

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('reward_questions.submit_label', 'أرسل إجابتي') }}
                </button>
            </form>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        // العدّاد عرضٌ فقط: الإغلاق الحقيقيّ يقرّره الخادم لحظة الإرسال
        document.querySelectorAll('[data-countdown]').forEach(function (node) {
            let left = parseInt(node.dataset.countdown, 10);
            const tick = setInterval(function () {
                if (left <= 0) { clearInterval(tick); location.reload(); return; }
                left -= 1;
                const h = String(Math.floor(left / 3600)).padStart(2, '0');
                const m = String(Math.floor((left % 3600) / 60)).padStart(2, '0');
                const s = String(left % 60).padStart(2, '0');
                node.textContent = h + ':' + m + ':' + s;
            }, 1000);
        });
    </script>
@endpush
