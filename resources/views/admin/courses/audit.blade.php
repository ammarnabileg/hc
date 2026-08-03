@extends('layouts.admin')

@section('title', 'سجلّ التدقيق — '.$course->name_ar)

@section('content')
    {{-- سجلّ التدقيق: مَن عدّل ماذا ومتى (12.4-هـ) --}}
    <x-page-header
        :title="'سجلّ التدقيق: '.$course->name_ar"
        subtitle="كلّ تغيير على التدريب متسجّل بصاحبه ووقته."
        :breadcrumbs="[
            ['label' => 'التدريبات', 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => 'التدقيق'],
        ]" />

    @if ($entries->isEmpty())
        <x-empty message="مفيش تغييرات متسجّلة لسّه." />
    @else
        <div class="space-y-3">
            @foreach ($entries as $entry)
                <div class="card p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ $entry->action }}</span>
                        <span class="text-xs" title="{{ $entry->created_at }}" style="color: var(--text-muted)">
                            {{ $entry->created_at?->diffForHumans() }}
                        </span>
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $entry->user?->name ?? 'النظام' }}
                    </div>
                    @if ($entry->new_values)
                        <details class="mt-2">
                            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">التفاصيل</summary>
                            <ul class="text-xs mt-1 space-y-1">
                                @foreach ($entry->new_values as $key => $value)
                                    <li>{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
