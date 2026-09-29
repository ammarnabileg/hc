{{-- صفحة 429 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'hourglass'])

@section('title', setting('errors.429.title', 'طلبات كتير'))
@section('code', '429')
@section('message', setting('errors.429.message', 'طلبات كتير في وقت قصير. استنّى لحظة وجرّب تاني.'))
@section('action_label', setting('errors.429.action', 'ارجع للرئيسيّة'))
@section('action_url'){{ Route::has('dashboard') && auth()->check() ? route('dashboard') : url('/') }}@endsection
