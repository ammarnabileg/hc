@extends('challenges.layouts.focus')
@section('title', $challenge->name_ar)

@section('content')
    @php
        $total = count($items);
        $startIndex = (int) ($participation->progress['index'] ?? 0);
        $startIndex = min(max(0, $startIndex), max(0, $total - 1));
    @endphp

    <header class="shrink-0 px-4 md:px-6 py-3" style="border-bottom: 1px solid var(--border)">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-bold truncate">{{ $challenge->name_ar }}</h1>
                <p class="text-xs" style="color: var(--text-muted)">
                    مهمّة <span data-current>{{ $startIndex + 1 }}</span> من {{ $total }}
                </p>
            </div>

            {{-- العدّاد التنازليّ — والوقت لمّا يخلص التسليم بيتم تلقائيًّا --}}
            <div class="text-left">
                <div class="text-xl font-extrabold tabular-nums" data-countdown
                     style="color: var(--color-brand-400)">{{ $secondsLeft === null ? '∞' : gmdate('i:s', $secondsLeft) }}</div>
                <div class="text-[11px]" style="color: var(--text-muted)">باقي من وقتك</div>
            </div>
        </div>

        <div class="max-w-2xl mx-auto mt-3 h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
            <div class="h-full motion-standard" data-progress
                 style="width: {{ $total > 0 ? round(count($answers) / $total * 100) : 0 }}%; background: var(--color-brand-500)"></div>
        </div>
    </header>

    {{-- «شغلك محفوظ» عند انقطاع الشبكة (2.17-ب) --}}
    <div data-offline class="hidden px-4 md:px-6 pt-3">
        <div class="max-w-2xl mx-auto card p-3 text-sm flex items-center gap-2" role="status"
             style="border-color: var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>النت فصل — بس تقدّمك محفوظ، وأوّل ما يرجع هنكمّل من نفس المكان.</span>
        </div>
    </div>

    <main class="flex-1 px-4 md:px-6 py-6">
        <div class="max-w-2xl mx-auto">
            @if ($total === 0)
                <x-empty message="التحدّي ده لسّه مالوش مهامّ — سلّم وارجع بعدين."
                         action="سلّم" :href="route('challenges.mine')" />
            @else
                {{-- سؤال/مهمّة في المرّة الواحدة (24.5) --}}
                @foreach ($items as $item)
                    <section data-item="{{ $item['i'] }}" @class(['card p-5 md:p-6', 'hidden' => $item['i'] !== $startIndex])>
                        <h2 class="text-lg font-bold leading-relaxed">{{ $item['text'] }}</h2>

                        <div class="mt-5 space-y-2">
                            @if ($item['kind'] === 'mcq')
                                @foreach ($item['options'] as $optionIndex => $option)
                                    <button type="button"
                                            data-answer="{{ $item['i'] }}" data-value="{{ $optionIndex }}"
                                            @class(['w-full text-start rounded-xl px-4 py-3 text-sm motion-standard'])
                                            style="background: {{ (string) ($answers[$item['i']] ?? null) === (string) $optionIndex ? 'var(--color-brand-800)' : 'var(--surface-sunken)' }};
                                                   border: 1px solid {{ (string) ($answers[$item['i']] ?? null) === (string) $optionIndex ? 'var(--color-brand-500)' : 'var(--border)' }};
                                                   color: var(--text)">
                                        {{ $option }}
                                    </button>
                                @endforeach

                            @elseif ($item['kind'] === 'number')
                                <label class="block">
                                    <span class="block text-sm mb-1">تقديرك بالرقم{{ $item['unit'] ? ' ('.$item['unit'].')' : '' }}</span>
                                    <input type="number" step="any" inputmode="decimal"
                                           data-answer="{{ $item['i'] }}" data-input
                                           value="{{ $answers[$item['i']] ?? '' }}"
                                           class="w-full rounded-xl px-3 py-3 text-base"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                </label>

                            @else
                                {{-- مهمّة تركيز: العدّ مبني على الأمانة (15.3) --}}
                                <p class="text-xs mb-3" style="color: var(--text-muted)">
                                    التحدّي ده أمانة بينك وبين نفسك — سجّل اللي عملته بصدق.
                                </p>
                                <button type="button" data-answer="{{ $item['i'] }}" data-value="1"
                                        class="w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                                        style="background: {{ ($answers[$item['i']] ?? null) ? 'var(--color-brand-800)' : 'var(--surface-sunken)' }};
                                               border: 1px solid var(--border); color: var(--text)">
                                    خلّصتها ✓
                                </button>
                            @endif
                        </div>

                        {{-- «اتحفظ ✓» بجوار الحقل مع الحفظ التلقائيّ (2.17-ب) --}}
                        <p class="mt-4 text-xs h-4" data-saved="{{ $item['i'] }}" style="color: var(--color-state-ok)"></p>
                    </section>
                @endforeach

                <div class="flex items-center justify-between gap-3 mt-5">
                    <button type="button" data-prev
                            class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text)">السابق</button>

                    <button type="button" data-next
                            class="btn rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">التالي</button>
                </div>
            @endif

            <form method="post" action="{{ route('challenges.submit', $participation) }}" class="mt-6" data-submit-form>
                @csrf
                <input type="hidden" name="auto" value="0" data-auto-flag>
                <button type="submit" data-submit
                        @class(['w-full rounded-xl px-4 py-3 text-sm font-semibold motion-standard', 'hidden' => $total > 0 && $startIndex < $total - 1])
                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border)">
                    سلّم التحدّي
                </button>
            </form>
        </div>
    </main>

    @push('scripts')
        <script>
            (() => {
                const total = {{ $total }};
                const answerUrl = @json(route('challenges.answer', $participation));
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const items = [...document.querySelectorAll('[data-item]')];
                const progress = document.querySelector('[data-progress]');
                const currentLabel = document.querySelector('[data-current]');
                const submitBtn = document.querySelector('[data-submit]');
                const offline = document.querySelector('[data-offline]');
                const answered = new Set(@json(array_map('strval', array_keys($answers))));
                let index = {{ $startIndex }};

                const show = (i) => {
                    if (total === 0) return;
                    index = Math.min(Math.max(0, i), total - 1);
                    items.forEach((el) => el.classList.toggle('hidden', Number(el.dataset.item) !== index));
                    if (currentLabel) currentLabel.textContent = index + 1;
                    if (submitBtn) submitBtn.classList.toggle('hidden', index !== total - 1);
                };

                const paint = () => {
                    if (progress) progress.style.width = total ? `${Math.round((answered.size / total) * 100)}%` : '0%';
                };

                const pending = new Map();

                // Autosave: كلّ إجابة تُحفَظ لحظيًّا على السيرفر لا مع التسليم
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
                        if (note) note.textContent = 'اتحفظ ✓';
                        answered.add(String(i));
                        paint();
                    } catch {
                        // الانقطاع لا يعاقِب: نطمئنه ونحتفظ بالإجابة محلّيًّا لحدّ ما النت يرجع
                        offline?.classList.remove('hidden');
                        if (note) note.textContent = 'تقدّمك محفوظ — هنبعته أوّل ما النت يرجع';
                        pending.set(i, value);
                    }
                };

                const flush = () => {
                    pending.forEach((value, i) => { pending.delete(i); save(i, value); });
                };
                window.addEventListener('online', flush);

                document.addEventListener('click', (e) => {
                    const choice = e.target.closest('[data-answer][data-value]');
                    if (choice) {
                        const i = Number(choice.dataset.answer);
                        choice.parentElement.querySelectorAll('[data-value]').forEach((b) => {
                            b.style.background = 'var(--surface-sunken)';
                            b.style.borderColor = 'var(--border)';
                        });
                        choice.style.background = 'var(--color-brand-800)';
                        choice.style.borderColor = 'var(--color-brand-500)';
                        save(i, choice.dataset.value);
                        if (i < total - 1) setTimeout(() => show(i + 1), 250);
                    }
                    if (e.target.closest('[data-next]')) show(index + 1);
                    if (e.target.closest('[data-prev]')) show(index - 1);
                });

                document.querySelectorAll('[data-input]').forEach((input) => {
                    let timer = null;
                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => save(Number(input.dataset.answer), input.value), 500);
                    });
                });

                // العدّاد التنازليّ — وانتهاء الوقت يرسل التسليم التلقائيّ
                const el = document.querySelector('[data-countdown]');
                let left = @json($secondsLeft);
                if (el && left !== null) {
                    const tick = () => {
                        if (left <= 0) {
                            el.textContent = '00:00';
                            document.querySelector('[data-auto-flag]').value = '1';
                            document.querySelector('[data-submit-form]').submit();
                            return;
                        }
                        const m = String(Math.floor(left / 60)).padStart(2, '0');
                        const s = String(left % 60).padStart(2, '0');
                        el.textContent = `${m}:${s}`;
                        if (left <= 30) el.style.color = 'var(--color-state-danger)';
                        left -= 1;
                        setTimeout(tick, 1000);
                    };
                    tick();
                }

                paint();
                show(index);
            })();
        </script>
    @endpush
@endsection
