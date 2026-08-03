@extends('layouts.volunteer')

@section('title', 'ملفّ قضيّة تحكيم')

@section('content')
    <x-page-header
        :title="'قضيّة #'.$selected->id"
        subtitle="ملفّ الحالة كاملًا — والقرار نهائيّ لا يُعاد."
        :breadcrumbs="[
            ['label' => 'التحكيمات', 'url' => route('volunteer.arbitrations')],
            ['label' => 'قضيّة #'.$selected->id],
        ]" />

    @include('volunteer.escalations.arbitrations.partials.file', ['file' => $file])
@endsection
