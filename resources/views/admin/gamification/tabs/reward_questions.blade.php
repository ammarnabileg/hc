{{--
  أسئلة المكافآت (12.10-أ): بنك منفصل بروابط مؤقّتة وتايمر نازل.
  الإجابة مخفيّة افتراضيًّا، والتصحيح Server-side — لا تُرسَل الإجابة لصفحة المتدرّب أبدًا.
--}}
@php($service = $data['service'])

<section class="card p-4 md:p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-1">
        <h2 class="font-bold">بنك أسئلة المكافآت</h2>
        <div class="flex items-center gap-2">
            @can('reward_questions.import')
                @if (setting('reward_questions.csv_import_enabled', true))
                    {{-- استيراد دفعة: الصفوف تدخل مسودّات فلا ينشر ملفٌّ سؤالًا بلا مراجعة --}}
                    <form method="post" action="{{ route('admin.gamification.reward-questions.import') }}"
                          enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="file" accept=".csv,text/csv" required
                               class="text-xs" style="color: var(--text-muted)">
                        <button type="submit" class="rounded-xl px-3 py-1.5 text-xs"
                                style="background: var(--surface-raised); color: var(--text)">استيراد CSV</button>
                    </form>
                @endif
            @endcan

            @can('reward_questions.create')
                <button type="button" data-modal-open="reward-question-modal"
                        class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">+ سؤال</button>
            @endcan
        </div>
    </div>
    <p class="text-xs mb-3" style="color: var(--text-muted)">
        لكلّ سؤال رابط مؤقّت + QR تشاركه مع المتدرّبين؛ وفوق السؤال تايمر نازل، وبعد انتهاء الوقت
        يقفل الرابط ويظهر «{{ setting('reward_questions.closed_text', 'انتهى وقت الإجابة') }}».
    </p>

    <div class="space-y-3">
        @forelse ($data['questions'] as $question)
            @php($state = $service->liveState($question))
            <article class="rounded-xl p-3" style="background: var(--surface-sunken)">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold text-sm">{{ \Illuminate\Support\Str::limit($question->prompt, 90) }}</div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            المكافأة: <strong>{{ $question->reward_xp }}</strong> XP ·
                            <strong>{{ $question->reward_tickets }}</strong> تذكرة ·
                            مدّة التفعيل: <strong>{{ $question->active_minutes }}</strong> دقيقة
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-state-badge :state="$state['state']" :label="$state['label']" />
                        @if ($state['open'] && $state['seconds_left'] !== null)
                            {{-- العدّاد النازل = ندرة صادقة تحفّز الإجابة الفوريّة (12.10-أ) --}}
                            <span class="text-xs font-mono" data-countdown="{{ $state['seconds_left'] }}"
                                  style="color: var(--text-muted)">{{ gmdate('H:i:s', $state['seconds_left']) }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex items-start gap-3 mt-3 flex-wrap">
                    <div class="shrink-0 rounded-lg overflow-hidden" style="border: 1px solid var(--border)">
                        {!! $service->qrSvg($question, 96) !!}
                    </div>

                    <div class="min-w-0 flex-1">
                        <label class="text-xs" style="color: var(--text-muted)">رابط السؤال</label>
                        <input type="text" readonly value="{{ $service->url($question) }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 text-xs font-mono" data-copy-source
                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">

                        <div class="flex items-center gap-3 mt-2 flex-wrap text-xs">
                            <a href="https://wa.me/?text={{ urlencode($service->shareText($question)) }}"
                               target="_blank" rel="noopener" class="underline">مشاركة واتساب</a>

                            <a href="{{ $service->url($question) }}" target="_blank" rel="noopener" class="underline">معاينة</a>

                            @can('reward_questions.view')
                                <a href="{{ route('admin.gamification.reward-questions.results', $question) }}" class="underline">النتائج</a>
                            @endcan

                            @can('reward_questions.edit')
                                <button type="button" class="underline" data-reward-question-edit
                                        data-id="{{ $question->id }}"
                                        data-prompt="{{ $question->prompt }}"
                                        data-type="{{ $question->type }}"
                                        data-options="{{ implode("\n", (array) $question->options) }}"
                                        data-answer="{{ $question->correct_answer }}"
                                        data-xp="{{ $question->reward_xp }}"
                                        data-tickets="{{ $question->reward_tickets }}"
                                        data-minutes="{{ $question->active_minutes }}"
                                        data-status="{{ $question->status }}">تعديل</button>

                                @if ($state['open'])
                                    <form method="post" action="{{ route('admin.gamification.reward-questions.close', $question) }}"
                                          onsubmit="return confirm('تقفل السؤال دلوقتي؟')">
                                        @csrf
                                        <button type="submit" class="underline" style="color: var(--color-state-danger)">إغلاق فوريّ</button>
                                    </form>
                                @endif
                            @endcan
                        </div>

                        @can('reward_questions.manage')
                            {{-- الإجابة مخفيّة افتراضيًّا ولا تظهر إلّا بفتحٍ صريح --}}
                            <details class="mt-2 text-xs">
                                <summary class="cursor-pointer" style="color: var(--text-muted)">إظهار الإجابة الصحيحة</summary>
                                <p class="mt-1 font-mono">{{ $question->correct_answer }}</p>
                            </details>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <x-empty message="{{ setting('reward_questions.empty_message', 'لا أسئلة مكافآت بعد.') }}" />
        @endforelse
    </div>
</section>

@can('reward_questions.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات أسئلة المكافآت',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_reward_questions'],
        'lockedKeys' => ['reward_questions.one_answer_per_user_locked', 'reward_questions.server_side_locked'],
        'open' => false,
    ])
@endcan

@push('modals')
    <x-modal id="reward-question-modal" title="سؤال مكافأة">
        <form method="post" action="{{ route('admin.gamification.reward-questions.save') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="id" id="rq-id">

            <label class="block text-sm">نصّ السؤال
                <textarea name="prompt" id="rq-prompt" rows="3" required
                          class="w-full rounded-lg px-3 py-2 mt-1"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">النوع
                    <select name="type" id="rq-type" class="w-full rounded-lg px-3 py-2 mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="choice">اختيارات</option>
                        <option value="text">إجابة نصّيّة</option>
                        <option value="number">إجابة رقميّة</option>
                    </select>
                </label>

                <label class="block text-sm">الحالة
                    <select name="status" id="rq-status" class="w-full rounded-lg px-3 py-2 mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">مسودّة</option>
                        <option value="published">منشور</option>
                        <option value="archived">مؤرشف</option>
                    </select>
                </label>
            </div>

            <label class="block text-sm">الاختيارات (سطر لكلّ اختيار — للنوع «اختيارات»)
                <textarea name="options" id="rq-options" rows="3"
                          class="w-full rounded-lg px-3 py-2 mt-1"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <label class="block text-sm">الإجابة الصحيحة (لا تظهر للمتدرّب أبدًا)
                <input type="text" name="correct_answer" id="rq-answer" required
                       class="w-full rounded-lg px-3 py-2 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <div class="grid grid-cols-3 gap-3">
                <label class="block text-sm">مكافأة XP
                    <input type="number" min="0" name="reward_xp" id="rq-xp" value="0"
                           class="w-full rounded-lg px-3 py-2 mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="block text-sm">مكافأة تذاكر
                    <input type="number" min="0" name="reward_tickets" id="rq-tickets" value="0"
                           class="w-full rounded-lg px-3 py-2 mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="block text-sm">مدّة التفعيل (دقيقة)
                    <input type="number" min="1" name="active_minutes" id="rq-minutes"
                           value="{{ setting('reward_questions.default_minutes', 60) }}"
                           class="w-full rounded-lg px-3 py-2 mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </div>

            <label class="block text-sm">جدولة الفتح (اتركه فارغًا ليفتح فور النشر)
                <input type="datetime-local" name="opens_at"
                       class="w-full rounded-lg px-3 py-2 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">احفظ السؤال</button>
        </form>
    </x-modal>
@endpush

@push('scripts')
    <script>
        // تعبئة الفورم للتعديل + عدّاد نازل — عرضٌ فقط، والقرار كلّه في الخادم
        document.querySelectorAll('[data-reward-question-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                const d = button.dataset;
                document.getElementById('rq-id').value = d.id;
                document.getElementById('rq-prompt').value = d.prompt;
                document.getElementById('rq-type').value = d.type;
                document.getElementById('rq-options').value = d.options;
                document.getElementById('rq-answer').value = d.answer;
                document.getElementById('rq-xp').value = d.xp;
                document.getElementById('rq-tickets').value = d.tickets;
                document.getElementById('rq-minutes').value = d.minutes;
                document.getElementById('rq-status').value = d.status;
                document.querySelector('[data-modal-open="reward-question-modal"]')?.click();
            });
        });

        document.querySelectorAll('[data-countdown]').forEach(function (node) {
            let left = parseInt(node.dataset.countdown, 10);
            setInterval(function () {
                if (left <= 0) { return; }
                left -= 1;
                const h = String(Math.floor(left / 3600)).padStart(2, '0');
                const m = String(Math.floor((left % 3600) / 60)).padStart(2, '0');
                const s = String(left % 60).padStart(2, '0');
                node.textContent = h + ':' + m + ':' + s;
            }, 1000);
        });

        document.querySelectorAll('[data-copy-source]').forEach(function (input) {
            input.addEventListener('focus', function () { input.select(); });
        });
    </script>
@endpush
