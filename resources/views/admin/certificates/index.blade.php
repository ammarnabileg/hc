@extends('layouts.app')

@section('title', 'إدارة الشهادات')

@section('content')
    {{-- إدارة الشهادات (12.5): نعرّف ⟵ نُعِدّ ⟵ نُصدِر ⟵ نتابع --}}
    <x-page-header
        title="إدارة الشهادات"
        subtitle="عرّف جهات الاعتماد، جهّز الأنواع وقوالبها، أصدِر، وتابع السجلّ."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.certificates.index')], ['label' => 'الشهادات']]" />

    <x-tabs :tabs="$tabs" :current="$tab" />

    {{-- تحميل كسول: لا يُبنى إلّا التاب المفتوح (2.15-د) --}}
    @includeWhen($tab === 'accreditations', 'admin.certificates.partials.accreditations')
    @includeWhen($tab === 'types', 'admin.certificates.partials.types')
    @includeWhen($tab === 'issue', 'admin.certificates.partials.issue')
    @includeWhen($tab === 'ledger', 'admin.certificates.partials.ledger')

    @include('admin.courses.partials.toast')
@endsection
