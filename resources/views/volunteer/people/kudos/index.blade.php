@extends('layouts.volunteer')

@section('title', 'Kudos')

@php
    /**
     * Kudos (13.4-ي · 24.4-11).
     * **نصّ السبب بارز** — القصّة أهمّ من العدّاد. والحدود تُشرَح لحظة بلوغها فقط (2.15-د).
     */
    $limitsLine = $dailyLimit.'/يوم · '.$weeklyLimit.' أفراد/أسبوع';
@endphp

@section('content')
    <x-page-header
        title="Kudos"
        :subtitle="'اليوم '.$sentToday.'/'.$dailyLimit.' · الأسبوع '.$peopleThisWeek.'/'.$weeklyLimit.' أفراد'"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'التقدير'], ['label' => 'Kudos']]">
        <x-slot:action>
            @if ($canSend)
                <button type="button" data-modal-open="kudos-modal"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">شكر زميل</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        <x-kpi label="إجمالي Kudos وصلني" :value="$totalReceived" icon="contribution" />
        <x-kpi label="بعتّه اليوم" :value="$sentToday" icon="envelope" :hint="$limitsLine" />
        <x-kpi label="أفراد شكرتهم الأسبوع ده" :value="$peopleThisWeek" icon="people" :hint="$limitsLine" />
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'received', 'label' => 'وصلني', 'url' => route('volunteer.kudos', ['tab' => 'received'])],
        ['key' => 'sent', 'label' => 'أرسلته', 'url' => route('volunteer.kudos', ['tab' => 'sent'])],
        ['key' => 'wall', 'label' => 'حائط الشكر', 'url' => route('volunteer.kudos.wall')],
    ]" />

    <x-filters :action="route('volunteer.kudos')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ([30 => 'آخر 30 يومًا', 90 => 'آخر 90 يومًا', 0 => 'من البداية'] as $days => $label)
                    <option value="{{ $days }}" @selected((int) $filters['days'] === $days)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث في السبب</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="كلمة من نصّ الشكر…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty message="مفيش شكرات بعد — ابدأ أنت" />
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                @php $person = $tab === 'sent' ? $item->receiver : $item->sender; @endphp

                <article class="card p-4 animate-fadeup">
                    <div class="flex items-center gap-2">
                        <x-avatar :user="$person" size="9" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold truncate">{{ $person?->name }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">{{ $item->created_at->diffForHumans() }}</div>
                        </div>
                        <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">+{{ (int) $item->vxp_awarded }} VXP</span>
                    </div>

                    {{-- السبب بارز — هو الحكاية كلّها --}}
                    <p class="mt-3 text-base leading-7">{{ $item->reason }}</p>
                </article>
            @endforeach
        </div>
    @endif

    @if ($canSend)
        <x-modal id="kudos-modal" title="شكر زميل">
            <form method="post" action="{{ route('volunteer.kudos.store') }}" class="space-y-3">
                @csrf

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">ابحث بالاسم أو الكود</span>
                    <input type="search" data-kudos-search placeholder="اكتب حرفين على الأقلّ…"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                </label>

                <div data-kudos-results class="space-y-1"></div>
                <input type="hidden" name="receiver_id" data-kudos-receiver required>
                <p class="text-sm" data-kudos-chosen style="color: var(--color-state-ok)"></p>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">سبب الشكر (إلزاميّ)</span>
                    <textarea name="reason" rows="4" required minlength="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                              placeholder="{{ setting('kudos.reason.placeholder', 'احكِ الموقف نفسه — الحكاية هي اللي بتفضل.') }}">{{ old('reason') }}</textarea>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    الحدّ: {{ $limitsLine }} — وأشخاص مختلفون فقط.
                </p>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">ابعت الشكر</button>
            </form>
        </x-modal>
    @endif
@endsection

@section('mobile_action')
    @if ($canSend)
        <button type="button" data-modal-open="kudos-modal"
                class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">شكر زميل</button>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    const search = document.querySelector('[data-kudos-search]');
    const results = document.querySelector('[data-kudos-results]');
    const receiver = document.querySelector('[data-kudos-receiver]');
    const chosen = document.querySelector('[data-kudos-chosen]');
    if (!search) return;

    let timer = null;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }

        timer = setTimeout(() => {
            fetch(`{{ route('volunteer.kudos.search') }}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then((r) => r.json())
                .then((json) => {
                    results.innerHTML = '';
                    (json.results || []).forEach((person) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn w-full text-start rounded-xl px-3 py-2 text-sm';
                        btn.style.background = 'var(--surface-sunken)';
                        btn.textContent = `${person.name} — #${person.code}`;
                        btn.addEventListener('click', () => {
                            receiver.value = person.id;
                            chosen.textContent = `اخترت: ${person.name} ✓`;
                            results.innerHTML = '';
                        });
                        results.appendChild(btn);
                    });
                })
                .catch(() => { results.innerHTML = ''; });
        }, 300);
    });
})();
</script>
@endpush
