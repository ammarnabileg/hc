@extends('challenges.layouts.focus')
@section('title', $challenge->name_ar)

@section('content')
    @php
        $total = count($items);
        $isSurvival = $match->war_type === 'survival';
        $startIndex = (int) ($side->progress['index'] ?? 0);
        $startIndex = min(max(0, $startIndex), max(0, $total - 1));
    @endphp

    <header class="shrink-0 px-4 md:px-6 py-3" style="border-bottom: 1px solid var(--border)">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-bold truncate">{{ $challenge->name_ar }}</h1>
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ str_replace(':rival', $rival?->name ?? setting('challenges.play.unknown_rival', 'محارب'), (string) setting('challenges.play.rival_line', 'خصمك: :rival')) }}
                    @if ($isSurvival)
                        {!! str_replace([':index', ':total'], ['<span data-current>'.($startIndex + 1).'</span>', $total], e(setting('challenges.play.question_of', '· سؤال :index من :total'))) !!}
                    @else
                        {!! str_replace([':count', ':total'], ['<span data-answered>'.count($answers).'</span>', $total], e(setting('challenges.play.answered_of', '· :count من :total'))) !!}
                    @endif
                </p>
            </div>

            @if ($isSurvival)
                {{-- مؤقّت السؤال: انتهاؤه = إجابة خاطئة (15.5) --}}
                <div class="text-left">
                    <div class="text-xl font-extrabold tabular-nums" data-qtimer
                         style="color: var(--color-brand-400)">{{ $questionSecondsLeft ?? '—' }}</div>
                    <div class="text-[11px]" style="color: var(--text-muted)">{{ setting('challenges.play.seconds_per_question', 'ثانية للسؤال') }}</div>
                </div>
            @endif
        </div>

        <div class="max-w-2xl mx-auto mt-3 h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
            <div class="h-full motion-standard" data-progress
                 style="width: {{ $total > 0 ? round(count($answers) / $total * 100) : 0 }}%; background: var(--color-brand-500)"></div>
        </div>
    </header>

    {{-- عدّاد الحسم يظهر للطرفين لمّا يخلّص أحدهما (15.1) --}}
    <div data-decision class="{{ $decisionSecondsLeft === null ? 'hidden' : '' }} px-4 md:px-6 pt-3">
        <div class="max-w-2xl mx-auto card p-3 text-sm flex items-center gap-2" role="status"
             style="border-color: var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>{!! str_replace(':seconds', '<b data-decision-left>'.(int) ($decisionSecondsLeft ?? 0).'</b>', e(setting('challenges.play.decision_note', 'خصمك خلّص — باقي :seconds ثانية وتُقفَل المواجهة.'))) !!}</span>
        </div>
    </div>

    {{-- «شغلك محفوظ» عند انقطاع الشبكة — والانقطاع لا يعاقِب (15.2-2) --}}
    <div data-offline class="hidden px-4 md:px-6 pt-3">
        <div class="max-w-2xl mx-auto card p-3 text-sm flex items-center gap-2" role="status"
             style="border-color: var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>{{ setting('challenges.play.offline_note', 'النت فصل — بس تقدّمك محفوظ، وأوّل ما يرجع هنكمّل من نفس المكان.') }}</span>
        </div>
    </div>

    <main class="flex-1 px-4 md:px-6 py-6">
        <div class="max-w-2xl mx-auto">
            @if ($total === 0)
                <x-empty :message="setting('challenges.play.no_questions', 'المواجهة دي بلا أسئلة — سلّم وارجع بعدين.')"
                         :action="setting('challenges.play.no_questions_action', 'سلّم')" :href="route('challenges.mine')" />
            @else
                <div class="space-y-4">
                    @foreach ($items as $item)
                        <section data-item="{{ $item['i'] }}"
                                 @class(['card p-5 md:p-6', 'hidden' => $isSurvival && $item['i'] !== $startIndex])>
                            <h2 class="text-lg font-bold leading-relaxed">
                                <span style="color: var(--text-muted)">{{ $item['i'] + 1 }}.</span> {{ $item['text'] }}
                            </h2>

                            <div class="mt-5 space-y-2">
                                @if ($item['kind'] === 'number')
                                    <label class="block">
                                        <span class="block text-sm mb-1">{{ setting('challenges.play.number_answer_label', 'تقديرك بالرقم') }}{{ $item['unit'] ? ' ('.$item['unit'].')' : '' }}</span>
                                        <input type="number" step="any" inputmode="decimal"
                                               data-answer="{{ $item['i'] }}" data-input
                                               value="{{ $answers[(string) $item['i']] ?? '' }}"
                                               class="w-full rounded-xl px-3 py-3 text-base"
                                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                                    </label>
                                @else
                                    @foreach ($item['options'] as $optionIndex => $option)
                                        @php $picked = (string) ($answers[(string) $item['i']] ?? '') === (string) $optionIndex; @endphp
                                        <button type="button"
                                                data-answer="{{ $item['i'] }}" data-value="{{ $optionIndex }}"
                                                class="w-full text-start rounded-xl px-4 py-3 text-sm motion-standard"
                                                style="min-height: 44px;
                                                       background: {{ $picked ? 'var(--color-brand-800)' : 'var(--surface-sunken)' }};
                                                       border: 1px solid {{ $picked ? 'var(--color-brand-500)' : 'var(--border)' }};
                                                       color: var(--text)">
                                            {{ $option }}
                                        </button>
                                    @endforeach
                                @endif
                            </div>

                            {{-- «اتحفظ ✓» بجوار الحقل مع الحفظ التلقائيّ (2.17-ب) --}}
                            <p class="mt-4 text-xs h-4" data-saved="{{ $item['i'] }}" style="color: var(--color-state-ok)"></p>
                        </section>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <form method="post" action="{{ route('challenges.submit', $match) }}" class="flex-1" data-submit-form>
                    @csrf
                    <button type="submit"
                            class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                        {{ setting('challenges.play.submit_action', 'خلّصت — سلّم') }}
                    </button>
                </form>

                {{-- الانسحاب إجراء متعمَّد وحده، وتكلفته معلَنة قبله (15.2-2) --}}
                <button type="button" data-modal-open="withdraw-{{ $match->id }}"
                        class="rounded-xl px-4 py-3 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                    {{ setting('challenges.play.withdraw_action', 'انسحاب') }}
                </button>
            </div>
        </div>
    </main>

    <x-modal :id="'withdraw-'.$match->id" :title="setting('challenges.play.withdraw_modal_title', 'متأكّد إنك عايز تنسحب؟')">
        <p class="text-sm">
            {!! str_replace(
                [':loss', ':penalty'],
                ['<b>'.e(setting('challenges.play.withdraw_loss_word', 'خسارة')).'</b>', '<b>'.e(setting('challenges.play.withdraw_penalty_word', 'عقوبة انسحاب')).'</b>'],
                e(setting('challenges.play.withdraw_modal_body', 'الانسحاب بيحسب عليك :loss وكمان :penalty — والخصم بيكسب المواجهة. لو النت بيقطع منك، مفيش داعي تنسحب: تقدّمك محفوظ وهيتحسب لوحده.')),
            ) !!}
        </p>
        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close
                        class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('challenges.play.withdraw_cancel', 'أكمّل المواجهة') }}</button>
                <form method="post" action="{{ route('challenges.withdraw', $match) }}">
                    @csrf
                    <button type="submit" class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text); min-height: 44px">{{ setting('challenges.play.withdraw_confirm', 'أنسحب') }}</button>
                </form>
            </div>
        </x-slot:footer>
    </x-modal>

    @push('scripts')
        @php
            // ردود الحفظ التلقائيّ من الإعدادات لا من السكربت (2.13)
            $playWords = [
                'saved' => (string) setting('challenges.play.answer_saved', 'اتحفظ ✓'),
                'queued' => (string) setting('challenges.play.answer_queued', 'تقدّمك محفوظ — هنبعته أوّل ما النت يرجع'),
            ];
        @endphp
        <script>
            (() => {
                const playWords = @json($playWords);
                const total = {{ $total }};
                const isSurvival = {{ $isSurvival ? 'true' : 'false' }};
                const answerUrl = @json(route('challenges.answer', $match));
                const stateUrl = @json(route('challenges.state', $match));
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const items = [...document.querySelectorAll('[data-item]')];
                const progress = document.querySelector('[data-progress]');
                const currentLabel = document.querySelector('[data-current]');
                const answeredLabel = document.querySelector('[data-answered]');
                const offline = document.querySelector('[data-offline]');
                const decision = document.querySelector('[data-decision]');
                const decisionLeft = document.querySelector('[data-decision-left]');
                const qtimer = document.querySelector('[data-qtimer]');
                const answered = new Set(@json(array_map('strval', array_keys($answers))));
                let index = {{ $startIndex }};

                const show = (i) => {
                    if (!isSurvival || total === 0) return;
                    index = Math.min(Math.max(0, i), total - 1);
                    items.forEach((el) => el.classList.toggle('hidden', Number(el.dataset.item) !== index));
                    if (currentLabel) currentLabel.textContent = index + 1;
                };

                const paint = () => {
                    if (progress) progress.style.width = total ? `${Math.round((answered.size / total) * 100)}%` : '0%';
                    if (answeredLabel) answeredLabel.textContent = answered.size;
                };

                const pending = new Map();

                // Autosave: كلّ إجابة تُحفَظ وتُصحَّح على الخادم لحظيًّا (15.1)
                const save = async (i, value) => {
                    const note = document.querySelector(`[data-saved="${i}"]`);
                    try {
                        const res = await fetch(answerUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                            body: JSON.stringify({ index: i, value }),
                        });
                        const data = await res.json();
                        if (data.redirect) return window.location.assign(data.redirect);
                        offline?.classList.add('hidden');
                        if (note) note.textContent = playWords.saved;
                        answered.add(String(i));
                        paint();
                        if (isSurvival && data.saved) { show(i + 1); resetQuestionTimer(); }
                    } catch {
                        // الانقطاع لا يعاقِب: نطمئنه ونحتفظ بالإجابة لحدّ ما النت يرجع
                        offline?.classList.remove('hidden');
                        if (note) note.textContent = playWords.queued;
                        pending.set(i, value);
                    }
                };

                const flush = () => { pending.forEach((value, i) => { pending.delete(i); save(i, value); }); };
                window.addEventListener('online', flush);

                document.addEventListener('click', (e) => {
                    const choice = e.target.closest('[data-answer][data-value]');
                    if (!choice) return;
                    const i = Number(choice.dataset.answer);
                    choice.parentElement.querySelectorAll('[data-value]').forEach((b) => {
                        b.style.background = 'var(--surface-sunken)';
                        b.style.borderColor = 'var(--border)';
                    });
                    choice.style.background = 'var(--color-brand-800)';
                    choice.style.borderColor = 'var(--color-brand-500)';
                    save(i, choice.dataset.value);
                });

                document.querySelectorAll('[data-input]').forEach((input) => {
                    let timer = null;
                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => save(Number(input.dataset.answer), input.value), 500);
                    });
                });

                // مؤقّت السؤال (البقاء) — والخادم هو الحكم، والعرض تنبيه فقط
                let qLeft = @json($questionSecondsLeft);
                const qMax = {{ (int) $questionSeconds }};
                const resetQuestionTimer = () => { qLeft = qMax; };
                if (qtimer && qLeft !== null) {
                    setInterval(() => {
                        qLeft = Math.max(0, qLeft - 1);
                        qtimer.textContent = qLeft;
                        qtimer.style.color = qLeft <= 5 ? 'var(--color-state-danger)' : 'var(--color-brand-400)';
                    }, 1000);
                }

                // نبض حالة المواجهة: عدّاد الحسم + إغلاق المواجهة — كلّه من الخادم
                setInterval(async () => {
                    try {
                        const res = await fetch(stateUrl, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (data.redirect) return window.location.assign(data.redirect);
                        if (data.decision_seconds !== null && decision) {
                            decision.classList.remove('hidden');
                            if (decisionLeft) decisionLeft.textContent = data.decision_seconds;
                        }
                        if (data.question_seconds !== null) qLeft = data.question_seconds;
                    } catch {
                        offline?.classList.remove('hidden');
                    }
                }, 3000);

                paint();
                show(index);
            })();
        </script>
    @endpush
@endsection
