@extends('layouts.volunteer')

@section('title', setting('volunteer.goals.title', 'الأهداف والمَعالِم'))

@php
    /**
     * الأهداف والمَعالِم (24.4 · 23 — 1.7).
     * سؤال واحد للشاشة: «أين يقف عملي من الأهداف المعتمَدة للتنفيذ؟»
     * ⭐ ولا شيء هنا يظهر قبل «إرسال للتنفيذ» — الفلترة في الكنترولر لا في الواجهة.
     */
    $completed = $goals->firstWhere('status', 'completed');
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.goals.title', 'الأهداف والمَعالِم')"
        :subtitle="setting('volunteer.goals.subtitle', 'النِّسَب بتصعد لوحدها من المهامّ — محدّش بيكتب تقرير حالة.')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer/goals')], ['label' => setting('volunteer.goals.title', 'الأهداف والمَعالِم')]]">
        <x-slot:action>
            @if ($completed)
                {{-- فعل رئيسيّ واحد بارز، والباقي داخل الكروت (2.15-أ-2) --}}
                <button type="button" data-modal-open="closing-report"
                        class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.goals.action', 'تقرير الإغلاق') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) — وعلى الموبايل شبكة 2×2 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.goals.label', 'أهداف مربوطة بكيانك')" :value="$kpis['goals']" icon="goal" />
        <x-kpi :label="setting('volunteer.goals.label_2', 'متوسّط الإنجاز')" :value="$kpis['progress'].'%'" icon="chart" />
        <x-kpi :label="setting('volunteer.goals.label_3', 'مَعالِم متحقّقة')" :value="$kpis['milestones']" icon="check" />
        <x-kpi :label="setting('volunteer.goals.label_4', 'مهامّ مُغلَقة (مستبعَدة)')" :value="$kpis['closed']" icon="○" />
    </div>

    {{-- 3 فلاتر ظاهرة + بحث (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.goals')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.status', 'الحالة') }}</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.goals.field', 'الأولويّة') }}</span>
            <select name="priority" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($priorities as $key => $label)
                    <option value="{{ $key }}" @selected((string) $filters['priority'] === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.entity', 'الكيان') }}</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.goals.option', 'كلّ كياناتي') }}</option>
                @foreach ($memberships as $membership)
                    <option value="{{ $membership->entity_id }}" @selected($filters['entity'] === (int) $membership->entity_id)>
                        {{ $membership->entity?->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.goals.placeholder', 'ابحث باسم الهدف…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($goals->isEmpty())
        {{-- الحالة الفارغة = سطر واحد + زرّ واحد (2.15-د) --}}
        <x-empty :message="setting('volunteer.goals.empty', 'مفيش أهداف مربوطة بكيانك حاليًّا')" :action="setting('volunteer.goals.action_2', 'افتح المشروع التشغيليّ')" :href="route('volunteer.project')" />
    @else
        <div class="space-y-4">
            @foreach ($goals as $goal)
                @php
                    $rows = $tree[$goal->id] ?? [];
                    $verified = collect($rows)->filter(fn ($r) => $r['milestone']->is_verified)->count();
                    $closed = collect($rows)->flatMap(fn ($r) => collect($r['packages'])->pluck('counts.closed'))->sum();
                    $overdue = $goal->end_date && $goal->end_date->isPast() && $goal->status !== 'completed';
                @endphp

                <article class="card p-4 animate-fadeup">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <h2 class="font-bold text-lg">{{ $goal->name }}</h2>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                {{-- معيار التحقّق: رقم من X إلى Y أو حالة نعم/لا (23 — 1.1) --}}
                                {{ setting('volunteer.goals.field_2', 'معيار التحقّق:') }}
                                @if ($goal->verification_type === 'numeric')
                                    {{ setting('volunteer.goals.field_3', 'رقميّ من') }} {{ rtrim(rtrim(number_format((float) $goal->target_from, 2), '0'), '.') }}
                                    {{ setting('volunteer.common.to', 'إلى') }} {{ rtrim(rtrim(number_format((float) $goal->target_to, 2), '0'), '.') }}
                                @else
                                    {{ setting('volunteer.goals.field_4', 'حالة قابلة للفحص بنعم/لا') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-state-badge :state="$overdue ? 'danger' : ($goal->status === 'completed' ? 'ok' : 'warn')"
                                           :label="$statuses[$goal->status] ?? $goal->status" />
                            <span class="text-xs" style="color: var(--text-muted)">
                                {{ setting('volunteer.goals.field_5', 'النهاية:') }} {{ $goal->end_date?->format('Y/m/d') ?? '—' }}
                            </span>
                        </div>
                    </div>

                    @include('volunteer.goals.partials.progress', [
                        'percent' => (float) $goal->progress_percent,
                        'label' => setting('volunteer.goals.label_5', 'الإنجاز بالصعود الآليّ'),
                        'closed' => $closed,
                    ])

                    <div class="mt-2 text-xs" style="color: var(--text-muted)">
                        <x-icon name="check" size="16" /> {{ setting('volunteer.goals.field_6', 'مَعالِم مكتملة:') }} <span class="font-semibold" style="color: var(--text)">{{ $verified }} {{ setting('volunteer.common.from', 'من') }} {{ count($rows) }}</span>
                    </div>

                    {{-- التفاصيل بالتوسيع لا بصفحة جديدة (2.15-أ-6) --}}
                    <details class="mt-3">
                        <summary class="text-sm cursor-pointer" style="color: var(--color-brand-500)">{{ setting('volunteer.goals.summary', 'المَعالِم وحزمها') }}</summary>

                        <div class="mt-3 space-y-3">
                            @forelse ($rows as $row)
                                @php $milestone = $row['milestone']; @endphp
                                <div class="rounded-xl p-3" style="background: var(--surface-raised)">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <button type="button" data-modal-open="milestone-{{ $milestone->id }}"
                                                class="text-sm font-semibold text-right">
                                            <x-icon :name="$milestone->is_verified ? 'check' : 'task'" size="14" /> {{ $milestone->name }}
                                        </button>
                                        <span class="text-xs" style="color: var(--text-muted)">
                                            {{ rtrim(rtrim(number_format((float) $milestone->progress_percent, 1), '0'), '.') }}%
                                        </span>
                                    </div>

                                    <div class="mt-2 space-y-1">
                                        @foreach ($row['packages'] as $entry)
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-1 text-xs items-center">
                                                <span class="md:col-span-2"><x-icon name="bundle" size="16" /> {{ $entry['package']->name }}
                                                    <span style="color: var(--text-muted)">· {{ $entry['package']->entity?->name_ar }}</span>
                                                </span>
                                                <span style="color: var(--text-muted)">
                                                    {{ setting('volunteer.goals.field_7', 'معتمدة') }} {{ $entry['counts']['done'] }} / {{ $entry['counts']['denominator'] }}
                                                    @if ($entry['counts']['closed'] > 0)
                                                        <span style="color: var(--color-state-idle)">· ○ {{ $entry['counts']['closed'] }} {{ setting('volunteer.goals.field_8', 'مُغلَقة مستبعَدة') }}</span>
                                                    @endif
                                                </span>
                                                <a class="hover:underline" style="color: var(--color-brand-500)"
                                                   href="{{ route('volunteer.packages.show', $entry['package']) }}">{{ setting('volunteer.goals.link', 'افتح الحزمة') }}</a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.goals.field_9', 'لسّه مفيش مَعالِم ظاهرة لكيانك في الهدف ده.') }}</p>
                            @endforelse
                        </div>
                    </details>
                </article>
            @endforeach
        </div>
    @endif

    {{-- ------------------------------------------------------------ البوب-أبات --}}
    @push('modals')
        @foreach ($tree as $goalId => $rows)
            @foreach ($rows as $row)
                @php $milestone = $row['milestone']; @endphp
                <x-modal :id="'milestone-'.$milestone->id" :title="$milestone->name">
                    <div class="space-y-3 text-sm">
                        <p style="color: var(--text-muted)">
                            {{ setting('volunteer.goals.field_10', 'معيار تحقّقه:') }} {{ $milestone->verification_criteria ?: setting('volunteer.goals.text', 'يتبع معيار الهدف') }}
                        </p>

                        <div>
                            <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.goals.field_11', 'حزمه والكيانات المرتبطة') }}</div>
                            <ul class="space-y-1">
                                @foreach ($row['packages'] as $entry)
                                    <li><x-icon name="bundle" size="16" /> {{ $entry['package']->name }} — {{ $entry['package']->entity?->name_ar }}
                                        ({{ rtrim(rtrim(number_format((float) $entry['package']->progress_percent, 1), '0'), '.') }}%)</li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($milestone->verification_status === 'declared')
                            <div class="rounded-xl p-3" style="background: var(--surface-raised)">
                                <div class="flex items-center gap-2">
                                    <x-state-badge state="warn" :label="setting('volunteer.goals.label_6', 'بانتظار اعتماد مشرف المسار')" />
                                    @if ($milestone->approval_due_at)
                                        <span class="text-xs" style="color: var(--text-muted)">
                                            {{ setting('volunteer.goals.field_12', 'المهلة:') }} {{ \Illuminate\Support\Carbon::parse($milestone->approval_due_at)->format('Y/m/d H:i') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs mt-2">{{ $milestone->evidence_note }}</p>
                            </div>
                        @endif

                        @if ($canDeclare && ! $milestone->is_verified && $milestone->verification_status !== 'declared')
                            <form method="post" action="{{ route('volunteer.goals.declare', $milestone) }}" class="space-y-2">
                                @csrf
                                <label class="block">
                                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.goals.field_13', 'الدليل المرفق (رابط أو وصف) — إلزاميّ') }}</span>
                                    <textarea name="evidence" rows="3" required minlength="10"
                                              class="w-full rounded-xl px-3 py-2 text-sm"
                                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                                              placeholder="{{ setting('volunteer.goals.placeholder_2', 'اكتب الدليل اللي يثبت تحقّق المعيار…') }}"></textarea>
                                </label>
                                <button type="submit"
                                        class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c">
                                    {{ setting('volunteer.goals.action_3', 'إعلان تحقّق المعيار بدليل مرفق') }}
                                </button>
                                <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.goals.field_14', 'مشرف المسار بيعتمده خلال نافذة القرار.') }}</p>
                            </form>
                        @endif

                        @if ($canApprove && $milestone->verification_status === 'declared')
                            <form method="post" action="{{ route('volunteer.goals.approve', $milestone) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <label class="block">
                                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.goals.field_15', 'ملاحظة (اختياريّة)') }}</span>
                                    <input type="text" name="note" class="w-full rounded-xl px-3 py-2 text-sm"
                                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                        style="background: var(--color-state-ok); color: #04201c">{{ setting('volunteer.goals.action_4', 'اعتماد التحقّق') }}</button>
                            </form>
                        @endif
                    </div>
                </x-modal>
            @endforeach
        @endforeach

        @if ($completed)
            <x-modal id="closing-report" :title="setting('volunteer.goals.tooltip', 'تقرير الإغلاق — شجرة النتائج')">
                <div class="space-y-2 text-sm">
                    @foreach ($goals->where('status', 'completed') as $goal)
                        <div class="rounded-xl p-3" style="background: var(--surface-raised)">
                            <div class="font-semibold">{{ $goal->name }}</div>
                            @foreach ($tree[$goal->id] ?? [] as $row)
                                <div class="text-xs mt-1">
                                    <x-icon :name="$row['milestone']->is_verified ? 'check' : 'task'" size="14" /> {{ $row['milestone']->is_verified ? setting('volunteer.goals.text_2', 'تحقّق') : setting('volunteer.goals.text_3', 'لم يتحقّق') }} —
                                    {{ $row['milestone']->name }}
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-modal>
        @endif
    @endpush
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.packages') }}"
       class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('volunteer.goals.link_2', 'حزم العمل وبنودها') }}</a>
@endsection
