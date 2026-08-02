@extends('layouts.app')

@section('title', 'تحليلات المنشور')

@section('content')
    {{-- تحليلات عميقة: نسبة القراءة ومَن قرأ ومَن أقرّ (12.6-أ) --}}
    <x-page-header
        :title="'تحليلات: '.$announcement->title"
        subtitle="مين قرأ ومين أقرّ — والإقرار محسوب مرّة واحدة لكلّ منشور."
        :breadcrumbs="[
            ['label' => 'التعليمات', 'url' => route('admin.guidance.index')],
            ['label' => 'تحليلات'],
        ]" />

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        <x-kpi label="نسبة القراءة" :value="($stats['rate'] ?? 0).'%'" icon="eye" />
        <x-kpi label="قراءات" :value="$stats['reads'] ?? 0" icon="article" />
        <x-kpi label="إقرارات" :value="$stats['acks'] ?? 0" icon="check" />
    </div>

    @if ($readers->isEmpty())
        <x-empty message="محدّش فتح المنشور لسّه." />
    @else
        <div class="space-y-2">
            @foreach ($readers as $read)
                <div class="card p-3 flex items-center gap-3">
                    <x-avatar :user="$read->user" size="8" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm truncate">{{ $read->user?->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            <span title="{{ $read->read_at }}">{{ $read->read_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                    </div>
                    @if ($read->acknowledged_at)
                        <x-state-badge state="ok" label="أقرّ" />
                    @else
                        <x-state-badge state="idle" label="قرأ فقط" />
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
