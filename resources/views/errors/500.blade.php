{{-- صفحة 500 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'warning'])

@section('title', setting('errors.500.title', 'حصل خطأ'))
@section('code', '500')
@section('message', setting('errors.500.message', 'حصل خطأ عندنا مش عندك. اتسجّل وهنراجعه، جرّب تاني بعد شويّة.'))
@section('action_label', setting('errors.500.action', 'ارجع للرئيسيّة'))
@section('action_url'){{ Route::has('dashboard') && auth()->check() ? route('dashboard') : url('/') }}@endsection
