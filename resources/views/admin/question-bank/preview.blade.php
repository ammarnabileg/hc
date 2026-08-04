@extends('layouts.admin')

@section('title', setting('admin.question_bank.preview.maayna_alamthan_alnhayy', 'معاينة الامتحان النهائيّ'))

@php
    /**
     * معاينة الامتحان النهائيّ كما سيُبنى من الأسئلة العامّة النشطة (24.1-3).
     * الغرض: يشوف الأدمن ما سيراه الطالب **قبل** النشر لا بعده (2.13-د).
     */
@endphp

@section('content')
    <x-page-header :title="setting('admin.question_bank.preview.maayna_alamthan_alnhayy', 'معاينة الامتحان النهائيّ')"
                   :subtitle="setting('admin.question_bank.preview.dh_alamthan_kma_hytbna_dlwqty_mn_alasyla', 'ده الامتحان كما هيتبنى دلوقتي من الأسئلة العامّة النشطة.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.question_bank.preview.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.question_bank.preview.bnk_alasyla', 'بنك الأسئلة'), 'url' => route('admin.question-bank.index')],
                       ['label' => setting('admin.question_bank.preview.maayna_alamthan', 'معاينة الامتحان')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.question-bank.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.question_bank.preview.rjwa_llbnk', 'رجوع للبنك') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-state-badge :state="$questions->count() >= $cap ? 'ok' : 'warn'"
                           :label="$questions->count().setting('admin.question_bank.preview.swal_mn_sqf', ' سؤال من سقف ').$cap" />
            <span style="color: var(--text-muted)">{{ setting('admin.question_bank.preview.altrtyb_alashwayy_waltshyh_ala_alkhadm', 'الترتيب العشوائيّ والتصحيح على الخادم بيتظبطوا من إعدادات البنك.') }}</span>
        </div>
    </div>

    @if ($questions->isEmpty())
        <x-empty :message="setting('admin.question_bank.preview.mafysh_asyla_aama_nshta_alamthan_alnhayy_msh', 'مافيش أسئلة عامّة نشطة — الامتحان النهائيّ مش هيتبنى دلوقتي.')"
                 :action="setting('admin.question_bank.preview.rwh_llbnk', 'روح للبنك')" :href="route('admin.question-bank.index')" />
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
