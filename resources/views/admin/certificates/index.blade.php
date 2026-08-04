@extends('layouts.admin')

@section('title', setting('admin.certificates.index.idara_alshhadat', 'إدارة الشهادات'))

@section('content')
    {{-- إدارة الشهادات (12.5): نعرّف ⟵ نُعِدّ ⟵ نُصدِر ⟵ نتابع --}}
    <x-page-header
        :title="setting('admin.certificates.index.idara_alshhadat', 'إدارة الشهادات')"
        :subtitle="setting('admin.certificates.index.arf_jhat_alaatmad_jhz_alanwaa_wqwalbha_asdr', 'عرّف جهات الاعتماد، جهّز الأنواع وقوالبها، أصدِر، وتابع السجلّ.')"
        :breadcrumbs="[['label' => setting('admin.certificates.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.certificates.index')], ['label' => setting('admin.certificates.index.alshhadat', 'الشهادات')]]" />

    <x-tabs :tabs="$tabs" :current="$tab" />

    {{-- تحميل كسول: لا يُبنى إلّا التاب المفتوح (2.15-د) --}}
    @includeWhen($tab === 'accreditations', 'admin.certificates.partials.accreditations')
    @includeWhen($tab === 'types', 'admin.certificates.partials.types')
    @includeWhen($tab === 'issue', 'admin.certificates.partials.issue')
    @includeWhen($tab === 'ledger', 'admin.certificates.partials.ledger')
    {{-- صفحة التحقّق: جدول البلاغات ومراجعتها (24.1) --}}
    @includeWhen($tab === 'verification', 'admin.certificates.partials.reports')

    @include('admin.courses.partials.toast')
@endsection
