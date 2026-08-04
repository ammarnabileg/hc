@php
    /** تابات إدارة التدريب — رقائق أفقيّة متمرّرة على الموبايل (2.15-ج) */
    $navTabs = [
        ['key' => 'paths', 'label' => setting('admin.courses.partials.nav.almsarat', 'المسارات'), 'url' => route('admin.paths.index')],
        ['key' => 'courses', 'label' => setting('admin.courses.partials.nav.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
        ['key' => 'media', 'label' => setting('admin.courses.partials.nav.mktba_alwsayt', 'مكتبة الوسائط'), 'url' => route('admin.media.index')],
    ];
@endphp

<x-tabs :tabs="$navTabs" :current="$current ?? 'courses'" />
