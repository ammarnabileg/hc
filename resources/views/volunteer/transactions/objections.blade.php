@extends('layouts.volunteer')

@section('title', setting('volunteer.transactions_objections.title', 'اعتراضاتي'))

@php
    /**
     * اعتراضاتي (24.4 · 13.4-ط) — تخطيط «قائمة + بانل» الموحَّد (2.15-ب).
     * التبديل فوريّ بلا انتقال صفحة، وعلى الموبايل شاشة واحدة + Bottom Sheet.
     * ولا أزرار ردّ/تصعيد هنا — تلك للمسؤول في صفحة التصعيدات.
     */
    $sign = fn (float $v) => $v > 0 ? '+' : ($v < 0 ? '−' : '');
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.transactions_objections.title', 'اعتراضاتي')"
        :subtitle="setting('volunteer.transactions_objections.subtitle', 'الباب مفتوح ').$service->windowDays().setting('volunteer.transactions_objections.subtitle_2', ' أيّام من كلّ معاملة')"
        :breadcrumbs="[['label' => setting('volunteer.transactions_objections.label', 'معاملاتي'), 'url' => route('volunteer.transactions')], ['label' => setting('volunteer.transactions_objections.title', 'اعتراضاتي')]]" />

    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs">
        @foreach ($counts as $status => $count)
            <a href="{{ route('volunteer.objections', ['status' => $status]) }}"
               class="rounded-full px-3 py-1"
               style="{{ $filters['status'] === $status ? 'background: var(--color-brand-500); color:#04201c' : 'border: 1px solid var(--border)' }}">
                {{ $service->statusLabel($status) }} ({{ $count }})
            </a>
        @endforeach
        @if ($overdue > 0)
            <x-state-badge state="danger" :label="setting('volunteer.transactions_objections.label_2', 'متأخّر عن الـSLA: ').$overdue" />
        @endif
        @if ($filters['status'] !== '')
            <a href="{{ route('volunteer.objections') }}" class="rounded-full px-3 py-1" style="border: 1px solid var(--border)">{{ setting('volunteer.common.all', 'الكلّ') }}</a>
        @endif
    </div>

    @if ($rows->isEmpty())
        <x-empty message="{{ setting('volunteer.transactions_objections.empty', 'مفيش اعتراضات — والباب مفتوح') }} {{ $service->windowDays() }} {{ setting('volunteer.transactions_objections.empty_2', 'أيّام من كلّ معاملة') }}"
                 :action="setting('volunteer.transactions_objections.action', 'روح لمعاملاتي')" :href="route('volunteer.transactions')" />
    @else
        <div class="grid gap-4 md:grid-cols-[20rem_1fr]">
            {{-- يمين: القائمة --}}
            <div class="space-y-2" data-objections-list>
                @foreach ($rows as $objection)
                    @php $overdueRow = $service->isOverdue($objection); @endphp
                    <button type="button" data-objection-select="{{ $objection->id }}"
                            class="card p-3 w-full text-start motion-standard"
                            @if ($selected && $selected->id === $objection->id) style="border-color: var(--color-brand-600)" @endif>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs" style="color: var(--text-muted)">
                                #{{ $objection->id }} · {{ setting('volunteer.transactions_objections.action_2', 'معاملة #') }}{{ $objection->transaction_id }}
                            </span>
                            <x-state-badge :state="$service->statusState($objection->status)"
                                           :label="$service->statusLabel($objection->status)" />
                        </div>
                        <div class="mt-1 text-sm truncate">{{ $objection->reason }}</div>
                        <div class="mt-1 flex items-center justify-between text-xs" style="color: var(--text-muted)">
                            <span>{{ setting('volunteer.transactions_objections.action_3', 'المسؤول:') }} {{ $objection->current_handler?->name ?? setting('volunteer.transactions_objections.text', 'بانتظار الإسناد') }}</span>
                            <span>{{ $objection->created_at?->format('Y-m-d') }}</span>
                        </div>
                        @if ($overdueRow)
                            <div class="mt-1"><x-state-badge state="danger" :label="setting('volunteer.transactions_objections.label_3', 'متأخّر')" /></div>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- يسار: التفاصيل — وعلى الموبايل Bottom Sheet برأس ثابت وجسم متمرّر --}}
            <div data-objection-panels>
                @foreach ($rows as $objection)
                    <section data-objection-panel="{{ $objection->id }}"
                             class="@if (! $selected || $selected->id !== $objection->id) hidden @endif">
                        @include('volunteer.transactions.partials.objection-panel', [
                            'objection' => $objection,
                            'service' => $service,
                            'ladder' => $ladders[$objection->id] ?? collect(),
                        ])
                    </section>
                @endforeach
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            /* التبديل فوريّ بلا انتقال صفحة (13.4-ط)، وعلى الموبايل Bottom Sheet (2.15-ج) */
            (function () {
                const list = document.querySelector('[data-objections-list]');
                const panels = document.querySelector('[data-objection-panels]');
                if (!list || !panels) return;

                const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

                const show = (id) => {
                    panels.querySelectorAll('[data-objection-panel]').forEach((p) => {
                        p.classList.toggle('hidden', p.dataset.objectionPanel !== String(id));
                    });
                    list.querySelectorAll('[data-objection-select]').forEach((b) => {
                        b.style.borderColor = b.dataset.objectionSelect === String(id) ? 'var(--color-brand-600)' : '';
                    });
                    panels.classList.toggle('sheet-open', isMobile());
                    if (isMobile()) document.body.style.overflow = 'hidden';
                    history.replaceState(null, '', '?objection=' + id);
                };

                list.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-objection-select]');
                    if (btn) show(btn.dataset.objectionSelect);
                });

                panels.addEventListener('click', (e) => {
                    if (e.target.closest('[data-sheet-close]')) {
                        panels.classList.remove('sheet-open');
                        document.body.style.overflow = '';
                    }
                });
            })();
        </script>
        <style>
            @media (max-width: 767px) {
                [data-objection-panels]:not(.sheet-open) { display: none; }
                [data-objection-panels].sheet-open {
                    position: fixed; inset-block-end: 0; inset-inline: 0; z-index: 50;
                    max-block-size: 88vh; overflow-y: auto;
                    background: var(--surface); border-start-start-radius: 1rem; border-start-end-radius: 1rem;
                    border-block-start: 1px solid var(--border); padding: 1rem;
                }
            }
            @media (min-width: 768px) { [data-sheet-close] { display: none; } }
        </style>
    @endpush
@endsection
