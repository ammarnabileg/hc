@extends('layouts.app')

@section('title', setting('cv.page.title', 'سيرتي الذاتيّة'))

@push('head')
<style>
    /* المعاينة الحيّة بالمقاس الحقيقيّ: ورقة A4 مصغَّرة بصريًّا لا بالمحتوى (24.5) */
    .cv-preview-frame { inline-size: 210mm; block-size: 297mm; border: 0; transform-origin: top right; background: #fff; }
    .cv-preview-wrap { overflow: hidden; border-radius: 1rem; }
    .cv-pane[hidden] { display: none; }
    @media (min-width: 1024px) { .cv-pane { display: block !important; } }
</style>
@endpush

@section('content')
    @php
        $stepKeys = array_keys($steps);
        $freeTemplateNote = setting('cv.template.free_badge', 'مجّانيّ');
    @endphp

    <x-page-header
        :title="setting('cv.page.title', 'سيرتي الذاتيّة')"
        :subtitle="setting('cv.page.subtitle', 'املأ الخطوات، والمعاينة بتتحدّث معاك لحظة بلحظة.')"
        :breadcrumbs="[
            ['label' => setting('cv.breadcrumb.experiences', 'خبراتي'), 'url' => url()->current()],
            ['label' => setting('cv.page.title', 'سيرتي الذاتيّة')],
        ]">
        <x-slot:action>
            {{-- الفعل الرئيسيّ الوحيد (2.15-أ-2) --}}
            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener"
               class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                {{ $guest ? setting('cv.guest.download_label', 'أنشئ حساب وحمّل PDF') : setting('cv.download_label', 'تحميل PDF') }}
            </a>
        </x-slot:action>
    </x-page-header>

    @if ($guest)
        <div class="card p-3 mb-4 text-sm" role="status">
            {{ setting('cv.guest.note', 'إنت بتجرّب القالب المجّانيّ بلا تسجيل — التحميل بيطلب إنشاء حساب، وشغلك محفوظ لحدّ ما تسجّل.') }}
        </div>
    @endif

    {{-- أدوات القسم 9: الاستيراد · ATS PDF · الرابط العامّ --}}
    @include('cv.partials.tools')

    {{-- مؤشّر الاكتمال % — يوضّح الناقص ويحفّز بلا منع (9) --}}
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between text-sm">
            <span>{{ setting('cv.completion.label', 'اكتمال السيرة') }}</span>
            <span class="font-extrabold tabular-nums" data-completion-value>{{ $completion }}%</span>
        </div>
        <div class="mt-2 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
            <div class="h-full motion-standard" data-completion-bar style="width: {{ $completion }}%; background: var(--color-brand-500)"></div>
        </div>
        <p class="text-xs mt-2" data-missing style="color: var(--text-muted)">
            {{ $missing ? setting('cv.completion.missing_prefix', 'ناقصك:').' '.implode(' · ', $missing) : setting('cv.completion.done', 'سيرتك مكتملة — جاهزة للتحميل.') }}
        </p>
    </div>

    {{-- على الموبايل: المعاينة تاب منفصل (24.5) --}}
    <div class="flex gap-2 mb-3 lg:hidden">
        <button type="button" data-pane-tab="edit" class="rounded-full px-4 py-2 text-sm font-bold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('cv.tab.edit_label', 'التعديل') }}</button>
        <button type="button" data-pane-tab="preview" class="rounded-full px-4 py-2 text-sm"
                style="background: var(--surface-raised); color: var(--text)">{{ setting('cv.tab.preview_label', 'المعاينة') }}</button>
    </div>

    <div class="grid gap-4 lg:grid-cols-2" data-cv
         data-autosave-url="{{ $autosaveUrl }}"
         data-preview-url="{{ $guest ? route('cv.free.preview') : route('cv.preview') }}"
         data-debounce="{{ (int) setting('cv.autosave.debounce_ms', 900) }}">

        <section class="cv-pane" data-pane="edit">
            {{-- Stepper بخطوات بحفظ تلقائيّ بينها (2.15-د) --}}
            <div class="flex gap-2 overflow-x-auto no-scrollbar mb-3">
                @foreach ($steps as $key => $label)
                    <button type="button" data-step-tab="{{ $key }}"
                            class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                            style="{{ $loop->first ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-raised); color: var(--text)' }}">
                        <span class="opacity-70">{{ $loop->iteration }}.</span> {{ $label }}
                    </button>
                @endforeach
            </div>

            @include('cv.partials.step-profile')
            @include('cv.partials.step-experience')
            @include('cv.partials.step-education')
            @include('cv.partials.step-skills')
            @include('cv.partials.step-certificates')

            <div class="flex items-center justify-between mt-3">
                <button type="button" data-step-prev class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">
                    {{ setting('cv.step.prev_label', 'السابق') }}
                </button>
                <span class="text-xs" data-saved-note style="color: var(--color-state-ok)"></span>
                <button type="button" data-step-next class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('cv.step.next_label', 'التالي') }}</button>
            </div>

            @unless ($guest)
                @include('cv.partials.templates')
            @endunless
        </section>

        <section class="cv-pane" data-pane="preview" hidden>
            <div class="card p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold">{{ setting('cv.preview.title', 'المعاينة الحيّة') }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">{{ setting('cv.preview.size_note', 'بالمقاس الحقيقيّ A4') }}</span>
                </div>
                <div class="cv-preview-wrap" data-preview-wrap>
                    <iframe class="cv-preview-frame" data-preview title="{{ setting('cv.preview.title', 'المعاينة الحيّة') }}"
                            src="{{ $guest ? route('cv.free.preview') : route('cv.preview') }}"></iframe>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener"
       class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        {{ $guest ? setting('cv.guest.download_label', 'أنشئ حساب وحمّل PDF') : setting('cv.download_label', 'تحميل PDF') }}
    </a>
@endsection

@push('scripts')
<script>
/* منشئ السيرة: حفظ تلقائيّ بين الخطوات + «اتحفظ ✓» + معاينة حيّة (9 · 2.17-ب) */
(function () {
    const root = document.querySelector('[data-cv]');
    if (!root) return;

    const steps = @json($stepKeys);
    const savedLabel = @json(setting('cv.autosave.saved_label', 'اتحفظ ✓'));
    const errorLabel = @json(setting('cv.autosave.error_label', 'ما اتحفظش — راجع النت وجرّب تاني.'));
    const missingPrefix = @json(setting('cv.completion.missing_prefix', 'ناقصك:'));
    const doneLabel = @json(setting('cv.completion.done', 'سيرتك مكتملة — جاهزة للتحميل.'));

    const note = document.querySelector('[data-saved-note]');
    const preview = document.querySelector('[data-preview]');
    const wrap = document.querySelector('[data-preview-wrap]');
    const bar = document.querySelector('[data-completion-bar]');
    const value = document.querySelector('[data-completion-value]');
    const missing = document.querySelector('[data-missing]');

    let index = 0;
    let timer = null;

    function showStep(i) {
        index = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach((key, n) => {
            document.querySelector(`[data-step-panel="${key}"]`).hidden = n !== index;
            const tab = document.querySelector(`[data-step-tab="${key}"]`);
            const on = n === index;
            tab.style.background = on ? 'var(--color-brand-500)' : 'var(--surface-raised)';
            tab.style.color = on ? '#04201c' : 'var(--text)';
            tab.style.fontWeight = on ? '700' : '400';
        });
    }

    async function save(step) {
        const form = document.querySelector(`[data-step-form="${step}"]`);
        if (!form) return;

        const body = new FormData(form);
        body.append('step', step);

        try {
            const res = await fetch(root.dataset.autosaveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body,
            });
            if (!res.ok) throw new Error('http');
            const data = await res.json();

            note.textContent = data.label || savedLabel;
            note.style.color = 'var(--color-state-ok)';
            bar.style.width = data.completion + '%';
            value.textContent = data.completion + '%';
            missing.textContent = (data.missing && data.missing.length)
                ? missingPrefix + ' ' + data.missing.join(' · ')
                : doneLabel;

            refreshPreview();
        } catch {
            /* رسالة الخطأ = ماذا حدث + ماذا تفعل، بلا لوم (2.17-ب) */
            note.textContent = errorLabel;
            note.style.color = 'var(--color-state-warn)';
        }
    }

    function refreshPreview() {
        if (!preview) return;
        preview.src = root.dataset.previewUrl + '?t=' + Date.now();
    }

    function scalePreview() {
        if (!preview || !wrap) return;
        const scale = Math.min(1, wrap.clientWidth / preview.offsetWidth);
        preview.style.transform = `scale(${scale})`;
        wrap.style.height = (preview.offsetHeight * scale) + 'px';
    }

    root.addEventListener('input', (e) => {
        const panel = e.target.closest('[data-step-form]');
        if (!panel) return;
        clearTimeout(timer);
        timer = setTimeout(() => save(panel.dataset.stepForm), parseInt(root.dataset.debounce, 10) || 900);
    });

    root.addEventListener('change', (e) => {
        const panel = e.target.closest('[data-step-form]');
        if (panel) save(panel.dataset.stepForm);
    });

    document.querySelectorAll('[data-step-tab]').forEach((tab, n) => tab.addEventListener('click', () => {
        save(steps[index]);
        showStep(n);
    }));

    document.querySelector('[data-step-next]').addEventListener('click', () => { save(steps[index]); showStep(index + 1); });
    document.querySelector('[data-step-prev]').addEventListener('click', () => { save(steps[index]); showStep(index - 1); });

    /* الأقسام المتكرّرة: زرّ إضافة + «×» للحذف (9) */
    document.querySelectorAll('[data-repeat]').forEach((group) => {
        const list = group.querySelector('[data-repeat-list]');
        const tpl = group.querySelector('template');

        group.querySelector('[data-repeat-add]').addEventListener('click', () => {
            const html = tpl.innerHTML.replaceAll('__I__', list.children.length);
            const holder = document.createElement('div');
            holder.innerHTML = html;
            list.appendChild(holder.firstElementChild);
        });

        list.addEventListener('click', (e) => {
            if (!e.target.closest('[data-repeat-remove]')) return;
            e.target.closest('[data-repeat-row]').remove();
            save(group.closest('[data-step-form]').dataset.stepForm);
        });
    });

    /* تاب المعاينة على الموبايل */
    document.querySelectorAll('[data-pane-tab]').forEach((tab) => tab.addEventListener('click', () => {
        const wanted = tab.dataset.paneTab;
        document.querySelectorAll('[data-pane]').forEach((pane) => { pane.hidden = pane.dataset.pane !== wanted; });
        document.querySelectorAll('[data-pane-tab]').forEach((t) => {
            const on = t === tab;
            t.style.background = on ? 'var(--color-brand-500)' : 'var(--surface-raised)';
            t.style.color = on ? '#04201c' : 'var(--text)';
            t.style.fontWeight = on ? '700' : '400';
        });
        scalePreview();
    }));

    window.addEventListener('resize', scalePreview);
    preview?.addEventListener('load', scalePreview);
    showStep(0);
    scalePreview();
})();
</script>
@endpush
