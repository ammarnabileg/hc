@extends('layouts.app')

@section('title', 'معاينة كطالب — '.$course->name_ar)

@section('content')
    {{-- معاينة كطالب قبل النشر (12.4-هـ) --}}
    <x-page-header
        :title="'معاينة كطالب: '.$course->name_ar"
        subtitle="ده اللي المتدرّب هيشوفه — بلا أزرار إدارة."
        :breadcrumbs="[
            ['label' => 'التدريبات', 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => 'معاينة'],
        ]" />

    <div class="card p-4 mb-4">
        <h2 class="font-bold text-lg">{{ $course->name_ar }}</h2>
        @if ($course->description_ar)
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $course->description_ar }}</p>
        @endif
        <p class="text-sm mt-2">{{ $course->is_free ? 'مجّانيّ' : (int) $course->price_coins.' كوينز' }}</p>
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
                            <x-state-badge state="ok" label="معاينة مجّانيّة" />
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <x-empty message="المحتوى لسّه فاضي — ابنِ أوّل سيكشن." />
    @endforelse
@endsection
