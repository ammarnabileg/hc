{{--
    صفحة 405 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13).

    ⛔ ما قبلها: طلب GET لرابطٍ لا يقبل إلّا PUT/DELETE (مثل admin/courses/{course})
    كان يعرض صفحة مصحّح Laravel الخام في التطوير وصفحةً بيضاء في الإنتاج،
    لأنّ Laravel لا يشحن قالبًا لـ405 فيسقط إلى عارض الاستثناءات.
--}}
@extends('errors.minimal', ['icon' => 'blocked'])

@section('title', setting('errors.405.title', 'الرابط مش بيتفتح كده'))
@section('code', '405')
@section('message', setting('errors.405.message', 'الرابط ده مش بيتفتح بالطريقة دي. ارجع للصفحة اللي جيت منها وكمّل من هناك.'))
@section('action_label', setting('errors.405.action', 'ارجع للرئيسيّة'))
@section('action_url'){{ Route::has('dashboard') && auth()->check() ? route('dashboard') : url('/') }}@endsection
