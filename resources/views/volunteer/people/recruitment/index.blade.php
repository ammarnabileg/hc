@extends('layouts.app')

@section('title', 'المرشّحون')

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
        title="المرشّحون"
        subtitle="{{ $total }} مرشّحًا في الرحلة دلوقتي"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'التوظيف'], ['label' => 'المرشّحون']]" />

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.recruitment')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">المرحلة</span>
            <select name="stage" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($stages as $key => $label)
                    <option value="{{ $key }}" @selected($filters['stage'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">القسم المناسب</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
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
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="الاسم أو الكود…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <x-slot:advanced>
            {{-- ⭐ نطاق الدرجات: منزلق مدى **بلا بوردر** (قاعدة نظام التصميم) --}}
            <label class="text-sm flex-1 min-w-56">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">
                    نطاق الدرجات <output data-range-out>{{ $filters['score_min'] ?? $scoreFloor }}–{{ $filters['score_max'] ?? $scoreCeiling }}</output>
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
                <span class="block text-xs mb-1" style="color: var(--text-muted)">مدّة الانتظار</span>
                <select name="waiting" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ([7 => 'أسبوع فأكثر', 30 => 'شهر فأكثر', 60 => 'شهرين فأكثر'] as $days => $label)
                        <option value="{{ $days }}" @selected((string) $filters['waiting'] === (string) $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">طبّق</button>
        </x-slot:advanced>
    </x-filters>

    @if ($total === 0)
        <x-empty message="مفيش مرشّحين في المرحلة دي" action="شيل الفلاتر" :href="route('volunteer.recruitment')" />
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
                            <p class="text-xs py-3 text-center" style="color: var(--text-muted)">فاضي دلوقتي</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- سحب الكارت ⟵ تأكيد بسبب، والسبب يدخل سجلّ التدقيق (24.4-12) --}}
    @if ($canMove)
        <x-modal id="move-modal" title="نقل المرشّح">
            <form method="post" data-move-form>
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    بتنقل <strong data-move-name></strong> إلى <strong data-move-stage></strong>.
                </p>
                <input type="hidden" name="stage" data-move-stage-input>
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">سبب النقل (إلزاميّ)</span>
                    <textarea name="reason" rows="3" required minlength="3"
                              class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                              placeholder="اكتب سببًا واضحًا يفيد اللي هيقرأ السجلّ بعدك"></textarea>
                </label>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">أكّد النقل</button>
                    <button type="button" data-modal-close class="btn rounded-xl px-4 py-2 text-sm"
                            style="background: var(--surface-sunken)">رجوع</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection

@push('scripts')
<script>
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
