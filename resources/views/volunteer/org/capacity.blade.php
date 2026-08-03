@extends('layouts.volunteer')

@section('title', 'السعة والأحمال')

@php
    /**
     * السعة والأحمال (13.4-ف · 24.4-7) — **مؤشّرات لا موانع**.
     * الشاشة **تقرأ فقط**: لا شاشات ضبط قيم هنا ولا زرّ يمنع إجراءً.
     */
    $tabs = [
        ['key' => 'span', 'label' => 'نطاق الإشراف', 'url' => request()->fullUrlWithQuery(['tab' => 'span'])],
        ['key' => 'occupancy', 'label' => 'الإشغال', 'url' => request()->fullUrlWithQuery(['tab' => 'occupancy'])],
        ['key' => 'gaps', 'label' => 'الفجوات', 'url' => request()->fullUrlWithQuery(['tab' => 'gaps'])],
        ['key' => 'loads', 'label' => 'الأحمال', 'url' => request()->fullUrlWithQuery(['tab' => 'loads'])],
    ];
@endphp

@section('content')
    <x-page-header
        title="السعة والأحمال"
        :subtitle="$root?->name_ar"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'قسمي'], ['label' => 'السعة والأحمال']]">
        <x-slot:action>
            @include('volunteer.org.partials.entity-switcher', ['action' => route('volunteer.capacity')])
        </x-slot:action>
    </x-page-header>

    {{-- بانر ثابت: السعة غير مانعة — نصّه من الإعدادات (2.13 · 13.4-ف) --}}
    <div class="card p-4 mb-4" style="border-color: var(--color-brand-600)">
        <div class="font-bold text-sm">{{ $banner }}</div>
        <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $difference }}</div>
    </div>

    @if (! $root)
        <x-empty message="الهيكل صغير — لا مؤشّرات تجاوز" action="الأعضاء والبوزشنز" :href="route('volunteer.department')" />
    @else
        <x-tabs :tabs="$tabs" :current="$tab" />

        @if ($tab === 'span')
            @forelse ($spans as $row)
                <div class="card p-4 mb-3">
                    <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
                        <div class="font-bold text-sm">{{ $row['position'] }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            الأدنى {{ $row['min'] ?? '—' }} · الافتراضيّ {{ $row['default'] ?? '—' }} · الأقصى {{ $row['max'] ?? 'بلا حدّ' }}
                        </div>
                    </div>
                    @foreach ($row['holders'] as $holder)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <span class="min-w-0 truncate">
                                {{ $holder['name'] }}
                                <span class="text-xs" style="color: var(--text-muted)">· {{ $holder['entity'] }}</span>
                            </span>
                            <span class="flex items-center gap-2 shrink-0">
                                <span class="font-bold">{{ $holder['actual'] }}</span>
                                <x-state-badge :state="$holder['state']" :label="$holder['state_label']" />
                            </span>
                        </div>
                    @endforeach
                </div>
            @empty
                <x-empty message="الهيكل صغير — لا مؤشّرات تجاوز" action="الأعضاء والبوزشنز" :href="route('volunteer.department')" />
            @endforelse

        @elseif ($tab === 'occupancy')
            <div class="card p-4">
                @forelse ($occupancy as $row)
                    <button type="button" class="w-full text-start py-3" style="border-top: 1px solid var(--border)"
                            data-capacity="{{ route('volunteer.capacity.entity', $row['entity_id']) }}">
                        <div class="flex items-center justify-between gap-2 text-sm mb-1">
                            <span class="font-semibold truncate">{{ $row['entity'] }}</span>
                            <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                {{ $row['members'] }} / {{ $row['cap'] }} — {{ $row['percent'] ?? '—' }}%
                            </span>
                        </div>
                        <div class="org-bar" style="block-size:8px; border-radius:999px; background: var(--surface-sunken); overflow:hidden">
                            <span style="display:block; block-size:100%;
                                         inline-size: {{ min(100, $row['percent'] ?? 0) }}%;
                                         background: var(--color-state-{{ $row['state'] }})"></span>
                        </div>
                    </button>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">لا كيانات فرعيّة بعد.</p>
                @endforelse
            </div>

        @elseif ($tab === 'gaps')
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">كيانات غير صحّيّة</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">تحت الحدّ الأدنى — والاقتراح اقتراح لا إلزام.</p>
                    @forelse ($unhealthy as $row)
                        <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ $row['name'] }} <span class="text-xs" style="color: var(--text-muted)">· {{ $row['position'] }} · {{ $row['entity'] }}</span></span>
                                <x-state-badge state="warn" :label="$row['actual'].' / '.$row['min']" />
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $row['suggestion'] }}</div>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">كلّ الكيانات فوق الحدّ الأدنى.</p>
                    @endforelse
                </div>

                <div class="card p-4">
                    <div class="text-sm font-bold mb-1">الشواغر</div>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">ومعها مرشّح سلّم الترقية الحاليّ وحالة «قائم بأعمال».</p>
                    @forelse ($vacancies as $row)
                        <div class="py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate font-semibold">{{ $row['entity'] }}</span>
                                @if ($row['acting'])
                                    <x-state-badge state="warn" :label="'قائم بأعمال: '.$row['acting']" />
                                @else
                                    <x-state-badge state="idle" label="بلا مسؤول" />
                                @endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                المرشّح: {{ $row['candidate'] ?? '—' }}{{ $row['candidate_position'] ? ' · '.$row['candidate_position'] : '' }}
                            </div>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مفيش شواغر مفتوحة.</p>
                    @endforelse
                </div>
            </div>

        @else
            <div class="card p-4">
                <div class="text-sm font-bold mb-1">الأحمال — الأقلّ حملًا أوّلًا</div>
                <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('volunteer.capacity.load_note', 'منطق الموازن: يقترح ولا يُلزِم') }}</p>
                @forelse ($loads as $row)
                    <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                        <span class="min-w-0 truncate">
                            {{ $row['name'] }}
                            <span class="text-xs" style="color: var(--text-muted)">· {{ $row['position'] }} · {{ $row['entity'] }}</span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="text-xs" style="color: var(--text-muted)">فريق {{ $row['team'] }} · مهامّ {{ $row['tasks'] }}</span>
                            @if ($row['suggested'])
                                <x-state-badge state="ok" :label="$row['suggestion_note']" />
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">لا أعضاء في هذا الكيان بعد.</p>
                @endforelse
            </div>
        @endif

        <x-modal id="capacity-modal" title="تفاصيل الكيان">
            <div data-capacity-body class="text-sm">
                <p style="color: var(--text-muted)">جارٍ التحميل…</p>
            </div>
        </x-modal>
    @endif
@endsection

@push('scripts')
<script>
/* بوب-أب الكيان — أرقام تُقرأ فقط، والتجاوز تنبيهٌ لا منع (13.4-ف) */
(() => {
    const modal = document.getElementById('capacity-modal');
    if (!modal) return;
    const body = modal.querySelector('[data-capacity-body]');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-capacity]');
        if (!trigger) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        body.innerHTML = '<p style="color: var(--text-muted)">جارٍ التحميل…</p>';
        fetch(trigger.dataset.capacity, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then((d) => {
                const members = d.members.map((m) =>
                    `<div class="flex items-center justify-between gap-2 py-1" style="border-top:1px solid var(--border)">
                        <span class="truncate">${m.name} <span class="text-xs" style="color: var(--text-muted)">· ${m.position}</span></span>
                        <span class="text-xs shrink-0" style="color: var(--text-muted)">تحته ${m.team}</span>
                     </div>`).join('');
                const breaches = d.breaches.length
                    ? d.breaches.map((b) => `<div class="text-xs py-1">${b.name}: ${b.actual} فوق الحدّ ${b.max} — ${d.notice}</div>`).join('')
                    : `<div class="text-xs" style="color: var(--text-muted)">لا تجاوزات.</div>`;
                body.innerHTML = `
                    <div class="font-bold mb-1">${d.entity}</div>
                    <div class="text-xs mb-3" style="color: var(--text-muted)">نسبة الإشغال: ${d.percent === null ? '—' : d.percent + '%'}</div>
                    <div class="mb-3">${members}</div>
                    <div class="card p-2">${breaches}</div>`;
            })
            .catch(() => { body.innerHTML = '<p>تعذّر تحميل التفاصيل — جرّب تاني.</p>'; });
    });
})();
</script>
@endpush
