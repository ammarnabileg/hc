@php
    /** تابات إدارة التطوّع — رقائق أفقيّة متمرّرة على الموبايل (2.15-ج) */
    $items = [
        ['key' => 'index', 'label' => setting('admin.volunteer.partials.tabs.allwha', 'اللوحة'), 'route' => 'admin.volunteer.index', 'can' => 'volunteer_central_settings.view'],
        ['key' => 'org', 'label' => setting('admin.volunteer.partials.tabs.alhykl_walsaa', 'الهيكل والسعة'), 'route' => 'admin.volunteer.org', 'can' => 'org_chart.view'],
        ['key' => 'rep', 'label' => setting('admin.volunteer.partials.tabs.dbt_rep', 'ضبط Rep'), 'route' => 'admin.volunteer.rep', 'can' => 'rep_transactions.view'],
        // الغيابات والتفويض المؤقّت (23-6) — استعراضٌ وإنهاءٌ مبكّر، والإضافة في «الأعضاء والبوزشنز»
        ['key' => 'delegations', 'label' => setting('admin.volunteer.partials.tabs.alghyabat_waltfwyd', 'الغيابات والتفويض'), 'route' => 'admin.volunteer.delegations', 'can' => 'delegations.list'],
        ['key' => 'offboarding', 'label' => setting('admin.volunteer.partials.tabs.alawfbwrdnj', 'الأوفبوردنج'), 'route' => 'admin.volunteer.offboarding', 'can' => 'offboarding.view'],
        ['key' => 'reentries', 'label' => setting('admin.volunteer.partials.tabs.alaaydwn', 'العائدون'), 'route' => 'admin.volunteer.reentries', 'can' => 'offboarding.view'],
        ['key' => 'certificates', 'label' => setting('admin.volunteer.partials.tabs.alshhadat', 'الشهادات'), 'route' => 'admin.volunteer.certificates', 'can' => 'volunteer_certificates.view'],
        ['key' => 'analytics', 'label' => setting('admin.volunteer.partials.tabs.althlylat', 'التحليلات'), 'route' => 'admin.volunteer.analytics', 'can' => 'reports_volunteer.view'],
    ];
@endphp

<div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
    <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar">
        @foreach ($items as $item)
            {{-- بلا صلاحيّة = مخفيّ فعلًا لا معطَّل (2.15-أ-7) --}}
            @can($item['can'])
                <a href="{{ route($item['route']) }}"
                   class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ ($current ?? '') === $item['key']
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $item['label'] }}</a>
            @endcan
        @endforeach
    </div>
</div>
