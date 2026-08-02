@extends('layouts.app')

@section('title', $product->name_ar)
@section('noindex', true)

@push('head')
<style>
    /* القارئ يملأ الشاشة على الموبايل (2.15-ج) */
    .reader { display: grid; grid-template-rows: auto 1fr auto; block-size: calc(100dvh - 140px); }
    @media (min-width: 768px) { .reader { block-size: calc(100dvh - 120px); } }
    .reader:fullscreen { block-size: 100dvh; background: var(--surface); padding: .5rem; }
    .reader-stage { position: relative; overflow: auto; display: grid; place-items: center; }
    .reader-page { max-inline-size: 100%; transform-origin: center top; }

    /* تقليب بأنيميشن — بسرعة المنصّة الموحّدة (2.17-د) */
    @keyframes flip-next { from { opacity: .25; transform: perspective(1400px) rotateY(-14deg) translateX(3%); } to { opacity: 1; transform: none; } }
    @keyframes flip-prev { from { opacity: .25; transform: perspective(1400px) rotateY(14deg) translateX(-3%); } to { opacity: 1; transform: none; } }
    .flip-next { animation: flip-next 220ms var(--ease-standard) both; }
    .flip-prev { animation: flip-prev 220ms var(--ease-standard) both; }

    /* طبقة العلامة المائيّة: تُرسَم لحظة العرض ولا تُخزَّن نسخة لكلّ مستخدم (20.3) */
    .reader-watermark {
        position: absolute; inset: 0; pointer-events: none; user-select: none;
        display: grid; grid-template-columns: repeat(2, 1fr); align-content: space-around;
        justify-items: center; overflow: hidden;
    }
    .reader-watermark span {
        transform: rotate(-30deg); white-space: nowrap; font-weight: 700; letter-spacing: .04em;
    }

    .reader[data-reading-mode="dark"] .reader-page { filter: invert(1) hue-rotate(180deg); }
    .reader-thumbs { inline-size: 0; overflow: hidden; transition: inline-size 200ms var(--ease-standard); }
    .reader[data-thumbs="1"] .reader-thumbs { inline-size: 120px; }
    @media (max-width: 767px) { .reader[data-thumbs="1"] .reader-thumbs { inline-size: 88px; } }
</style>
@endpush

@section('content')
    @php
        $tocOpen = (bool) setting('reader.toc.open_by_default', false);
        $zoomMin = (int) setting('reader.zoom.min_percent', 60);
        $zoomMax = (int) setting('reader.zoom.max_percent', 240);
        $zoomStep = (int) setting('reader.zoom.step_percent', 20);
    @endphp

    <x-page-header
        :title="$product->name_ar"
        :breadcrumbs="[
            ['label' => setting('library.page.title', 'مكتبتي'), 'url' => \Illuminate\Support\Facades\Route::has('library.index') ? route('library.index') : url('/')],
            ['label' => $product->name_ar],
        ]" />

    @unless ($engineAvailable)
        {{-- بديل آمن بلا كسر: نوضّح ماذا حدث وماذا يفعل (2.17-ب) --}}
        <div class="card p-3 mb-3 text-sm" role="status">
            {{ setting('reader.fallback.message', 'محرّك عرض الـPDF مش مركّب على السيرفر دلوقتي — بنعرض لك صفحاتٍ بديلة مؤقّتًا لحدّ ما يتفعّل، وملفّك محفوظ زيّ ما هو.') }}
        </div>
    @endunless

    <div class="reader card p-2 md:p-3"
         data-reader
         data-pages="{{ $pages }}"
         data-start="{{ $startPage }}"
         data-page-url="{{ $pageUrl }}"
         data-thumb-url="{{ $thumbUrl }}"
         data-progress-url="{{ $progressUrl }}"
         data-reading-mode="light"
         data-thumbs="{{ $tocOpen ? 1 : 0 }}">

        {{-- الهيدر: اسم الملفّ + رقم الصفحة/الإجماليّ + «⋯» (24.5) --}}
        <div class="flex items-center justify-between gap-2 pb-2" style="border-bottom: 1px solid var(--border)">
            <div class="min-w-0 flex items-center gap-2">
                <span style="color: var(--color-brand-400)">@include('library.components.type-icon', ['type' => 'pdf', 'size' => 18])</span>
                <span class="truncate text-sm font-semibold">{{ $product->name_ar }}</span>
                <x-state-badge :state="$availability['state']" :label="$availability['label']" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs tabular-nums" style="color: var(--text-muted)">
                    <span data-current-page>{{ $startPage }}</span> / {{ $pages }}
                </span>
                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-lg px-2 py-1 text-sm" style="background: var(--surface-sunken)">⋯</summary>
                    <div class="card absolute end-0 mt-2 p-2 space-y-1 z-30 w-48">
                        <button type="button" data-toggle-thumbs class="w-full text-start rounded-lg px-2 py-1.5 text-sm motion-standard">
                            {{ setting('reader.menu.toc_label', 'الفهرس والمصغّرات') }}
                        </button>
                        <button type="button" data-toggle-fullscreen class="w-full text-start rounded-lg px-2 py-1.5 text-sm motion-standard">
                            {{ setting('reader.menu.fullscreen_label', 'ملء الشاشة') }}
                        </button>
                        <button type="button" data-toggle-mode class="w-full text-start rounded-lg px-2 py-1.5 text-sm motion-standard">
                            {{ setting('reader.menu.mode_label', 'وضع القراءة داكن/فاتح') }}
                        </button>
                    </div>
                </details>
            </div>
        </div>

        <div class="flex min-h-0 gap-2 py-2">
            {{-- الفهرس (TOC) + المصغّرات مع بحث داخلهما (20.3 · 24.5) --}}
            <aside class="reader-thumbs shrink-0">
                <input type="search" data-toc-search
                       placeholder="{{ setting('reader.toc.search_placeholder', 'رقم الصفحة…') }}"
                       aria-label="{{ setting('reader.toc.search_placeholder', 'رقم الصفحة…') }}"
                       class="w-full rounded-lg px-2 py-1 text-xs mb-2"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                {{-- فهرس الملفّ: عنوانٌ ورقم صفحة، والضغط يقفز إليها مباشرةً (20.3) --}}
                <nav class="mb-3" data-toc aria-label="{{ setting('reader.toc.title', 'فهرس الملفّ') }}">
                    <h2 class="text-xs font-semibold mb-1" style="color: var(--text-muted)">
                        {{ setting('reader.toc.title', 'فهرس الملفّ') }}
                    </h2>

                    @if ($toc === [])
                        {{-- حالة فارغة بسطر واحد لا تعاتب (2.17-ج) --}}
                        <p class="text-xs" style="color: var(--text-muted)">{{ setting('reader.toc.empty_text') }}</p>
                    @else
                        <ul class="space-y-0.5 overflow-y-auto" style="max-block-size: 34vh" data-toc-list>
                            @foreach ($toc as $entry)
                                <li>
                                    <button type="button" data-toc-entry="{{ $entry['page'] }}"
                                            data-toc-title="{{ $entry['title'] }}"
                                            class="w-full flex items-baseline justify-between gap-1 rounded-lg px-1.5 py-1 text-xs text-start motion-standard">
                                        <span class="truncate">{{ $entry['title'] }}</span>
                                        <span class="tabular-nums shrink-0" style="color: var(--text-muted)">{{ $entry['page'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </nav>

                <h2 class="text-xs font-semibold mb-1" style="color: var(--text-muted)">
                    {{ setting('reader.thumbs.title', 'المصغّرات') }}
                </h2>
                <div class="overflow-y-auto space-y-2 pe-1" style="max-block-size: 100%" data-thumbs-list></div>
            </aside>

            <div class="reader-stage grow min-w-0" data-stage>
                <div class="relative">
                    <img data-page-image alt="{{ setting('reader.page.alt', 'صفحة من الملفّ') }}"
                         class="reader-page rounded-lg" style="background: var(--surface-sunken)">

                    @if ($watermarkEnabled)
                        {{-- الاسم + الكود — رادعٌ للتسريب وإحساس ملكيّة «نسختك الخاصّة» (20.3) --}}
                        <div class="reader-watermark" data-watermark aria-hidden="true"
                             style="color: var(--text-muted); opacity: {{ (int) setting('reader.watermark.opacity_percent', 12) / 100 }};
                                    font-size: {{ (int) setting('reader.watermark.font_size_px', 18) }}px">
                            @for ($i = 0; $i < $watermarkRepeat; $i++)
                                <span>{{ $watermarkText }}</span>
                            @endfor
                        </div>
                    @endif
                </div>

                @if ($teaser)
                    <div class="card p-4 mt-3 text-center space-y-2" data-buy-block hidden>
                        <p class="text-sm">{{ setting('reader.teaser.end_message', 'خلصت صفحات العيّنة — كمّل القراءة بعد الشراء.') }}</p>
                        <a href="{{ $buyUrl }}" class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold"
                           style="background: var(--color-brand-500); color: #04201c">{{ setting('reader.teaser.buy_label', 'شراء') }}</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- الشريط السفليّ: التقليب + الزووم + «تابع القراءة» --}}
        <div class="flex flex-wrap items-center justify-between gap-2 pt-2" style="border-top: 1px solid var(--border)">
            <div class="flex items-center gap-2">
                <button type="button" data-prev class="btn rounded-xl px-3 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">{{ setting('reader.nav.prev_label', 'السابقة') }}</button>
                <button type="button" data-next class="btn rounded-xl px-3 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('reader.nav.next_label', 'التالية') }}</button>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" data-zoom="-1" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)" aria-label="{{ setting('reader.zoom.out_label', 'تصغير') }}">−</button>
                <span class="text-xs tabular-nums" data-zoom-value style="color: var(--text-muted)">100%</span>
                <button type="button" data-zoom="1" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)" aria-label="{{ setting('reader.zoom.in_label', 'تكبير') }}">+</button>

                @if ($hasProgress)
                    <button type="button" data-continue class="btn rounded-xl px-3 py-2 text-sm motion-standard"
                            style="background: var(--surface-sunken)">{{ setting('reader.continue_label', 'تابع القراءة') }}</button>
                @endif
            </div>
        </div>
    </div>

    <p class="text-xs mt-2" style="color: var(--text-muted)">
        {{ setting('reader.protection_note', 'الملفّ ده بيتقرا جوّه الموقع بس — بلا تحميل وبلا رابط مباشر، ونسختك عليها اسمك وكودك.') }}
    </p>
@endsection

@push('scripts')
<script>
/* القارئ المحميّ (20.3): كلّ صفحة صورةٌ تُطلَب لحظتها من الخادم بجلسة المالك. */
(function () {
    const reader = document.querySelector('[data-reader]');
    if (!reader) return;

    const total = parseInt(reader.dataset.pages, 10) || 1;
    const image = reader.querySelector('[data-page-image]');
    const label = reader.querySelector('[data-current-page]');
    const thumbs = reader.querySelector('[data-thumbs-list]');
    const stage = reader.querySelector('[data-stage]');
    const buyBlock = reader.querySelector('[data-buy-block]');
    const zoomValue = reader.querySelector('[data-zoom-value]');
    const zoom = { value: 100, min: {{ $zoomMin }}, max: {{ $zoomMax }}, step: {{ $zoomStep }} };

    const pageUrl = (n) => reader.dataset.pageUrl.replace('__PAGE__', n);
    const thumbUrl = (n) => reader.dataset.thumbUrl.replace('__PAGE__', n);

    let current = Math.min(total, Math.max(1, parseInt(reader.dataset.start, 10) || 1));
    let saveTimer = null;

    function applyZoom() {
        image.style.width = zoom.value + '%';
        zoomValue.textContent = zoom.value + '%';
    }

    function saveProgress() {
        const url = reader.dataset.progressUrl;
        if (!url || url === '') return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ page: current }),
            }).catch(() => {});
        }, 600);
    }

    function show(page, direction) {
        current = Math.min(total, Math.max(1, page));
        image.classList.remove('flip-next', 'flip-prev');
        void image.offsetWidth; // إعادة تشغيل الأنيميشن
        image.classList.add(direction === 'prev' ? 'flip-prev' : 'flip-next');
        image.src = pageUrl(current);
        label.textContent = current;
        if (buyBlock) buyBlock.hidden = current !== total;
        thumbs.querySelectorAll('[data-thumb]').forEach((t) => {
            t.style.outline = parseInt(t.dataset.thumb, 10) === current ? '2px solid var(--color-brand-500)' : 'none';
        });
        paintToc();
        saveProgress();
    }

    for (let n = 1; n <= total; n++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.thumb = n;
        btn.className = 'block w-full rounded-lg overflow-hidden text-xs';
        btn.style.background = 'var(--surface-sunken)';
        btn.innerHTML = `<img loading="lazy" alt="${n}" src="${thumbUrl(n)}" class="w-full"><span class="block py-1">${n}</span>`;
        btn.addEventListener('click', () => show(n, n > current ? 'next' : 'prev'));
        thumbs.appendChild(btn);
    }

    reader.querySelector('[data-next]').addEventListener('click', () => show(current + 1, 'next'));
    reader.querySelector('[data-prev]').addEventListener('click', () => show(current - 1, 'prev'));
    reader.querySelector('[data-continue]')?.addEventListener('click', () => show(parseInt(reader.dataset.start, 10) || 1, 'next'));

    reader.querySelectorAll('[data-zoom]').forEach((btn) => btn.addEventListener('click', () => {
        zoom.value = Math.min(zoom.max, Math.max(zoom.min, zoom.value + zoom.step * parseInt(btn.dataset.zoom, 10)));
        applyZoom();
    }));

    reader.querySelector('[data-toggle-thumbs]').addEventListener('click', () => {
        reader.dataset.thumbs = reader.dataset.thumbs === '1' ? '0' : '1';
    });

    reader.querySelector('[data-toggle-mode]').addEventListener('click', () => {
        reader.dataset.readingMode = reader.dataset.readingMode === 'dark' ? 'light' : 'dark';
    });

    reader.querySelector('[data-toggle-fullscreen]').addEventListener('click', () => {
        if (document.fullscreenElement) { document.exitFullscreen?.(); return; }
        reader.requestFullscreen?.().catch(() => {});
    });

    /* الفهرس: القفز لصفحة الفصل، وتمييز الفصل الحاليّ بنصّ لا بلون وحده (2.16) */
    const tocEntries = Array.from(reader.querySelectorAll('[data-toc-entry]'));
    tocEntries.forEach((btn) => btn.addEventListener('click', () => {
        const page = parseInt(btn.dataset.tocEntry, 10);
        show(page, page > current ? 'next' : 'prev');
    }));

    function paintToc() {
        let activeIndex = -1;
        tocEntries.forEach((btn, index) => {
            if (parseInt(btn.dataset.tocEntry, 10) <= current) activeIndex = index;
        });
        tocEntries.forEach((btn, index) => {
            const active = index === activeIndex;
            btn.style.background = active ? 'var(--surface-sunken)' : 'transparent';
            btn.style.fontWeight = active ? '700' : '400';
            btn.setAttribute('aria-current', active ? 'true' : 'false');
        });
    }

    /* بحثٌ واحد يفلتر الفهرس بالعنوان والمصغّرات برقم الصفحة معًا */
    reader.querySelector('[data-toc-search]').addEventListener('input', (e) => {
        const wanted = e.target.value.trim();
        thumbs.querySelectorAll('[data-thumb]').forEach((t) => {
            t.style.display = wanted === '' || t.dataset.thumb.startsWith(wanted) ? '' : 'none';
        });
        tocEntries.forEach((btn) => {
            const hit = wanted === ''
                || btn.dataset.tocEntry.startsWith(wanted)
                || btn.dataset.tocTitle.includes(wanted);
            btn.parentElement.style.display = hit ? '' : 'none';
        });
    });

    /* التقليب بالكيبورد — والاتّجاه معكوس لأنّ الواجهة RTL (2.17-د) */
    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea')) return;
        if (e.key === 'ArrowLeft') show(current + 1, 'next');
        if (e.key === 'ArrowRight') show(current - 1, 'prev');
    });

    /* ردعٌ إضافيّ: لا قائمة سياق ولا سحب للصورة — والحماية الحقيقيّة في الخادم */
    stage.addEventListener('contextmenu', (e) => e.preventDefault());
    image.addEventListener('dragstart', (e) => e.preventDefault());

    applyZoom();
    show(current, 'next');
})();
</script>
@endpush
