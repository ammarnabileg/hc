{{-- صفحة 404 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'search'])

@section('title', setting('errors.404.title', 'الصفحة مش موجودة'))
@section('code', '404')
@section('message', setting('errors.404.message', 'الرابط ده مش موصّل لحاجة. يمكن اتغيّر أو اتكتب غلط.'))
@section('action_label', setting('errors.404.action', 'ارجع للرئيسيّة'))
@section('action_url'){{ Route::has('dashboard') && auth()->check() ? route('dashboard') : url('/') }}@endsection
