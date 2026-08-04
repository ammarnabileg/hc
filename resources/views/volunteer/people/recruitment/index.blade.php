@extends('layouts.volunteer')

@section('title', setting('volunteer.people_recruitment.title', 'المرشّحون'))

@php
    /**
     * المرشّحون — كانبان بالسحب (13.4-د · 24.4-12).
     * سؤال واحد للشاشة: «فين كلّ مرشّح في رحلته؟».
     * وعلى الموبايل: الكانبان **قائمة** لا أعمدة، بلا أيّ تمرير أفقيّ (2.15-ج).
     */
    $total = collect($columns)->sum(fn ($c) => $c->count());
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.people_recruitment.title', 'المرشّحون')"
        subtitle="{{ $total }} {{ setting('volunteer.people_recruitment.subtitle', 'مرشّحًا في الرحلة دلوقتي') }}"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.people_recruitment.label', 'التوظيف')], ['label' => setting('volunteer.people_recruitment.title', 'المرشّحون')]]" />

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.recruitment')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field', 'المرحلة') }}</span>
            <select name="stage" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($stages as $key => $label)
                    <option value="{{ $key }}" @selected($filters['stage'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_2', 'القسم المناسب') }}</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($tree as $root)
                    <optgroup label="{{ $root->name_ar }}">
                        <option value="{{ $root->id }}" @selected((int) $filters['entity'] === (int) $root->id)>{{ $root->name_ar }}</option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->id }}" @selected((int) $filters['entity'] === (int) $child->id)>— {{ $child->name_ar }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.people_recruitment.placeholder', 'الاسم أو الكود…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <x-slot:advanced>
            {{-- ⭐ نطاق الدرجات: منزلق مدى **بلا بوردر** (قاعدة نظام التصميم) --}}
            <label class="text-sm flex-1 min-w-56">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">
                    {{ setting('volunteer.people_recruitment.field_3', 'نطاق الدرجات') }} <output data-range-out>{{ $filters['score_min'] ?? $scoreFloor }}–{{ $filters['score_max'] ?? $scoreCeiling }}</output>
                </span>
                <div class="flex items-center gap-2">
                    <input type="range" name="score_min" data-range-min class="w-full" style="border: 0"
                           min="{{ $scoreFloor }}" max="{{ $scoreCeiling }}" step="1"
                           value="{{ $filters['score_min'] ?? $scoreFloor }}">
                    <input type="range" name="score_max" data-range-max class="w-full" style="border: 0"
                           min="{{ $scoreFloor }}" max="{{ $scoreCeiling }}" step="1"
                           value="{{ $filters['score_max'] ?? $scoreCeiling }}">
                </div>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_4', 'مدّة الانتظار') }}</span>
                <select name="waiting" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                    @foreach ([7 => setting('volunteer.people_recruitment.foreach', 'أسبوع فأكثر'), 30 => setting('volunteer.people_recruitment.foreach_2', 'شهر فأكثر'), 60 => setting('volunteer.people_recruitment.foreach_3', 'شهرين فأكثر')] as $days => $label)
                        <option value="{{ $days }}" @selected((string) $filters['waiting'] === (string) $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.common.apply', 'طبّق') }}</button>
        </x-slot:advanced>
    </x-filters>

    @if ($total === 0)
        <x-empty :message="setting('volunteer.people_recruitment.empty', 'مفيش مرشّحين في المرحلة دي')" :action="setting('volunteer.people_recruitment.action', 'شيل الفلاتر')" :href="route('volunteer.recruitment')" />
    @else
        {{-- الكانبان: أعمدة على الديسكتوب — وقائمة رأسيّة على الموبايل بلا تمرير أفقيّ --}}
        <div class="grid gap-4 md:grid-cols-5" data-board>
            @foreach ($stages as $key => $label)
                <section class="card p-3" data-column="{{ $key }}"
                         @if ($canMove) ondragover="event.preventDefault()" @endif>
                    <header class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold">{{ $label }}</h2>
                        <span class="text-xs rounded-full px-2 py-0.5"
                              style="background: var(--surface-sunken); color: var(--text-muted)">{{ $columns[$key]->count() }}</span>
                    </header>

                    <div class="space-y-3 min-h-12">
                        @forelse ($columns[$key] as $candidate)
                            @include('volunteer.people.recruitment.partials.card', [
                                'candidate' => $candidate,
                                'pipeline' => $pipeline,
                                'canSeePhone' => $canSeePhone,
                                'canSeeExitReason' => $canSeeExitReason,
                                'canMove' => $canMove,
                            ])
                        @empty
                            <p class="text-xs py-3 text-center" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_5', 'فاضي دلوقتي') }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- تفاصيل المرشّح في بوب-أب — لا صفحة جديدة، فلا يفقد مكانه في اللوحة (2.15-أ-6) --}}
    <x-modal id="candidate-modal" :title="setting('volunteer.people_recruitment.tooltip', 'المرشّح')">
        <div data-candidate-body class="text-sm">
            <p style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_6', 'بنحمّل التفاصيل…') }}</p>
        </div>
    </x-modal>

    {{-- سحب الكارت ⟵ تأكيد بسبب، والسبب يدخل سجلّ التدقيق (24.4-12) --}}
    @if ($canMove)
        <x-modal id="move-modal" :title="setting('volunteer.people_recruitment.tooltip_2', 'نقل المرشّح')">
            <form method="post" data-move-form>
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    {{ setting('volunteer.people_recruitment.field_7', 'بتنقل') }} <strong data-move-name></strong> {{ setting('volunteer.common.to', 'إلى') }} <strong data-move-stage></strong>.
                </p>
                <input type="hidden" name="stage" data-move-stage-input>
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment.field_8', 'سبب النقل (إلزاميّ)') }}</span>
                    <textarea name="reason" rows="3" required minlength="3"
                              class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                              placeholder="{{ setting('volunteer.people_recruitment.placeholder_2', 'اكتب سببًا واضحًا يفيد اللي هيقرأ السجلّ بعدك') }}"></textarea>
                </label>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_recruitment.action_2', 'أكّد النقل') }}</button>
                    <button type="button" data-modal-close class="btn rounded-xl px-4 py-2 text-sm"
                            style="background: var(--surface-sunken)">{{ setting('volunteer.people_recruitment.action_3', 'رجوع') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection

@php
    /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
    $jsText = [
        'loading' => (string) setting('volunteer.people_recruitment.js_loading', 'بنحمّل التفاصيل…'),
        'load_failed' => (string) setting('volunteer.people_recruitment.js_load_failed', 'تعذّر تحميل التفاصيل — جرّب تاني بعد شويّة.'),
    ];
@endphp

@push('scripts')
<script>
const T = @json($jsText);
(function () {
    // منزلق المدى: الحدّ الأدنى لا يتخطّى الأعلى — والقيمة تظهر لحظيًّا (2.17-ب)
    const min = document.querySelector('[data-range-min]');
    const max = document.querySelector('[data-range-max]');
    const out = document.querySelector('[data-range-out]');
    const sync = () => {
        if (!min || !max || !out) return;
        if (Number(min.value) > Number(max.value)) [min.value, max.value] = [max.value, min.value];
        out.textContent = `${min.value}–${max.value}`;
    };
    min?.addEventListener('input', sync);
    max?.addEventListener('input', sync);

    const modal = document.getElementById('move-modal');
    if (!modal) return;

    const form = modal.querySelector('[data-move-form]');
    const stageInput = modal.querySelector('[data-move-stage-input]');
    const nameOut = modal.querySelector('[data-move-name]');
    const stageOut = modal.querySelector('[data-move-stage]');

    const ask = (card, stageKey, stageLabel) => {
        form.action = card.dataset.moveUrl;
        stageInput.value = stageKey;
        nameOut.textContent = card.dataset.name;
        stageOut.textContent = stageLabel;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    // السحب على الديسكتوب
    document.querySelectorAll('[data-candidate]').forEach((card) => {
        card.addEventListener('dragstart', (e) => e.dataTransfer.setData('text/plain', card.dataset.candidate));
    });

    document.querySelectorAll('[data-column]').forEach((column) => {
        column.addEventListener('drop', (e) => {
            e.preventDefault();
            const id = e.dataTransfer.getData('text/plain');
            const card = document.querySelector(`[data-candidate="${id}"]`);
            if (!card || card.dataset.stage === column.dataset.column) return;
            ask(card, column.dataset.column, column.querySelector('h2').textContent.trim());
        });
    });

    // فتح تفاصيل المرشّح داخل البوب-أب — والتحميل عند الطلب فقط (2.15-د)
    const detail = document.getElementById('candidate-modal');
    const detailBody = detail?.querySelector('[data-candidate-body]');

    document.querySelectorAll('[data-detail-url]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!detail || !detailBody) return;
            detailBody.innerHTML = '<p style="color: var(--text-muted)">' + T.loading + '</p>';
            detail.classList.remove('hidden');
            detail.classList.add('flex');

            fetch(btn.dataset.detailUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.text())
                .then((html) => { detailBody.innerHTML = html; })
                .catch(() => {
                    detailBody.innerHTML = '<p>' + T.load_failed + '</p>';
                });
        });
    });

    // على الموبايل: بدل السحب — Select داخل الكارت بنفس التأكيد (2.15-ج)
    document.querySelectorAll('[data-move-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const card = select.closest('[data-candidate]');
            if (!select.value) return;
            ask(card, select.value, select.options[select.selectedIndex].text);
            select.value = '';
        });
    });
})();
</script>
@endpush
