@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'learning.lesson_view.js_1' => (string) setting('learning.lesson_view.js_1', 'اتحسبت المشاهدة ✓'),
        'learning.lesson_view.js_2' => (string) setting('learning.lesson_view.js_2', 'المشاهدة: '),
    ]);
@endphp

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

    {{-- ⭐ لحظة الذروة بعد إكمال الدرس السابق (2.14 · 4.1 · 3.4-18 · 3.4-22) --}}
    @if (session('celebration'))
        @include('learning.partials.celebration', ['celebration' => session('celebration')])
    @endif

    {{-- ⭐ شريط تقدّم لاصق أعلى التدريب (3.4-16) — موقعي لا يغيب وأنا داخل الدرس --}}
    <div class="sticky top-0 z-30 -mx-4 md:-mx-6 px-4 md:px-6 py-2 mb-4"
         style="background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border)">
        <div class="flex items-center gap-3">
            <span class="text-xs tabular-nums shrink-0" style="color: var(--text-muted)">
                {{ $outline['completed'] }}/{{ $outline['total'] }}
            </span>
            <span class="flex-1 h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                <span class="block h-full rounded-full motion-standard"
                      style="width: {{ $outline['percent'] }}%; background: var(--color-brand-500)"></span>
            </span>
            <span class="text-xs font-extrabold tabular-nums shrink-0">{{ $outline['percent'] }}%</span>
        </div>
    </div>

    <x-page-header :title="$lesson->title_ar"
                   :breadcrumbs="[
                       ['label' => setting('learning.breadcrumb.root'), 'url' => route('learning.courses')],
                       ['label' => $course->name_ar, 'url' => route('learning.course', $course)],
                       ['label' => $lesson->title_ar],
                   ]">
        <x-slot:action>
            {{-- ⭐ حفظ الدرس (3.4-34): حالة يقرّرها الخادم لا زينة في المتصفّح --}}
            @if (setting('learning.ux.bookmark_enabled', true))
                <form method="post" action="{{ route('learning.lesson.bookmark', [$course, $lesson]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-xl px-3 py-2 text-sm motion-standard"
                            style="background: var(--surface-raised); color: {{ $bookmarked ? 'var(--color-state-honor)' : 'var(--text-muted)' }}; min-block-size: 44px"
                            aria-label="{{ $bookmarked ? setting('learning.bookmark.remove', 'إزالة الحفظ') : setting('learning.bookmark.add', 'احفظ الدرس') }}">
                        <x-icon name="badge" size="16" />
                    </button>
                </form>
            @endif

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
                {{-- ⭐ تتبّع المشاهدة (4.1): التبليغ من المتصفّح والقرار في الخادم --}}
                <div class="card overflow-hidden" data-watch="{{ route('learning.lesson.watch', [$course, $lesson]) }}"
                     data-watch-duration="{{ (int) $lesson->duration_minutes * 60 }}"
                     data-watch-every="{{ (int) setting('learning.video.ping_seconds', 15) }}">
                    <div style="position: relative; padding-block-end: 56.25%">
                        <iframe src="{{ $embed_url }}" title="{{ $lesson->title_ar }}"
                                style="position: absolute; inset: 0; width: 100%; height: 100%; border: 0"
                                loading="lazy" allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                    <p class="px-4 py-2 text-xs" style="color: var(--text-muted)" data-watch-note></p>
                </div>
            @elseif ($lesson->type === 'video')
                {{-- خطأ تحميل الفيديو: بديل نصّيّ بدل شاشة فارغة (24.5) --}}
                <div class="card p-4">
                    <p class="text-sm">{{ setting('learning.video.missing_message') }}</p>
                </div>
            @endif

            {{-- قسم تعليقات الفيديو تحت المشغّل مباشرةً (3.1) --}}
            @if ($comments)
                @can('video_comments.view')
                    @include('learning.partials.comments')
                @endcan
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
                                    <x-icon name="attachment" size="15" style="color: var(--text-muted)" />
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
                @can('lesson_quiz.view')
                    <section class="card p-4">
                        <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
                            <h2 class="font-bold">{{ setting('learning.questions.block_title') }}</h2>
                            <x-state-badge :state="$quiz_passed ? 'ok' : 'warn'"
                                           :label="$quiz_passed ? setting('learning.questions.passed_label') : setting('learning.questions.gate_label')" />
                        </div>

                        <p class="text-sm mb-3" style="color: var(--text-muted)">{{ setting('learning.questions.block_hint') }}</p>

                        @if (! $quiz_passed && $quiz_wait_seconds > 0)
                            {{-- انتظار إعادة المحاولة (4.1-4): الزرّ يُخفى ولا يُعطَّل، والقرار في الخادم --}}
                            <p class="text-xs flex items-center gap-1" style="color: var(--text-muted)">
                                @include('learning.partials.icon', ['name' => 'clock'])
                                <span>{{ setting('learning.quiz.wait_message') }} {{ $quiz_wait_seconds }} {{ setting('learning.quiz.seconds_suffix') }}</span>
                            </p>
                        @else
                            <a href="{{ route('learning.lesson.quiz', [$course, $lesson]) }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                               style="background: {{ $quiz_passed ? 'var(--surface-sunken)' : 'var(--color-brand-500)' }};
                                      color: {{ $quiz_passed ? 'var(--text)' : '#04201c' }}">
                                {{ $quiz_passed ? setting('learning.questions.review_cta') : setting('learning.questions.open_cta') }}
                            </a>
                        @endif
                    </section>
                @endcan
            @endif

            {{-- ملاحظات التدريب: مساحة واحدة مشتركة يصلها من أيّ درس (3.2) --}}
            @if ($notes_enabled)
                @can('course_notes.view')
                    @include('learning.partials.notes')
                @endcan
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

                    {{-- ⭐ دليل اجتماعيّ حيّ تحت الدرس بحدّ 20 (2.9-7) — رقمٌ حقيقيّ،
                         وتحت الحدّ يتحوّل التأطير إلى **ريادة** بدل تضخيم عددٍ صغير --}}
                    <x-social-proof context="lesson" class="mt-2"
                        :count="app(App\Services\Engagement\SocialProof::class)->lessonFinishersToday($lesson->id, auth()->id())" />
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
                                            <x-icon :name="$row['icon']" size="15" style="color: var(--text-muted)" />
                                            <span class="flex-1 min-w-0 truncate">{{ $row['title'] }}</span>
                                            @if ($row['bookmarked'])
                                                <x-icon name="badge" size="14" :label="setting('learning.bookmark.saved_label', 'محفوظ')" style="color: var(--color-state-honor)" />
                                            @endif
                                            @if ($row['completed'])
                                                <x-icon name="check" size="15" :label="setting('learning.lesson.done_badge')" style="color: var(--color-state-ok)" />
                                            @endif
                                        </a>
                                    @else
                                        <span class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm"
                                              style="color: var(--text-muted)" title="{{ $row['lock_reason'] }}">
                                            <x-icon name="lock" size="15" />
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

@push('scripts')
    @if (! $completed)
        {{-- ⭐ إشعار «أكمل الدرس» لو خرج في النصّ (3.4-43) --}}
        <div data-lesson-nudge hidden
             class="fixed z-40 card p-3 text-sm flex items-center gap-2 animate-fadeup"
             style="inset-inline-start: 1rem; inset-block-end: 5.5rem; max-inline-size: 22rem">
            <x-icon name="lesson" size="16" style="color: var(--color-brand-400)" />
            <span class="flex-1">{{ setting('learning.nudge.resume_message', 'لسّه فاضل شويّة في الدرس ده — تحبّ تكمّله؟') }}</span>
            <button type="button" data-lesson-nudge-close class="opacity-70 hover:opacity-100"
                    aria-label="{{ setting('celebrations.labels.close', 'تمام') }}">
                <x-icon name="close" size="14" />
            </button>
        </div>

        <script>
            /*
             | ⭐ «أكمل الدرس» (3.4-43) — تذكيرٌ لطيف لا تأنيب.
             |
             | 🛡️ بلا Dark Patterns (2.9): لا نمنع الخروج، ولا نكتب رسالة مُذنِبة،
             | ولا نستخدم `beforeunload` الذي يحتجز المستخدم. نغيّر عنوان التاب
             | وهو غائب، وحين يعود نعرض سطرًا واحدًا قابلًا للإغلاق — والإغلاق
             | يُحفَظ لهذه الجلسة فلا يتكرّر عليه في الدرس نفسه.
             */
            (() => {
                const nudge = document.querySelector('[data-lesson-nudge]');
                if (!nudge) return;

                const key = 'lesson-nudge:' + location.pathname;
                const title = document.title;
                const prefix = @json(setting('learning.nudge.tab_prefix', '⏸ '));
                let away = false;

                if (sessionStorage.getItem(key) === 'off') return;

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        away = true;
                        document.title = prefix + title;
                        return;
                    }

                    document.title = title;
                    if (away) nudge.hidden = false;
                });

                nudge.querySelector('[data-lesson-nudge-close]').addEventListener('click', () => {
                    nudge.hidden = true;
                    sessionStorage.setItem(key, 'off');
                });
            })();
        </script>
    @endif

    <script>
        /*
         | ⭐ تتبّع مشاهدة الفيديو (4.1) — بلا أيّ SDK خارجيّ (2.1).
         | الصفحة تبلّغ بموضعها كلّ بضع ثوانٍ **وهي ظاهرة فقط**، والخادم يحدّ
         | القفزة الواحدة ويقرّر متى صارت المشاهدة كافية — فالعميل يعرض ويبلّغ،
         | والقرار في الخادم حصرًا.
         */
        (() => {
            const box = document.querySelector('[data-watch]');
            if (!box) return;

            const note = box.querySelector('[data-watch-note]');
            const every = Math.max(5, parseInt(box.dataset.watchEvery || '15', 10));
            const duration = parseInt(box.dataset.watchDuration || '0', 10);
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let seconds = 0;
            let done = false;

            const ping = async () => {
                if (done || document.hidden) return;
                seconds += every;

                try {
                    const res = await fetch(box.dataset.watch, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify({ position: seconds, duration: duration }),
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (note) note.textContent = data.watched ? @json($hcWords['learning.lesson_view.js_1']) : @json($hcWords['learning.lesson_view.js_2']) + data.percent + '%';
                    if (data.watched) done = true;
                } catch (e) { /* الشبكة اتقطعت — التقرير التالي يكمّل من مكانه */ }
            };

            setInterval(ping, every * 1000);
        })();

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

        /* ملاحظات التدريب (3.2): حفظ تلقائيّ مع «اتحفظ ✓» — والفورم يعمل بدون هذا الكود */
        (() => {
            const box = document.querySelector('[data-notes]');
            if (!box) return;

            const input = box.querySelector('[data-notes-input]');
            const state = box.querySelector('[data-notes-state]');
            const manual = box.querySelector('[data-notes-manual]');
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const delay = parseInt(box.dataset.delay, 10) || 800;
            let timer = null;
            let last = input?.value ?? '';

            if (manual) manual.classList.add('hidden'); // الجافاسكربت شغّال ⟵ الحفظ تلقائيّ

            const say = (text) => { if (state) state.textContent = text; };

            async function save() {
                if (!input || input.value === last) return;
                const body = input.value;
                say(@json(setting('learning.notes.saving')));

                try {
                    const response = await fetch(box.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify({ body: body }),
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'save failed');
                    last = body;
                    say(data.message || @json(setting('learning.notes.saved')));
                } catch (e) {
                    say(e.message || @json(setting('learning.notes.error')));
                }
            }

            input?.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(save, delay);
            });
            input?.addEventListener('blur', save);
            window.addEventListener('beforeunload', () => { if (input && input.value !== last) save(); });
        })();

        /* تعليقات الفيديو (3.1): 6 تعليقات أقدم كلّما نزل لأسفل + Skeleton أثناء الجلب */
        (() => {
            const box = document.querySelector('[data-comments]');
            if (!box) return;

            const list = box.querySelector('[data-comments-list]');
            const skeleton = box.querySelector('[data-comments-skeleton]');
            let busy = false;

            async function loadMore(trigger) {
                if (busy) return;
                busy = true;
                skeleton?.classList.remove('hidden');

                try {
                    const response = await fetch(trigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('load failed');
                    const html = await response.text();
                    trigger.remove();
                    list?.insertAdjacentHTML('beforeend', html);
                    watch();
                } catch (e) {
                    trigger.textContent = @json(setting('learning.comments.load_error'));
                } finally {
                    skeleton?.classList.add('hidden');
                    busy = false;
                }
            }

            const observer = 'IntersectionObserver' in window
                ? new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            observer.unobserve(entry.target);
                            loadMore(entry.target);
                        }
                    });
                })
                : null;

            function watch() {
                const next = list?.querySelector('[data-comments-next]');
                if (!next) return;
                next.addEventListener('click', (e) => { e.preventDefault(); loadMore(next); });
                observer?.observe(next);
            }

            watch();

            // فتح القسم تلقائيًّا عند الرجوع لتعليق بعينه بعد الإرسال
            if (location.hash.startsWith('#comment-')) box.open = true;

            // إظهار/إخفاء فورم الردّ — والردّ نفسه فورم عاديّ يعمل بلا جافاسكربت
            box.querySelectorAll('[data-reply-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = document.getElementById(button.dataset.replyToggle);
                    form?.classList.toggle('hidden');
                    form?.querySelector('textarea')?.focus();
                });
            });

            // اللايك: ردّ فوريّ بلا إعادة تحميل (2.17-أ)
            box.addEventListener('submit', async (event) => {
                const form = event.target.closest('[data-like-form]');
                if (!form) return;
                event.preventDefault();

                const button = form.querySelector('button');
                const counter = form.querySelector('[data-like-count]');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error('like failed');
                    if (counter) counter.textContent = data.count;
                    button?.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                    if (button) button.style.color = data.liked ? 'var(--color-brand-400)' : 'var(--text-muted)';
                } catch (e) {
                    form.submit(); // فشل الشبكة ⟵ نكمل بالطريق العاديّ
                }
            });
        })();
    </script>
@endpush
