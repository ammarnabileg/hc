@extends('layouts.volunteer')

@section('title', setting('volunteer.escalations_arbitrations_show.title', 'ملفّ قضيّة تحكيم'))

@section('content')
    <x-page-header
        :title="setting('volunteer.escalations_arbitrations_show.tooltip', 'قضيّة #').$selected->id"
        :subtitle="setting('volunteer.escalations_arbitrations_show.subtitle', 'ملفّ الحالة كاملًا — والقرار نهائيّ لا يُعاد.')"
        :breadcrumbs="[
            ['label' => setting('volunteer.escalations_arbitrations_show.label', 'التحكيمات'), 'url' => route('volunteer.arbitrations')],
            ['label' => setting('volunteer.escalations_arbitrations_show.tooltip', 'قضيّة #').$selected->id],
        ]" />

    @include('volunteer.escalations.arbitrations.partials.file', ['file' => $file])
@endsection
