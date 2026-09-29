{{-- صفحة 503 بنظام التصميم — تمتدّ من القالب المشترك، ونصوصها إعدادات (2.13) --}}
@extends('errors.minimal', ['icon' => 'refresh'])

@section('title', setting('errors.503.title', 'صيانة قصيرة'))
@section('code', '503')
@section('message', setting('errors.503.message', 'المنصّة في صيانة قصيرة. جرّب تاني بعد دقايق.'))
@section('action_label', setting('errors.503.action', 'جرّب تاني'))
@section('action_url'){{ url()->current() }}@endsection
