@extends('layouts.app')
@section('title', $title ?? (string) setting('ux.placeholder.section_1', 'قيد الإنشاء'))

@section('content')
    <x-page-header :title="$title ?? (string) setting('ux.placeholder.title_1', 'قيد الإنشاء')" subtitle="{{ setting('ux.placeholder.subtitle_1', 'الصفحة دي بتتبني دلوقتي.') }}" />
    <x-empty message="{{ setting('ux.placeholder.message_1', 'لسّه بنجهّز الصفحة دي.') }}" />
@endsection
