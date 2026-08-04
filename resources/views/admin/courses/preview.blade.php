@extends('layouts.admin')

@section('title', setting('admin.courses.preview.maayna_ktalb_2', 'معاينة كطالب — ').$course->name_ar)

@section('content')
    {{-- معاينة كطالب قبل النشر (12.4-هـ) --}}
    <x-page-header
        :title="setting('admin.courses.preview.maayna_ktalb', 'معاينة كطالب: ').$course->name_ar"
        :subtitle="setting('admin.courses.preview.dh_ally_almtdrb_hyshwfh_bla_azrar_idara', 'ده اللي المتدرّب هيشوفه — بلا أزرار إدارة.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.preview.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => setting('admin.courses.preview.maayna', 'معاينة')],
        ]" />

    <div class="card p-4 mb-4">
        <h2 class="font-bold text-lg">{{ $course->name_ar }}</h2>
        @if ($course->description_ar)
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $course->description_ar }}</p>
        @endif
        <p class="text-sm mt-2">{{ $course->is_free ? setting('admin.courses.preview.mjany', 'مجّانيّ') : (int) $course->price_coins.setting('admin.courses.preview.kwynz', ' كوينز') }}</p>
    </div>

    @forelse ($sections as $section)
        <div class="card p-4 mb-3">
            <h3 class="font-semibold">{{ $section->title_ar }}</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($section->lessons as $lesson)
                    <li class="flex items-center gap-2">
                        <span aria-hidden="true"><x-icon :name="$lesson->type === 'video' ? 'video' : 'document'" size="16" /></span>
                        <span class="flex-1">{{ $lesson->title_ar }}</span>
                        @if ($lesson->is_free_preview)
                            <x-state-badge state="ok" :label="setting('admin.courses.preview.maayna_mjanya', 'معاينة مجّانيّة')" />
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <x-empty :message="setting('admin.courses.preview.almhtwa_lsh_fady_abn_awl_sykshn', 'المحتوى لسّه فاضي — ابنِ أوّل سيكشن.')" />
    @endforelse
@endsection
