@extends('layouts.volunteer')

@section('title', 'الاعتراضات')

@php
    /**
     * ⬆️ الاعتراضات المصعَّدة إليّ (الدستور 24.4-8 · 13.4-ط).
     *
     * **الغرض:** الفصل في اعتراضات داونلايني على معاملاتهم.
     * **مَن يراها:** المسؤول المباشر عن المعترِض ومَن فوقه بالتصعيد — والحصر
     * على الخادم في `ObjectionDesk`: لا يظهر هنا إلّا ما هو على مكتبي فعلًا.
     *
     * تخطيط «نصّان» (2.15-ب): يمين القائمة ويسار التفاصيل، والتبديل فوريّ
     * بلا انتقال صفحة — وعلى الموبايل شاشة واحدة + Bottom Sheet بلا تمرير أفقيّ.
     */
    $selectedId = $selected?->id;

    /** رابط رقاقة الحالة: يحفظ بقيّة الفلاتر ويُسقِط الفارغ منها */
    $chip = fn (string $status) => route('volunteer.escalations.objections', array_filter(
        ['status' => $status, 'entity' => $filters['entity'], 'days' => $filters['days'], 'q' => $filters['q']],
        fn ($v) => $v !== '' && $v !== 0 && $v !== null,
    ));
@endphp

@section('content')
    <x-page-header
        title="الاعتراضات"
        subtitle="الفصل في اعتراضات داونلايني على معاملاتهم."
        :breadcrumbs="[['label' => 'يحتاج قرارك', 'url' => route('volunteer.escalations')], ['label' => 'الاعتراضات']]" />

    {{-- الهيدر: عدّادات الحالات · إبراز المتأخّر عن SLA · مبدّل الكيان --}}
    <div class="flex flex-wrap items-center gap-2 mb-3 text-xs">
        @foreach ($counts as $status => $count)
            <a href="{{ $chip($status) }}"
               class="rounded-full px-3 py-1 motion-standard"
               style="{{ $filters['status'] === $status ? 'background: var(--color-brand-500); color:#04201c' : 'border: 1px solid var(--border)' }}">
                {{ $service->statusLabel($status) }} ({{ $count }})
            </a>
        @endforeach

        @if ($filters['status'] !== '')
            <a href="{{ route('volunteer.escalations.objections') }}" class="rounded-full px-3 py-1"
               style="border: 1px solid var(--border)">الكلّ</a>
        @endif

        {{-- إبراز المتأخّر عن الـSLA — بلون ورمز معًا (2.16-ج) --}}
        @if ($overdue > 0)
            <x-state-badge state="danger" :label="'متأخّر عن الـSLA: '.$overdue" />
        @endif

        {{-- مبدّل الكيان: القسم/الفرعيّ الذي جاء منه المعترِض --}}
        @if ($entities->isNotEmpty())
            <form method="get" action="{{ route('volunteer.escalations.objections') }}" class="ms-auto">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <input type="hidden" name="days" value="{{ $filters['days'] }}">
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <label class="flex items-center gap-2">
                    <span style="color: var(--text-muted)">
                        @include('volunteer.meetings.partials.icon', ['name' => 'entity']) الكيان
                    </span>
                    <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-xs"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">كلّ الكيانات</option>
                        @foreach ($entities as $entity)
                            <option value="{{ $entity->id }}" @selected($filters['entity'] === (int) $entity->id)>{{ $entity->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        @endif
    </div>

    {{-- تنويه ثابت — نصُّه إعدادٌ لا حرفٌ محروق (2.13) --}}
    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        <span aria-hidden="true">▲</span> {{ $notice }}
    </p>

    <x-filters :action="route('volunteer.escalations.objections')">
        <input type="hidden" name="entity" value="{{ $filters['entity'] ?: '' }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($counts as $status => $count)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $service->statusLabel($status) }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة (يوم)</span>
            <input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}"
                   class="rounded-xl px-3 py-2 text-sm w-24"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-sm flex-1 min-w-0">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث برقم المعاملة أو الاسم</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    {{-- الحالة: خطأ ⟵ رسالة + إعادة (لا صفحة بيضاء) --}}
    @if ($errors->any())
        <div class="card p-4 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-danger)">
            <p class="mb-2"><span aria-hidden="true">◉</span> {{ $errors->first() }}</p>
            <a href="{{ route('volunteer.escalations.objections') }}"
               class="btn inline-flex rounded-xl px-4 py-2 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c">إعادة</a>
        </div>
    @endif

    {{-- الحالة: تحميل ⟵ Skeleton للعمودين معًا (لا مستطيل عامّ) --}}
    <div class="grid gap-4 md:grid-cols-[20rem_1fr]" data-objection-desk-skeleton hidden aria-hidden="true">
        <div class="space-y-2">
            @for ($i = 0; $i < 3; $i++)
                <div class="card p-3 space-y-2">
                    <span class="block h-3 rounded animate-shimmer" style="width:55%;background: var(--surface-sunken)"></span>
                    <span class="block h-3 rounded animate-shimmer" style="width:80%;background: var(--surface-sunken)"></span>
                </div>
            @endfor
        </div>
        <div class="card p-4 space-y-3">
            @for ($i = 0; $i < 5; $i++)
                <span class="block h-3 rounded animate-shimmer" style="width:{{ 90 - $i * 10 }}%;background: var(--surface-sunken)"></span>
            @endfor
        </div>
    </div>

    {{-- الحالة: فارغة --}}
    @if ($rows->isEmpty())
        <x-empty :message="$emptyMessage" action="روح ليحتاج قرارك" :href="route('volunteer.escalations')" />
    @else
        <div class="grid gap-4 md:grid-cols-[20rem_1fr]" data-objection-desk>
            {{-- يمين: القائمة (# · رقم المعاملة · الحالة · المسؤول الحاليّ · التاريخ · شارة تأخّر) --}}
            <div class="space-y-2" data-desk-list>
                @foreach ($rows as $objection)
                    <button type="button" data-desk-select="{{ $objection->id }}"
                            class="card p-3 w-full text-start motion-standard"
                            @if ($selectedId === $objection->id) style="border-color: var(--color-brand-600)" @endif>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs truncate" style="color: var(--text-muted)">
                                #{{ $objection->id }} · معاملة #{{ $objection->transaction_id }}
                            </span>
                            <x-state-badge :state="$service->statusState($objection->status)"
                                           :label="$service->statusLabel($objection->status)" />
                        </div>
                        <div class="mt-1 text-sm truncate">{{ $objection->user?->name }}</div>
                        <div class="mt-1 flex items-center justify-between gap-2 text-xs" style="color: var(--text-muted)">
                            <span class="truncate">المسؤول: {{ $objection->current_handler?->name ?? '—' }}</span>
                            <span class="shrink-0">{{ $objection->created_at?->format('Y-m-d') }}</span>
                        </div>
                        @if ($service->isOverdue($objection))
                            <div class="mt-1"><x-state-badge state="danger" label="متأخّر" /></div>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- يسار: التفاصيل — تبديل فوريّ عند اختيار اعتراض آخر --}}
            <div data-desk-panels>
                @foreach ($rows as $objection)
                    <section data-desk-panel="{{ $objection->id }}"
                             class="@if ($selectedId !== $objection->id) hidden @endif">
                        @include('volunteer.escalations.partials.objection-desk-panel', [
                            'objection' => $objection,
                            'service' => $service,
                            'desk' => $desk,
                            'ladder' => $ladders[$objection->id] ?? collect(),
                            'entity' => $entityOf($objection),
                            'decider' => $deciders[$objection->decided_by] ?? null,
                            'notice' => $notice,
                        ])
                    </section>
                @endforeach
            </div>
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.escalations.objections', ['status' => 'escalated']) }}"
       class="btn block text-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        المُصعَّد إليّ ({{ $counts['escalated'] ?? 0 }})
    </a>
@endsection

@push('modals')
    @foreach ($rows as $objection)
        @if ($desk->mayDecide(auth()->user(), $objection))
            @include('volunteer.escalations.partials.objection-desk-modals', [
                'objection' => $objection,
                'service' => $service,
                'notice' => $notice,
            ])
        @endif
    @endforeach
@endpush

@push('scripts')
    <script>
        /* تبديل فوريّ بلا انتقال صفحة (24.4-8)، وSkeleton أثناء إعادة الفلترة */
        (function () {
            const list = document.querySelector('[data-desk-list]');
            const panels = document.querySelector('[data-desk-panels]');
            const grid = document.querySelector('[data-objection-desk]');
            const skeleton = document.querySelector('[data-objection-desk-skeleton]');
            const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

            if (list && panels) {
                const show = (id) => {
                    panels.querySelectorAll('[data-desk-panel]').forEach((p) => {
                        p.classList.toggle('hidden', p.dataset.deskPanel !== String(id));
                    });
                    list.querySelectorAll('[data-desk-select]').forEach((b) => {
                        b.style.borderColor = b.dataset.deskSelect === String(id) ? 'var(--color-brand-600)' : '';
                    });
                    panels.classList.toggle('sheet-open', isMobile());
                    if (isMobile()) document.body.style.overflow = 'hidden';
                    const url = new URL(window.location.href);
                    url.searchParams.set('objection', id);
                    history.replaceState(null, '', url);
                };

                list.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-desk-select]');
                    if (btn) show(btn.dataset.deskSelect);
                });

                panels.addEventListener('click', (e) => {
                    if (e.target.closest('[data-sheet-close]')) {
                        panels.classList.remove('sheet-open');
                        document.body.style.overflow = '';
                    }
                });
            }

            /* الحالة «تحميل»: الفلتر يُرسَل ⟵ الشبكة تُستبدَل بالـSkeleton فورًا */
            document.querySelectorAll('form').forEach((form) => {
                form.addEventListener('submit', () => {
                    if (!skeleton) return;
                    if (form.method && form.method.toLowerCase() !== 'get') return;
                    skeleton.hidden = false;
                    if (grid) grid.hidden = true;
                });
            });
        })();
    </script>
    <style>
        @media (max-width: 767px) {
            [data-desk-panels]:not(.sheet-open) { display: none; }
            [data-desk-panels].sheet-open {
                position: fixed; inset-block-end: 0; inset-inline: 0; z-index: 50;
                max-block-size: 88vh; overflow-y: auto;
                background: var(--surface); border-start-start-radius: 1rem; border-start-end-radius: 1rem;
                border-block-start: 1px solid var(--border); padding: 1rem;
            }
        }
        @media (min-width: 768px) { [data-sheet-close] { display: none; } }
        /* ممنوع التمرير الأفقيّ على 375px (2.15-ج) */
        [data-objection-desk], [data-objection-desk] * { min-inline-size: 0; }
    </style>
@endpush
