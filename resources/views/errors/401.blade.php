{{-- صفحة 401 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'lock'])

@section('title', setting('errors.401.title', 'سجّل دخولك الأوّل'))
@section('code', '401')
@section('message', setting('errors.401.message', 'الصفحة دي للمسجّلين. سجّل دخولك وهترجع لها على طول.'))
@section('action_label', setting('errors.401.action', 'تسجيل الدخول'))
@section('action_url'){{ Route::has('login') ? route('login') : url('/') }}@endsection
