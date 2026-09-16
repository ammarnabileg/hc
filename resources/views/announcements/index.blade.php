@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'announcements.index.js_1' => (string) setting('announcements.index.js_1', 'افتح الرابط'),
    ]);
@endphp

@extends('layouts.app')

@section('title', (string) setting('announcements.index.section_1', 'التعليمات'))

@php
    /**
     * التعليمات (13.2 · 24.5): فيد بثّ اتّجاه واحد — بلا ردود.
     * سؤال واحد للشاشة: «إيه الجديد من الإدارة؟» — وفعل رئيسيّ واحد: تعليم الكلّ كمقروء.
     */
    $ackLabel = (string) setting('announcements.acknowledge.label', 'قرأتُ وفهمت');
    $emptyMessage = (string) setting('announcements.empty.message', 'لا تعليمات جديدة');
    // سطر يشرح لماذا لا تُغلَق النافذة — الشفافيّة تسبق الإلزام (13.2 · 2.16)
    $ackNotice = (string) setting(
        'announcements.acknowledge.notice',
        'توجيه حرج، لازم تقرّ بقراءته قبل ما تكمّل تصفّح المنصّة.',
    );
    $subtitle = $unread > 0 ? strtr((string) setting('announcements.index.php_1', 'عندك :a1 منشور لسّه ما اتقروش'), [':a1' => (string) ($unread)]) : (string) setting('announcements.index.php_2', 'كلّ التعليمات مقروءة، تمام ✓');
@endphp

@section('content')
    <x-page-header
        title="{{ setting('announcements.index.title_1', 'التعليمات') }}"
        :subtitle="$subtitle"
        :breadcrumbs="[['label' => (string) setting('announcements.index.breadcrumbs_1', 'الرئيسيّة'), 'url' => route('dashboard')], ['label' => (string) setting('announcements.index.breadcrumbs_2', 'التعليمات')]]">
        <x-slot:action>
            @if ($unread > 0)
                <form method="post" action="{{ route('announcements.read-all') }}" data-ajax-form>
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('announcements.index.text_1', 'تعليم الكلّ كمقروء') }}
                        <span class="rounded-full px-2 text-xs" style="background: rgb(0 0 0 / .15)">{{ $unread }}</span>
                    </button>
                </form>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة فلاتر ظاهرة فقط: غير المقروء · النوع · بحث (2.15-أ-4) --}}
    <x-filters :action="route('announcements.index')">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="unread" value="1" @checked($filters['unread'])
                   onchange="this.form.submit()" style="accent-color: var(--color-brand-500)">
            {{ setting('announcements.index.text_2', 'غير المقروء') }}
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('announcements.index.text_3', 'النوع') }}</span>
            <select name="type" onchange="this.form.submit()"
                    class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('announcements.index.text_4', 'الكلّ') }}</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('announcements.index.text_5', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('announcements.index.placeholder_1', 'ابحث بالعنوان…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty :message="$emptyMessage" action="{{ setting('announcements.index.action_1', 'الرجوع للرئيسيّة') }}" :href="route('dashboard')" />
    @else
        <div class="space-y-4">
            @foreach ($items as $announcement)
                @include('announcements.partials.card', [
                    'announcement' => $announcement,
                    'read' => $reads[$announcement->id] ?? null,
                    'poll' => $polls[$announcement->id] ?? null,
                    'counts' => $reactionCounts[$announcement->id] ?? [],
                    'reactions' => $reactions,
                    'ackLabel' => $ackLabel,
                ])
            @endforeach
        </div>

        @if ($hasMore)
            {{-- تمرير تدريجيّ لا ترقيم صفحات (2.15 — الترقيم مرفوض) --}}
            <div class="mt-5 text-center">
                <a class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-raised); border: 1px solid var(--border)"
                   href="{{ route('announcements.index', array_filter($filters) + ['more' => $nextMore]) }}">
                    {{ strtr((string) setting('announcements.index.text_6', 'عرض المزيد (:a1)'), [':a1' => (string) ($total - $items->count())]) }}
                </a>
            </div>
        @endif
    @endif

    {{--
        بوب-أب الإقرار الإلزاميّ للتوجيه الحرج — وعلى الموبايل Bottom Sheet (2.15-ج).

        ⭐ **بلا ✕ وبلا ESC وبلا إغلاق بالخلفيّة**: النصّ يقول «قبل المتابعة»
        (13.2)، ونافذةٌ تُغلَق بضغطة تُلغي الشرط. ولذلك لا يستعمل `x-modal`
        العامّ — فرأسه يحمل زرّ إغلاق و`data-modal` يجعل ESC يغلقه.
        والمخرج الوحيد هو زرّ الإقرار… أو الخروج من الحساب (فالإقرار ليس سجنًا).
    --}}
    @if ($pendingAck)
        <div id="ack-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
             style="background: rgb(0 0 0 / .72)" role="alertdialog" aria-modal="true"
             aria-label="{{ $pendingAck->title }}" data-ack-lock>
            <div class="modal-shell card w-full max-w-2xl">
                <div class="modal-head px-5 py-4" style="border-bottom: 1px solid var(--border)">
                    <h2 class="font-bold">{{ $pendingAck->title }}</h2>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $ackNotice }}</p>
                </div>
                <div class="modal-body px-5 py-4">
                    <p class="text-sm leading-7 whitespace-pre-line">{{ $pendingAck->body }}</p>
                    @if ($pendingAck->cta_url)
                        <a href="{{ $pendingAck->cta_url }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm mt-4 motion-standard"
                           style="background: var(--surface-sunken)">{{ $pendingAck->cta_label ?: (string) setting('announcements.index.expr_1', 'افتح الرابط') }}</a>
                    @endif
                </div>
                <div class="modal-head px-5 py-4" style="border-top: 1px solid var(--border)">
                    <form method="post" action="{{ route('announcements.acknowledge', $pendingAck) }}" class="flex justify-end">
                        @csrf
                        <button type="submit"
                                class="btn inline-flex items-center justify-center rounded-xl px-5 py-2 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ $ackLabel }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- تفاصيل المنشور: بانل/بوب-أب لا صفحة جديدة (2.15-أ-6) — Bottom Sheet على الموبايل --}}
    <x-modal id="announcement-sheet" title="{{ setting('announcements.index.title_2', 'تفاصيل المنشور') }}">
        <h3 class="font-bold mb-2" data-sheet-title></h3>
        <p class="text-sm leading-7 whitespace-pre-line" data-sheet-body></p>
        <a href="#" target="_blank" rel="noopener" data-sheet-cta
           class="btn hidden items-center rounded-xl px-4 py-2 text-sm mt-4 motion-standard"
           style="background: var(--surface-sunken)"></a>
    </x-modal>
@endsection

@section('mobile_action')
    @if ($unread > 0)
        <form method="post" action="{{ route('announcements.read-all') }}">
            @csrf
            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ strtr((string) setting('announcements.index.text_7', 'تعليم الكلّ كمقروء (:a1)'), [':a1' => (string) ($unread)]) }}</button>
        </form>
    @endif
@endsection

@push('head')
    <style>
        /* على الموبايل: البوب-أب يصير Bottom Sheet برأس ثابت وجسم متمرّر (2.15-ج) */
        @media (max-width: 767px) {
            #ack-modal, #announcement-sheet { padding: 0; }
            #ack-modal .modal-shell, #announcement-sheet .modal-shell {
                align-self: flex-end;
                max-block-size: 85vh;
                border-end-start-radius: 0;
                border-end-end-radius: 0;
            }
        }
        .announcement-body { display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
@endpush

@push('scripts')
    <script>
        // فتح الإقرار الإلزاميّ تلقائيًّا قبل المتابعة (13.2)
        const ack = document.getElementById('ack-modal');
        if (ack) { ack.classList.remove('hidden'); ack.classList.add('flex'); }

        // تفاصيل المنشور في بوب-أب/Bottom Sheet بدل صفحة جديدة
        const sheet = document.getElementById('announcement-sheet');
        document.querySelectorAll('[data-announcement-details]').forEach((btn) => {
            btn.addEventListener('click', () => {
                sheet.querySelector('[data-sheet-title]').textContent = btn.dataset.title || '';
                sheet.querySelector('[data-sheet-body]').textContent = btn.dataset.body || '';
                const cta = sheet.querySelector('[data-sheet-cta]');
                if (btn.dataset.ctaUrl) {
                    cta.href = btn.dataset.ctaUrl;
                    cta.textContent = btn.dataset.ctaLabel || @json($hcWords['announcements.index.js_1']);
                    cta.classList.remove('hidden');
                    cta.classList.add('inline-flex');
                } else {
                    cta.classList.add('hidden');
                    cta.classList.remove('inline-flex');
                }
                sheet.classList.remove('hidden');
                sheet.classList.add('flex');
            });
        });

        /*
         | تفاعل على طريقة فيسبوك (13.2): الزرّ الرئيسيّ يعمل/يلغي، والبوب-أب يختار،
         | والردّ فوريّ (2.17-ب) ثمّ يُصحَّح من الخادم بلا إعادة تحميل. الضغط المطوّل
         | يفتح البوب-أب على اللمس، والنقر خارجَه أو Escape يقفله.
         */
        document.querySelectorAll('[data-react-form]').forEach((form) => {
            const box = form.closest('[data-reactions]');
            const labels = JSON.parse(box.dataset.labels || '{}');
            const words = JSON.parse(box.dataset.words || '{}');
            const main = form.querySelector('[data-react-main]');
            const picks = Array.from(form.querySelectorAll('[data-react-pick]'));
            const defaultEmoji = picks[0] ? picks[0].value : main.value;

            const renderSummary = (mine, counts) => {
                const entries = Object.entries(counts).filter(([, n]) => n > 0).sort((a, b) => b[1] - a[1]);
                const total = entries.reduce((sum, [, n]) => sum + n, 0);
                let summary = box.querySelector('[data-reactions-summary]');
                if (!total) { if (summary) summary.remove(); return; }
                if (!summary) {
                    summary = document.createElement('div');
                    summary.className = 'reactions-summary';
                    summary.setAttribute('data-reactions-summary', '');
                    summary.innerHTML = '<span class="reactions-icons" data-reactions-icons></span><span class="reactions-count" data-reactions-count></span>';
                    box.prepend(summary);
                }
                summary.querySelector('[data-reactions-icons]').replaceChildren(...entries.slice(0, 3).map(([emoji]) => {
                    const chip = document.createElement('span'); chip.textContent = emoji; return chip;
                }));
                summary.querySelector('[data-reactions-count]').textContent = mine
                    ? (total <= 1 ? words.you : String(words.you_others).split(':n').join(total - 1))
                    : String(words.others).split(':n').join(total);
            };

            const paint = (mine, counts) => {
                box.dataset.mine = mine || '';
                main.dataset.active = mine ? '1' : '0';
                main.setAttribute('aria-pressed', mine ? 'true' : 'false');
                main.value = mine || defaultEmoji;
                main.querySelector('[data-react-emoji]').textContent = mine || defaultEmoji;
                main.querySelector('[data-react-label]').textContent = labels[mine || defaultEmoji] || words.react || '';
                picks.forEach((pick) => pick.setAttribute('aria-pressed', pick.value === mine ? 'true' : 'false'));
                if (counts) renderSummary(mine, counts);
            };

            const close = () => {
                box.dataset.open = 'false';
                if (document.activeElement && form.contains(document.activeElement)) document.activeElement.blur();
            };
            box.addEventListener('mouseleave', () => { if (box.dataset.open === 'false') delete box.dataset.open; });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const chosen = (e.submitter && e.submitter.value) || main.value;
                const current = box.dataset.mine || '';
                const fromPicker = !!(e.submitter && e.submitter.hasAttribute('data-react-pick'));
                if (fromPicker && chosen === current) { close(); return; }

                paint(chosen === current ? '' : chosen, null);
                close();
                const card = form.closest('[data-announcement]');
                if (card) card.removeAttribute('data-unread');

                try {
                    const body = new FormData(form);
                    body.set('reaction', chosen);
                    const res = await fetch(form.action, {
                        method: 'POST', body,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('failed');
                    const data = await res.json();
                    paint(data.reaction || '', data.counts || {});
                } catch {
                    window.location.reload();
                }
            });

            // الضغط المطوّل (اللمس/القلم) يفتح البوب-أب بدل الإعجاب المباشر
            let pressTimer = null, longPressed = false;
            main.addEventListener('pointerdown', (ev) => {
                if (ev.pointerType === 'mouse') return;
                longPressed = false;
                pressTimer = setTimeout(() => { pressTimer = null; longPressed = true; box.dataset.open = 'true'; }, 400);
            });
            ['pointerup', 'pointerleave', 'pointercancel'].forEach((name) => main.addEventListener(name, () => {
                if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
            }));
            main.addEventListener('click', (ev) => {
                if (longPressed) { ev.preventDefault(); longPressed = false; }
            }, true);
            main.addEventListener('contextmenu', (ev) => { if (box.dataset.open === 'true') ev.preventDefault(); });

            document.addEventListener('pointerdown', (ev) => { if (box.dataset.open === 'true' && !box.contains(ev.target)) close(); });
            box.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') close(); });
        });

        /*
         | ردّ فوريّ لكلّ فعل (2.17-ب): الفعل يظهر فورًا، ولو فشل الخادم
         | نُرجّع الصفحة لحالتها الصحيحة بإعادة التحميل بدل ترك المستخدم في شكّ.
         */
        document.querySelectorAll('[data-ajax-form]').forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const card = form.closest('[data-announcement]');
                if (card) card.removeAttribute('data-unread');

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('failed');
                    window.location.reload();
                } catch {
                    window.location.reload();
                }
            });
        });
    </script>
@endpush
