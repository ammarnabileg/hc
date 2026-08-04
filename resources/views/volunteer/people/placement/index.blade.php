@extends('layouts.volunteer')

@section('title', setting('volunteer.people_placement.title', 'القائمة النهائيّة والتسكين'))

@php
    /**
     * القوائم والتسكين (13.4-هـ · 24.4-12).
     * **عمودان في صفحة واحدة بلا تنقّل** — وعلى الموبايل: قائمة، والضغط يفتح **Bottom Sheet** (2.15-ج).
     * والامتلاء **مؤشّر لا مانع**: الممتلئ يبان بعلامة ولا يُخفى (13.4-ف).
     */
    $suggested = $placement->suggest($occupancy);
    $selectedRequests = $selected ? ($requests[$selected->id] ?? collect()) : collect();
    $pendingRequest = $selected ? $placement->pendingRequest($selected) : null;
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.people_placement.title', 'القائمة النهائيّة والتسكين')"
        subtitle="{{ $candidates->count() }} {{ setting('volunteer.people_placement.subtitle', 'مرشّحًا في القائمة') }}"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.people_placement.label', 'التوظيف')], ['label' => setting('volunteer.people_placement.label_2', 'القوائم والتسكين')]]" />

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.people_placement.label_3', 'مُرسَل / بانتظار الردّ')" :value="$counts['sent']" icon="envelope" />
        <x-kpi :label="setting('volunteer.people_placement.label_4', 'مقبول')" :value="$counts['accepted']" icon="✓" state="ok" />
        <x-kpi :label="setting('volunteer.people_placement.label_5', 'مرفوض')" :value="$counts['rejected']" icon="◉" state="danger" />
        <x-kpi :label="setting('volunteer.people_placement.label_6', 'فاتت المهلة')" :value="$counts['expired']" icon="▲" state="warn" />
    </div>

    <x-filters :action="route('volunteer.placement')">
        {{-- الترتيب Select والافتراضيّ «الأحدث أوّلًا» (13.4-هـ) --}}
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field', 'الترتيب') }}</span>
            <select name="sort" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ($sortOptions as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.people_placement.placeholder', 'الاسم أو الكود…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <x-slot:advanced>
            <label class="text-sm flex-1 min-w-56">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">
                    {{ setting('volunteer.people_placement.field_2', 'نطاق الدرجات') }} <output data-range-out>{{ $filters['score_min'] ?? $scoreFloor }}–{{ $filters['score_max'] ?? $scoreCeiling }}</output>
                </span>
                <div class="flex items-center gap-2">
                    <input type="range" name="score_min" data-range-min class="w-full" style="border: 0"
                           min="{{ $scoreFloor }}" max="{{ $scoreCeiling }}" step="1" value="{{ $filters['score_min'] ?? $scoreFloor }}">
                    <input type="range" name="score_max" data-range-max class="w-full" style="border: 0"
                           min="{{ $scoreFloor }}" max="{{ $scoreCeiling }}" step="1" value="{{ $filters['score_max'] ?? $scoreCeiling }}">
                </div>
            </label>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.common.apply', 'طبّق') }}</button>
        </x-slot:advanced>
    </x-filters>

    @if ($candidates->isEmpty())
        <x-empty :message="setting('volunteer.people_placement.empty', 'القائمة النهائيّة فاضية')" :action="setting('volunteer.people_placement.action', 'افتح لوحة المرشّحين')" :href="route('volunteer.recruitment')" />
    @else
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">

            {{-- يمين: كروت مضغوطة --}}
            <div class="space-y-2 md:max-h-[70vh] md:overflow-y-auto">
                @foreach ($candidates as $candidate)
                    @php
                        $isSelected = $selected && $selected->id === $candidate->id;
                        $inactive = ! $candidate->is_active_in_list;
                    @endphp
                    <a href="{{ route('volunteer.placement', array_filter(['candidate' => $candidate->id, 'sort' => $sort, 'q' => $filters['q']])) }}"
                       data-candidate-link
                       class="card p-3 flex items-center gap-2 motion-standard {{ $inactive ? 'opacity-60' : '' }}"
                       style="{{ $isSelected ? 'border-color: var(--color-brand-500)' : '' }}">
                        <x-avatar :user="$candidate->user" size="9" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold truncate">{{ $candidate->user?->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                #{{ $candidate->user?->code }} · {{ setting('volunteer.people_placement.link', 'الدرجة') }} {{ $candidate->qualifying_score ?? '—' }}
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach (($fits[$candidate->id] ?? collect()) as $entity)
                                    <span class="text-xs rounded-full px-2" style="background: var(--surface-sunken)">{{ $entity->name_ar }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-end space-y-1">
                            <x-state-badge :state="$pipeline->waitingState($candidate)" :label="$pipeline->waitingDays($candidate).setting('volunteer.people_placement.label_7', ' يومًا')" />
                            @if ($inactive)
                                {{-- المُسكَّن لا يختفي — يصير غير مفعَّل ويظلّ ظاهرًا للمخوَّلين (13.4-هـ) --}}
                                <x-state-badge state="idle" :label="setting('volunteer.people_placement.label_8', 'مُسكَّن — غير مفعَّل')" />
                            @endif
                            @if ($candidate->renewed_readiness)
                                <x-state-badge state="ok" :label="setting('volunteer.people_placement.label_9', 'جدّد استعداده')" />
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- يسار: بانل التفاصيل — وعلى الموبايل Bottom Sheet --}}
            @if ($selected)
                <aside class="card p-4 md:sticky md:top-24 h-fit" data-detail-panel>
                    <button type="button" class="md:hidden text-sm mb-2" data-sheet-close aria-label="{{ setting('volunteer.people_placement.aria', 'إغلاق') }}">✕ {{ setting('volunteer.people_placement.aria', 'إغلاق') }}</button>

                    <header class="flex items-start gap-3">
                        <x-avatar :user="$selected->user" size="12" />
                        <div class="min-w-0">
                            <h2 class="font-bold">{{ $selected->user?->name }}</h2>
                            <p class="text-xs" style="color: var(--text-muted)">#{{ $selected->user?->code }}</p>
                        </div>
                    </header>

                    @if ($line = $pipeline->returningLine($selected))
                        <p class="mt-3 rounded-xl px-3 py-2 text-xs"
                           style="background: color-mix(in srgb, var(--color-state-honor) 12%, transparent); color: var(--color-state-honor)">
                            <span aria-hidden="true">★</span> {{ setting('volunteer.people_placement.field_3', 'عائد —') }} {{ $line }}
                        </p>
                    @endif

                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <dt style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field_4', 'الدرجة الإجماليّة') }}</dt>
                        <dd class="font-semibold">{{ $selected->qualifying_score ?? '—' }}</dd>
                        <dt style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field_5', 'مدّة الانتظار') }}</dt>
                        <dd>{{ $pipeline->waitingDays($selected) }} {{ setting('volunteer.common.days', 'يومًا') }}</dd>
                    </dl>

                    @if (($fits[$selected->id] ?? collect())->isNotEmpty())
                        <div class="mt-3">
                            <h3 class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_placement.heading', 'الأقسام المناسبة') }}</h3>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($fits[$selected->id] as $entity)
                                    <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">{{ $entity->name_ar }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- حالات الطلبات بألوانها ورموزها + عدّاد المهلة --}}
                    @if ($selectedRequests->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach ($selectedRequests as $req)
                                <div class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">
                                    <div class="flex items-center justify-between gap-2">
                                        <span>{{ $req->entity?->name_ar }} — {{ $req->position?->name_ar }}</span>
                                        <x-state-badge :state="$placement->statusState($req->status)" :label="$statuses[$req->status] ?? $req->status" />
                                    </div>
                                    @if (in_array($req->status, ['sent', 'awaiting'], true))
                                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                                            {{ setting('volunteer.people_placement.field_6', 'باقي') }} {{ (int) now()->diffInHours($req->respond_due_at) }} {{ setting('volunteer.people_placement.field_7', 'ساعة من مهلة الـ') }}{{ $placement->responseHours() }} {{ setting('volunteer.common.hour', 'ساعة') }}
                                        </p>
                                        <form method="post" action="{{ route('volunteer.placement.withdraw', $req) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="btn rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised)">{{ setting('volunteer.people_placement.action_2', 'سحب الطلب') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($selected->user?->phone)
                            <a class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)"
                               href="https://wa.me/{{ preg_replace('/\D/', '', $selected->user->phone) }}?text={{ rawurlencode($placement->whatsappTemplate($selected)) }}">{{ setting('volunteer.common.whatsapp', 'واتساب') }}</a>
                        @endif

                        @if ($canPlace && ! $pendingRequest)
                            <button type="button" data-modal-open="place-modal"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_placement.action_3', 'تسكين') }}</button>
                        @elseif ($pendingRequest)
                            {{-- القفل المؤقّت: لا طلب آخر أثناء طلب معلَّق (13.4-هـ) --}}
                            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field_8', 'في طلب معلَّق قائم — استنّى الردّ أو اسحبه.') }}</p>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    @endif

    @if ($canPlace && $selected)
        <x-modal id="place-modal" :title="setting('volunteer.people_placement.tooltip', 'تسكين المرشّح')">
            <form method="post" action="{{ route('volunteer.placement.store', $selected) }}" class="space-y-3">
                @csrf

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field_9', 'القسم الفرعيّ') }}</span>
                    <select name="entity_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($occupancy as $row)
                            {{-- الممتلئ **يبان بعلامة ولا يُخفى** — مؤشّر لا مانع (13.4-ف) --}}
                            <option value="{{ $row['entity']->id }}"
                                    @selected($suggested && $suggested->id === $row['entity']->id)>
                                {{ $row['entity']->name_ar }} — {{ setting('volunteer.people_placement.option', 'إشغال') }} {{ $row['percent'] }}%{{ $row['full'] ? setting('volunteer.people_placement.text', ' (ممتلئ ▲)') : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                @if ($suggested)
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('volunteer.people_placement.field_10', 'اقتراحنا:') }} <strong>{{ $suggested->name_ar }}</strong> — {{ setting('volunteer.people_placement.field_11', 'الأقلّ إشغالًا دلوقتي. وإنت حرّ تختار غيره.') }}
                    </p>
                @endif

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.position', 'البوزشن') }}</span>
                    <select name="position_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}" @selected($position->rank === 1)>{{ $position->name_ar }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_placement.field_12', 'ملاحظة (اختياريّة)') }}</span>
                    <input type="text" name="note" class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                </label>

                <div class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-sunken); color: var(--text-muted)">
                    {{ setting('volunteer.people_placement.field_13', 'قالب واتساب:') }} {{ $placement->whatsappTemplate($selected, $suggested) }}
                </div>

                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('volunteer.people_placement.field_14', 'المرشّح عنده') }} {{ $placement->responseHours() }} {{ setting('volunteer.people_placement.field_15', 'ساعة يردّ — وبفواتها يرجع للقائمة تلقائيًّا.') }}
                </p>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_placement.action_4', 'أكّد إرسال الطلب') }}</button>
            </form>
        </x-modal>
    @endif
@endsection

@push('scripts')
<script>
(function () {
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

    // الموبايل: البانل يظهر كـBottom Sheet بعد اختيار مرشّح، والرجوع بزرّ (2.15-ج)
    const panel = document.querySelector('[data-detail-panel]');
    if (!panel) return;

    const isMobile = () => window.matchMedia('(max-width: 767px)').matches;
    const openSheet = () => {
        if (!isMobile()) return;
        panel.classList.add('fixed', 'inset-x-0', 'bottom-0', 'z-50', 'max-h-[85vh]', 'overflow-y-auto');
    };
    const closeSheet = () => panel.classList.remove('fixed', 'inset-x-0', 'bottom-0', 'z-50', 'max-h-[85vh]', 'overflow-y-auto');

    panel.querySelector('[data-sheet-close]')?.addEventListener('click', closeSheet);
    if (new URL(location.href).searchParams.has('candidate')) openSheet();
})();
</script>
@endpush
