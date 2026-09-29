{{-- صفحة 419 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'clock'])

@section('title', setting('errors.419.title', 'الجلسة انتهت'))
@section('code', '419')
@section('message', setting('errors.419.message', 'الصفحة قعدت مفتوحة كتير والجلسة انتهت. افتحها تاني وكمّل.'))
@section('action_label', setting('errors.419.action', 'افتح الصفحة تاني'))
@section('action_url'){{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}@endsection
