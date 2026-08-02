@php
    /** تابات إدارة التطوّع — رقائق أفقيّة متمرّرة على الموبايل (2.15-ج) */
    $items = [
        ['key' => 'index', 'label' => 'اللوحة', 'route' => 'admin.volunteer.index', 'can' => 'volunteer_central_settings.view'],
        ['key' => 'org', 'label' => 'الهيكل والسعة', 'route' => 'admin.volunteer.org', 'can' => 'org_chart.view'],
        ['key' => 'rep', 'label' => 'ضبط Rep', 'route' => 'admin.volunteer.rep', 'can' => 'rep_transactions.view'],
        ['key' => 'offboarding', 'label' => 'الأوفبوردنج', 'route' => 'admin.volunteer.offboarding', 'can' => 'offboarding.view'],
        ['key' => 'reentries', 'label' => 'العائدون', 'route' => 'admin.volunteer.reentries', 'can' => 'offboarding.view'],
        ['key' => 'certificates', 'label' => 'الشهادات', 'route' => 'admin.volunteer.certificates', 'can' => 'volunteer_certificates.view'],
        ['key' => 'analytics', 'label' => 'التحليلات', 'route' => 'admin.volunteer.analytics', 'can' => 'reports_volunteer.view'],
    ];
@endphp

<div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
    <div class="flex gap-2 overflow-x-auto no-scrollbar">
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
