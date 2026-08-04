@extends('layouts.volunteer')

@section('title', 'Kudos')

@php
    /**
     * Kudos (13.4-ي · 24.4-11).
     * **نصّ السبب بارز** — القصّة أهمّ من العدّاد. والحدود تُشرَح لحظة بلوغها فقط (2.15-د).
     */
    $limitsLine = $dailyLimit.setting('volunteer.people_kudos.text', '/يوم · ').$weeklyLimit.setting('volunteer.people_kudos.text_2', ' أفراد/أسبوع');
@endphp

@section('content')
    <x-page-header
        title="Kudos"
        :subtitle="setting('volunteer.people_kudos.subtitle', 'اليوم ').$sentToday.'/'.$dailyLimit.setting('volunteer.people_kudos.subtitle_2', ' · الأسبوع ').$peopleThisWeek.'/'.$weeklyLimit.setting('volunteer.people_kudos.subtitle_3', ' أفراد')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.people_kudos.label', 'التقدير')], ['label' => 'Kudos']]">
        <x-slot:action>
            @if ($canSend)
                <button type="button" data-modal-open="kudos-modal"
                        class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_kudos.action', 'شكر زميل') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.people_kudos.label_2', 'إجمالي Kudos وصلني')" :value="$totalReceived" icon="contribution" />
        <x-kpi :label="setting('volunteer.people_kudos.label_3', 'بعتّه اليوم')" :value="$sentToday" icon="envelope" :hint="$limitsLine" />
        <x-kpi :label="setting('volunteer.people_kudos.label_4', 'أفراد شكرتهم الأسبوع ده')" :value="$peopleThisWeek" icon="people" :hint="$limitsLine" />
    </div>

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'received', 'label' => setting('volunteer.people_kudos.label_5', 'وصلني'), 'url' => route('volunteer.kudos', ['tab' => 'received'])],
        ['key' => 'sent', 'label' => setting('volunteer.people_kudos.label_6', 'أرسلته'), 'url' => route('volunteer.kudos', ['tab' => 'sent'])],
        ['key' => 'wall', 'label' => setting('volunteer.people_kudos.label_7', 'حائط الشكر'), 'url' => route('volunteer.kudos.wall')],
    ]" />

    <x-filters :action="route('volunteer.kudos')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.period', 'الفترة') }}</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ([30 => setting('volunteer.people_kudos.foreach', 'آخر 30 يومًا'), 90 => setting('volunteer.people_kudos.foreach_2', 'آخر 90 يومًا'), 0 => setting('volunteer.people_kudos.foreach_3', 'من البداية')] as $days => $label)
                    <option value="{{ $days }}" @selected((int) $filters['days'] === $days)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_kudos.field', 'بحث في السبب') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.people_kudos.placeholder', 'كلمة من نصّ الشكر…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty :message="setting('volunteer.people_kudos.empty', 'مفيش شكرات بعد — ابدأ أنت')" />
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
        <x-modal id="kudos-modal" :title="setting('volunteer.people_kudos.action', 'شكر زميل')">
            <form method="post" action="{{ route('volunteer.kudos.store') }}" class="space-y-3">
                @csrf

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_kudos.field_2', 'ابحث بالاسم أو الكود') }}</span>
                    <input type="search" data-kudos-search placeholder="{{ setting('volunteer.people_kudos.placeholder_2', 'اكتب حرفين على الأقلّ…') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                </label>

                <div data-kudos-results class="space-y-1"></div>
                <input type="hidden" name="receiver_id" data-kudos-receiver required>
                <p class="text-sm" data-kudos-chosen style="color: var(--color-state-ok)"></p>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_kudos.field_3', 'سبب الشكر (إلزاميّ)') }}</span>
                    <textarea name="reason" rows="4" required minlength="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                              placeholder="{{ setting('kudos.reason.placeholder', 'احكِ الموقف نفسه — الحكاية هي اللي بتفضل.') }}">{{ old('reason') }}</textarea>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('volunteer.people_kudos.field_4', 'الحدّ:') }} {{ $limitsLine }} — {{ setting('volunteer.people_kudos.field_5', 'وأشخاص مختلفون فقط.') }}
                </p>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_kudos.action_2', 'ابعت الشكر') }}</button>
            </form>
        </x-modal>
    @endif
@endsection

@section('mobile_action')
    @if ($canSend)
        <button type="button" data-modal-open="kudos-modal"
                class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_kudos.action', 'شكر زميل') }}</button>
    @endif
@endsection

@php
    /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
    $jsText = [
        'chosen' => (string) setting('volunteer.people_kudos.js_chosen', 'اخترت:'),
    ];
@endphp

@push('scripts')
<script>
const T = @json($jsText);
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
                            chosen.textContent = `${T.chosen} ${person.name} ✓`;
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
