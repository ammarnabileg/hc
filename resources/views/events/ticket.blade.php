@extends('layouts.app')
@section('title', 'تذكرتي — '.$event->title_ar)
@section('og_image', route('events.og', $event->slug))
@section('noindex', true)

@section('content')
    @php
        $shareText = trim((string) setting('events.ticket.share_text', 'هحضر الفعاليّة دي — تعالى معايا:')).' '.route('events.show', $event->slug);
    @endphp

    <x-page-header title="تذكرتي"
                   subtitle="تذكرة حضورك جاهزة للنشر — والكود ده هو إثبات دخولك."
                   :breadcrumbs="[
                       ['label' => 'الفعاليّات', 'url' => route('events.index')],
                       ['label' => $event->title_ar, 'url' => route('events.show', $event->slug)],
                       ['label' => 'تذكرتي'],
                   ]" />

    <div class="max-w-xl mx-auto space-y-4">
        @include('events.components.ticket-card', ['registration' => $registration, 'event' => $event, 'presenter' => $presenter])

        <div class="card p-4 flex flex-wrap items-center justify-center gap-2">
            @include('events.components.copy', ['text' => $shareText, 'label' => 'نسخ نصّ المشاركة', 'tone' => 'ghost'])

            <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
               class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @include('events.components.icon', ['name' => 'share']) واتساب
            </a>

            <a href="{{ route('events.og', $event->slug) }}" target="_blank" rel="noopener"
               class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                بطاقة الفعاليّة (صورة)
            </a>
        </div>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ route('events.show', $event->slug) }}"
       class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">صفحة الفعاليّة</a>
@endsection
