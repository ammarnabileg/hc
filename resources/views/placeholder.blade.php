@extends('layouts.app')
@section('title', $title ?? 'قيد الإنشاء')

@section('content')
    <x-page-header :title="$title ?? 'قيد الإنشاء'" subtitle="الصفحة دي بتتبني دلوقتي." />
    <x-empty message="لسّه بنجهّز الصفحة دي." />
@endsection
