@extends('layouts.volunteer')

@section('title', 'نتيجة المقابلة')

@php
    /**
     * Scorecard (13.4-د · 24.4-12).
     * - المعايير بمنزلق /10 بأوزانها و**درجة إجماليّة تلقائيّة**.
     * - **حفظ تلقائيّ كمسودّة** + مؤشّر الحقول الناقصة + معاينة ملخّص.
     * - **المعيار المؤرشف يُعرَض بدرجته بوسم «معيار مؤرشف»** ولا يدخل حساب اليوم.
     */
    $selectedIds = $selectedFits->pluck('id')->all();
    $candidate = $interview->recruitment_candidate;
@endphp

@section('content')
    <x-page-header
        title="نتيجة المقابلة"
        :subtitle="$candidate?->user?->name.' — '.$interview->scheduled_at->translatedFormat('j F Y')"
        :breadcrumbs="[
            ['label' => 'لوحة التطوّع', 'url' => url('/volunteer')],
            ['label' => 'المقابلات', 'url' => route('volunteer.interviews')],
            ['label' => 'النتيجة'],
        ]">
        <x-slot:action>
            <span class="text-xs" data-saved style="color: var(--color-state-ok)"></span>
        </x-slot:action>
    </x-page-header>

    <form data-scorecard data-autosave-url="{{ route('volunteer.interviews.scorecard.autosave', $interview) }}" class="space-y-4">
        @csrf

        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">المهارات وتحليل الشخصيّة</h2>

            <label class="block text-sm mb-3">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">المهارات</span>
                <textarea name="skills_notes" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                          placeholder="{{ setting('scorecards.skills.placeholder', 'اكتب أمثلة ملموسة شفتها في المقابلة — مش صفات عامّة.') }}"
                          @readonly(! $canEdit)>{{ $card->skills_notes }}</textarea>
            </label>

            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">تحليل الشخصيّة</span>
                <textarea name="personality_notes" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                          placeholder="{{ setting('scorecards.personality.placeholder', 'إزاي بيتعامل مع الضغط والاختلاف؟ اذكر موقفًا.') }}"
                          @readonly(! $canEdit)>{{ $card->personality_notes }}</textarea>
            </label>
        </section>

        <section class="card p-4">
            <header class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-sm">المعايير</h2>
                <div class="text-sm">
                    الدرجة الإجماليّة:
                    <strong data-total>{{ $card->total_score ?? '0.00' }}</strong>/{{ $scale }}
                </div>
            </header>

            @forelse ($rows as $row)
                <div class="mb-4">
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span>
                            {{ $row['label'] }}
                            @if ($row['archived'])
                                {{-- الرماديّ = مؤرشف، وليس حالةً سيّئة (2.16-أ) --}}
                                <x-state-badge state="idle" :label="$archivedTag" />
                            @else
                                <span class="text-xs" style="color: var(--text-muted)">وزن {{ $row['weight'] }}</span>
                            @endif
                        </span>
                        <output data-score-out="{{ $row['id'] }}">{{ $row['score'] ?? 0 }}</output>
                    </div>

                    @if ($row['archived'])
                        {{-- المؤرشف يُعرَض بدرجته ولا يُعاد إدخاله --}}
                        <div class="h-1 rounded-full" style="background: var(--surface-sunken)">
                            <div class="h-1 rounded-full" style="width: {{ ($row['score'] ?? 0) / $scale * 100 }}%; background: var(--color-state-idle)"></div>
                        </div>
                    @else
                        <input type="range" class="w-full" style="border: 0"
                               name="criteria_scores[{{ $row['id'] }}]" data-criterion="{{ $row['id'] }}"
                               data-weight="{{ $row['weight'] }}"
                               min="0" max="{{ $scale }}" step="1" value="{{ $row['score'] ?? 0 }}"
                               @disabled(! $canEdit)>
                    @endif
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">مفيش معايير مفعّلة حاليًّا.</p>
            @endforelse
        </section>

        {{-- سكشن «الأقسام المناسبة»: شجرة باختيار متعدّد + عدّاد + بحث (13.4-د) --}}
        <section class="card p-4">
            <header class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-sm">الأقسام المناسبة</h2>
                <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">
                    <span data-fits-count>{{ count($selectedIds) }}</span> مختار
                </span>
            </header>

            <input type="search" data-fits-search placeholder="ابحث عن قسم…" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">

            <div class="space-y-3 max-h-72 overflow-y-auto">
                @foreach ($tree as $root)
                    <div data-fit-group>
                        <label class="flex items-center gap-2 text-sm font-semibold" data-fit-label="{{ $root->name_ar }}">
                            <input type="checkbox" name="entity_ids[]" value="{{ $root->id }}" data-fit-root
                                   @checked(in_array($root->id, $selectedIds, true)) @disabled(! $canEdit)>
                            {{ $root->name_ar }}
                        </label>
                        <div class="ps-6 mt-1 space-y-1">
                            @foreach ($root->children as $child)
                                <label class="flex items-center gap-2 text-sm" data-fit-label="{{ $child->name_ar }}">
                                    <input type="checkbox" name="entity_ids[]" value="{{ $child->id }}" data-fit-child
                                           @checked(in_array($child->id, $selectedIds, true)) @disabled(! $canEdit)>
                                    {{ $child->name_ar }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- مؤشّر الحقول الناقصة — يتحدّث مع كلّ حفظ تلقائيّ (2.15-د) --}}
        <div class="card p-4" data-missing-box style="{{ $missing ? '' : 'display:none' }}">
            <p class="text-sm">
                <x-state-badge state="warn" label="ناقص" />
                <span data-missing-list>{{ implode(' · ', $missing) }}</span>
            </p>
        </div>
    </form>

    <section class="card p-4 mt-4">
        <h2 class="font-bold text-sm mb-2">معاينة الملخّص</h2>
        <pre class="text-xs whitespace-pre-wrap" data-preview style="color: var(--text-muted)">{{ $engine->summary($card) }}</pre>
    </section>

    @if ($canEdit)
        <div class="card p-4 mt-4">
            <h2 class="font-bold text-sm mb-3">القرار</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <form method="post" action="{{ route('volunteer.interviews.scorecard.decide', $interview) }}">
                    @csrf
                    <input type="hidden" name="decision" value="passed">
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">نجح ⟵ القائمة النهائيّة</button>
                </form>

                <form method="post" action="{{ route('volunteer.interviews.scorecard.decide', $interview) }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="decision" value="rejected">
                    <input type="text" name="rejection_reason" placeholder="سبب الرفض" required
                           class="flex-1 rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">رفض</button>
                </form>
            </div>

            <a href="{{ route('volunteer.interviews.scorecard.export', $interview) }}"
               class="btn inline-flex mt-3 rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">تصدير</a>
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.interviews') }}"
       class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">رجوع للمقابلات</a>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.querySelector('[data-scorecard]');
    if (!form) return;

    const scale = {{ $scale }};
    const totalOut = document.querySelector('[data-total]');
    const savedOut = document.querySelector('[data-saved]');
    const missingBox = document.querySelector('[data-missing-box]');
    const missingList = document.querySelector('[data-missing-list]');
    const fitsCount = document.querySelector('[data-fits-count]');

    // الدرجة الإجماليّة تلقائيّة — متوسّط موزون، واستجابة لحظيّة للرقم (24.4-12)
    const recompute = () => {
        let weighted = 0, weights = 0;
        form.querySelectorAll('[data-criterion]').forEach((input) => {
            const w = Number(input.dataset.weight) || 1;
            weighted += Number(input.value) * w;
            weights += w;
            const out = document.querySelector(`[data-score-out="${input.dataset.criterion}"]`);
            if (out) out.textContent = input.value;
        });
        if (totalOut) totalOut.textContent = weights ? (weighted / weights).toFixed(2) : '0.00';
    };

    let timer = null;
    const save = () => {
        const data = new FormData(form);
        fetch(form.dataset.autosaveUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: data,
        })
            .then((r) => r.json())
            .then((json) => {
                // «اتحفظ ✓» بجوار الحقل مع الحفظ التلقائيّ (2.17-ب)
                if (savedOut) savedOut.textContent = json.message || 'اتحفظ ✓';
                if (totalOut && typeof json.total === 'number') totalOut.textContent = json.total.toFixed(2);
                if (missingBox && missingList) {
                    missingList.textContent = (json.missing || []).join(' · ');
                    missingBox.style.display = (json.missing || []).length ? '' : 'none';
                }
            })
            .catch(() => { if (savedOut) savedOut.textContent = 'الشبكة وقعت — شغلك محفوظ هنا لحدّ ما ترجع.'; });
    };

    const queue = () => { clearTimeout(timer); timer = setTimeout(save, 800); };

    form.addEventListener('input', (e) => {
        if (e.target.matches('[data-criterion]')) recompute();
        if (!e.target.matches('[data-fits-search]')) queue();
    });

    // اختيار الرئيسيّ يحدّد فرعيّاته تلقائيًّا (13.4-د)
    form.querySelectorAll('[data-fit-root]').forEach((root) => {
        root.addEventListener('change', () => {
            root.closest('[data-fit-group]').querySelectorAll('[data-fit-child]').forEach((c) => { c.checked = root.checked; });
            countFits();
        });
    });

    const countFits = () => {
        const n = form.querySelectorAll('input[name="entity_ids[]"]:checked').length;
        if (fitsCount) fitsCount.textContent = n;
    };
    form.addEventListener('change', countFits);

    const search = form.querySelector('[data-fits-search]');
    search?.addEventListener('input', () => {
        const term = search.value.trim();
        form.querySelectorAll('[data-fit-label]').forEach((label) => {
            label.style.display = !term || label.dataset.fitLabel.includes(term) ? '' : 'none';
        });
    });

    recompute();
})();
</script>
@endpush
