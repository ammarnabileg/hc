@php
    /** تابات إدارة التدريب — رقائق أفقيّة متمرّرة على الموبايل (2.15-ج) */
    $navTabs = [
        ['key' => 'paths', 'label' => 'المسارات', 'url' => route('admin.paths.index')],
        ['key' => 'courses', 'label' => 'التدريبات', 'url' => route('admin.courses.index')],
        ['key' => 'media', 'label' => 'مكتبة الوسائط', 'url' => route('admin.media.index')],
    ];
@endphp

<x-tabs :tabs="$navTabs" :current="$current ?? 'courses'" />
