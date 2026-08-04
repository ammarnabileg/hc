@extends('layouts.volunteer')

@section('title', setting('volunteer.org_department.title', 'الأعضاء والبوزشنز'))

@php
    /**
     * الأعضاء والبوزشنز (24.4-7 · 13.4-م).
     * سؤال واحد للشاشة: «مين معايا في القسم وبيعمل إيه؟».
     * والقسم يُعرَض **كاملًا حتى لو كنتُ في فرعيّ**.
     */
    $statuses = [
        'active' => setting('volunteer.org_department.active', 'نشط'),
        'absent' => setting('volunteer.org_department.absent', 'غائب'),
        'acting' => setting('volunteer.org_department.acting', 'قائم بأعمال'),
        'suspended' => setting('volunteer.org_department.suspended', 'معلَّق'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.org_department.title', 'الأعضاء والبوزشنز')"
        :subtitle="$root ? $root->name_ar.setting('volunteer.org_department.subtitle', ' — القسم كامل بكلّ فرعيّاته') : null"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.org_department.label', 'قسمي')], ['label' => setting('volunteer.org_department.title', 'الأعضاء والبوزشنز')]]">
        <x-slot:action>
            @include('volunteer.org.partials.entity-switcher', ['action' => route('volunteer.department')])
        </x-slot:action>
    </x-page-header>

    @if (! $root)
        <x-empty :message="setting('volunteer.org_department.empty', 'لسّه مش مُسكَّن في كيان — أوّل خطوة مستنّياك')" :action="setting('volunteer.org_department.action', 'الرجوع للرئيسيّة')" :href="route('dashboard')" />
    @else
        {{-- سطر «أخوكم» الشرفيّ — خارج العدّاد وخارج الفلاتر (13.4-ص-ب) --}}
        @include('volunteer.org.partials.honorary-line', ['honorary' => $honorary])

        {{-- ثلاثة عدّادات فقط (2.15-أ-3) --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
            <x-kpi :label="setting('volunteer.org_department.label_2', 'الأعضاء')" :value="$counters['members']" icon="people" />
            <x-kpi :label="setting('volunteer.org_department.label_3', 'الفرعيّات')" :value="$counters['sub_entities']" icon="entity" />
            <x-kpi :label="setting('volunteer.org_department.label_4', 'الشواغر')" :value="$counters['vacancies']" icon="placement" />
        </div>

        <x-filters :action="route('volunteer.department')">
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.org_department.field', 'الفرعيّ') }}</span>
                <select name="sub" onchange="this.form.submit()"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                    @foreach ($subEntities as $sub)
                        <option value="{{ $sub->id }}" @selected((int) ($filters['entity'] ?? 0) === $sub->id)>{{ $sub->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.position', 'البوزشن') }}</span>
                <select name="position" onchange="this.form.submit()"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->key }}" @selected(($filters['position'] ?? null) === $position->key)>{{ $position->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.status', 'الحالة') }}</span>
                <select name="status" onchange="this.form.submit()"
                        class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm flex-1 min-w-40">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ setting('volunteer.org_department.placeholder', 'بالاسم أو الكود…') }}"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>

            <x-slot:advanced>
                <div class="flex items-center gap-2 text-sm">
                    <span style="color: var(--text-muted)">{{ setting('volunteer.org_department.field_2', 'العرض') }}</span>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'cards']) }}"
                       class="rounded-full px-3 py-1 text-xs"
                       style="{{ $view === 'cards' ? 'background: var(--color-brand-500); color:#04201c' : 'background: var(--surface-sunken)' }}">{{ setting('volunteer.org_department.link', 'كروت') }}</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'table']) }}"
                       class="rounded-full px-3 py-1 text-xs"
                       style="{{ $view === 'table' ? 'background: var(--color-brand-500); color:#04201c' : 'background: var(--surface-sunken)' }}">{{ setting('volunteer.org_department.link_2', 'جدول') }}</a>
                </div>
            </x-slot:advanced>
        </x-filters>

        @if ($cards->isEmpty())
            <x-empty :message="setting('volunteer.org_department.empty_2', 'مفيش أعضاء مطابقين للفلتر')" :action="setting('volunteer.org_department.action_2', 'امسح الفلاتر')" :href="route('volunteer.department')" />
        @elseif ($view === 'table')
            {{-- على الموبايل: كروت رأسيّة لا تمرير أفقيّ (2.15-ج) --}}
            <div class="hidden md:block card min-w-0 overflow-x-auto">
                <table class="w-full text-sm"
                   {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                   @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                    <thead>
                        <tr style="color: var(--text-muted)">
                            <th class="text-start p-3">{{ setting('volunteer.org_department.col', 'العضو') }}</th>
                            <th class="text-start p-3">{{ setting('volunteer.common.position', 'البوزشن') }}</th>
                            <th class="text-start p-3">{{ setting('volunteer.org_department.field', 'الفرعيّ') }}</th>
                            <th class="text-start p-3">{{ setting('volunteer.org_department.col_2', 'الأبلاين') }}</th>
                            <th class="text-start p-3">Rep</th>
                            <th class="text-start p-3">{{ setting('volunteer.common.status', 'الحالة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cards as $card)
                            <tr class="cursor-pointer motion-standard hover:opacity-90"
                                style="border-top: 1px solid var(--border)"
                                data-member="{{ route('volunteer.department.member', $card['id']) }}"
                                data-member-name="{{ $card['name'] }}">
                                <td class="p-3 font-semibold">
                                    {{ $card['short_name'] }}
                                    @if ($card['is_club'])
                                        <span style="color: var(--color-state-honor)">★</span>
                                    @endif
                                </td>
                                <td class="p-3">{{ $card['position'] }}</td>
                                <td class="p-3">{{ $card['entity'] }}</td>
                                <td class="p-3">{{ $card['upline'] ?? '—' }}</td>
                                <td class="p-3"><x-state-badge :state="$card['rep_state']" :label="$card['rep_label']" /></td>
                                <td class="p-3">{{ $statuses[$card['status']] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="md:hidden grid gap-3">
                @foreach ($cards as $card)
                    @include('volunteer.org.partials.member-card', ['card' => $card])
                @endforeach
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    @include('volunteer.org.partials.member-card', ['card' => $card])
                @endforeach
            </div>
        @endif

        {{--
            ⭐ وضع «غائب» والتفويض المؤقّت (23-6): يضيفه المشرف/الدايركتور لا الشخص
            نفسه — ولذلك يُخفى تمامًا عمّن لا يملكه (2.15-أ-7). وبلا هذا الوضع يقع
            نزيف خصومات تباطؤ على غائب معذور.
        --}}
        @can('delegations.create')
            <details class="card p-4 mt-5">
                <summary class="cursor-pointer text-sm font-semibold">{{ setting('volunteer.org_department.summary', 'تسجيل غياب وتفويض بديل') }}</summary>

                <form method="post" action="{{ url('/volunteer/department/member') }}"
                      data-absence-form data-absence-base="{{ url('/volunteer/department/member') }}"
                      class="grid gap-3 sm:grid-cols-2 mt-3">
                    @csrf

                    <label class="text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.org_department.field_3', 'العضو الغائب') }}</span>
                        <select name="membership" required data-absence-member
                                class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('volunteer.org_department.option', 'اختار…') }}</option>
                            @foreach ($cards as $card)
                                <option value="{{ $card['id'] }}">{{ $card['name'] }} — {{ $card['position'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-sm">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.org_department.field_4', 'البديل المفوَّض (فاضي = أبلاينه المباشر)') }}</span>
                        <select name="delegate_membership_id"
                                class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('volunteer.org_department.option_2', 'الأبلاين المباشر') }}</option>
                            @foreach ($cards as $card)
                                <option value="{{ $card['id'] }}">{{ $card['name'] }} — {{ $card['position'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-form.input name="from_date" :label="setting('volunteer.common.from', 'من')" type="date" required />
                    <x-form.input name="to_date" :label="setting('volunteer.common.to', 'إلى')" type="date" required />

                    <label class="text-sm sm:col-span-2">
                        <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.org_department.field_5', 'السبب (اختياريّ)') }}</span>
                        <input type="text" name="reason" class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <div class="sm:col-span-2">
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.org_department.action_3', 'تسجيل الغياب') }}</button>
                        <span class="block text-xs mt-2" style="color: var(--text-muted)">
                            {{ setting('volunteer.org_department.field_6', 'طول الغياب: القرارات تروح للبديل · مفيش أثر تباطؤ على الغائب · مفيش إسناد جديد له · وساعات مهامّه واقفة.') }}
                        </span>
                    </div>
                </form>
            </details>
        @endcan

        {{-- بوب-أب ملفّ عضو مختصر: رأس ثابت وجسم متمرّر (2.10.1-17) --}}
        <x-modal id="member-modal" :title="setting('volunteer.org_department.tooltip', 'ملفّ العضو')">
            <div data-member-body class="text-sm">
                <p style="color: var(--text-muted)">{{ setting('volunteer.common.loading', 'جارٍ التحميل…') }}</p>
            </div>
        </x-modal>
    @endif
@endsection

@push('scripts')
@php
    /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
    $jsText = [
        'no_phone' => (string) setting('volunteer.org_department.js_no_phone', 'ما سجّلش رقم تواصل لسّه.'),
        'whatsapp' => (string) setting('volunteer.org_department.js_whatsapp', 'واتساب'),
        'request_phone' => (string) setting('volunteer.org_department.js_request_phone', 'اطلب إظهار الرقم'),
        'service_duration' => (string) setting('volunteer.org_department.js_service_duration', 'مدّة الخدمة'),
        'certificates' => (string) setting('volunteer.org_department.js_certificates', 'الشهادات'),
        'open_profile' => (string) setting('volunteer.org_department.js_open_profile', 'فتح البروفايل'),
        'kudos' => (string) setting('volunteer.org_department.js_kudos', 'شكر (Kudos)'),
        'loading' => (string) setting('volunteer.org_department.js_loading', 'جارٍ التحميل…'),
        'load_failed' => (string) setting('volunteer.org_department.js_load_failed', 'تعذّر تحميل الملفّ — جرّب تاني.'),
    ];
@endphp

<script>
const T = @json($jsText);
/* فورم الغياب: المسار يحمل عضويّة الغائب، فنبنيه من الاختيار قبل الإرسال */
(() => {
    const form = document.querySelector('[data-absence-form]');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        const membership = form.querySelector('[data-absence-member]')?.value;
        if (!membership) { e.preventDefault(); return; }
        form.action = `${form.dataset.absenceBase}/${membership}/absence`;
    });
})();

/* بوب-أب ملفّ العضو — بلا مكتبات، وردّ فوريّ لكلّ فعل (2.17-ب) */
(() => {
    const modal = document.getElementById('member-modal');
    if (!modal) return;
    const body = modal.querySelector('[data-member-body]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const consentBase = @json(url('/volunteer/department/member'));

    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };

    const badge = (state, label) => {
        const colors = { ok: '●', warn: '▲', danger: '◉', honor: '★', idle: '○' };
        return `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
            style="background: color-mix(in srgb, var(--color-state-${state}) 15%, transparent);
                   color: var(--color-state-${state})">${colors[state] ?? '○'} ${label}</span>`;
    };

    const render = (d) => {
        const c = d.contact;
        // ⭐ الحقل المقفول لا يُعرَض فراغًا — يظهر زرّ الإجراء بدلًا منه (13.4-م-2)
        let contactBlock;
        if (!c.has_phone) {
            contactBlock = `<p style="color: var(--text-muted)">${T.no_phone}</p>`;
        } else if (c.visible) {
            contactBlock = `<div class="flex items-center gap-2 flex-wrap">
                <span dir="ltr" class="font-semibold">${c.display}</span>
                <a href="${c.whatsapp}" target="_blank" rel="noopener"
                   class="btn rounded-xl px-3 py-2 text-xs font-semibold"
                   style="background: var(--color-brand-500); color:#04201c">${T.whatsapp}</a>
            </div>`;
        } else {
            contactBlock = `<div class="flex items-center gap-2 flex-wrap">
                <span dir="ltr" style="color: var(--text-muted)">${c.display}</span>
                <form method="post" action="${consentBase}/${d.membership_id}/consent">
                    <input type="hidden" name="_token" value="${csrf}">
                    <button type="submit" class="btn rounded-xl px-3 py-2 text-xs font-semibold"
                            style="background: var(--surface-sunken); color: var(--text)">${T.request_phone}</button>
                </form>
            </div>`;
        }

        body.innerHTML = `
            <div class="flex items-center gap-3 mb-3">
                <div class="min-w-0">
                    <div class="font-bold flex items-center gap-2 flex-wrap">
                        ${d.name} ${badge(d.rep_state, d.rep_label)}
                        ${d.is_club ? '<span style="color: var(--color-state-honor)">★</span>' : ''}
                    </div>
                    <div class="text-xs" style="color: var(--text-muted)">#${d.code} · ${d.position} · ${d.entity}</div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">${T.service_duration}</div><div class="font-bold text-sm mt-1">${d.service_duration}</div></div>
                <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">Kudos</div><div class="font-bold text-sm mt-1">${d.kudos}</div></div>
                <div class="card p-2 text-center"><div class="text-xs" style="color: var(--text-muted)">${T.certificates}</div><div class="font-bold text-sm mt-1">${d.certificates}</div></div>
            </div>
            <div class="mb-4">${contactBlock}</div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="${d.profile_url}" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color:#04201c">${T.open_profile}</a>
                <a href="${d.profile_url}#kudos" class="btn rounded-xl px-4 py-2 text-sm"
                   style="background: var(--surface-sunken); color: var(--text)">${T.kudos}</a>
            </div>`;
    };

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-member]');
        if (!trigger) return;
        open();
        body.innerHTML = '<p style="color: var(--text-muted)">' + T.loading + '</p>';
        fetch(trigger.dataset.member, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then(render)
            .catch(() => {
                body.innerHTML = '<p>' + T.load_failed + '</p>';
            });
    });
})();
</script>
@endpush
