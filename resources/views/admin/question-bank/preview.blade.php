@extends('layouts.admin')

@section('title', 'معاينة الامتحان النهائيّ')

@php
    /**
     * معاينة الامتحان النهائيّ كما سيُبنى من الأسئلة العامّة النشطة (24.1-3).
     * الغرض: يشوف الأدمن ما سيراه الطالب **قبل** النشر لا بعده (2.13-د).
     */
@endphp

@section('content')
    <x-page-header title="معاينة الامتحان النهائيّ"
                   subtitle="ده الامتحان كما هيتبنى دلوقتي من الأسئلة العامّة النشطة."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'بنك الأسئلة', 'url' => route('admin.question-bank.index')],
                       ['label' => 'معاينة الامتحان'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.question-bank.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">رجوع للبنك</a>
        </x-slot:action>
    </x-page-header>

    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-state-badge :state="$questions->count() >= $cap ? 'ok' : 'warn'"
                           :label="$questions->count().' سؤال من سقف '.$cap" />
            <span style="color: var(--text-muted)">الترتيب العشوائيّ والتصحيح على الخادم بيتظبطوا من إعدادات البنك.</span>
        </div>
    </div>

    @if ($questions->isEmpty())
        <x-empty message="مافيش أسئلة عامّة نشطة — الامتحان النهائيّ مش هيتبنى دلوقتي."
                 action="روح للبنك" :href="route('admin.question-bank.index')" />
    @else
        <ol class="space-y-3">
            @foreach ($questions as $index => $question)
                <li class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold">{{ $index + 1 }}. {{ $question->prompt }}</p>
                        <span class="text-xs shrink-0" style="color: var(--text-muted)">
                            {{ $types[$question->type] ?? $question->type }}
                        </span>
                    </div>

                    @if (is_array($question->options) && $question->options !== [])
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($question->options as $option)
                                <li class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">{{ $option }}</li>
                            @endforeach
                        </ul>
                    @elseif ($question->placeholder)
                        <p class="mt-3 rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $question->placeholder }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
@endsection
