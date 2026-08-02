@extends('layouts.app')

@section('title', 'الشكاوى والمقترحات')

@section('content')
    {{-- الشكاوى (24.3): طابور بالحالات + إسناد + ردّ + إغلاق بسبب --}}
    <x-page-header
        title="الشكاوى والمقترحات"
        subtitle="طابور واضح بالحالات — والردّ في وقته."
        :breadcrumbs="[['label' => 'التوجيه والدعم', 'url' => route('admin.guidance.index')], ['label' => 'الشكاوى']]" />

    <x-tabs :tabs="$tabs" current="complaints" />

    <x-filters :action="route('admin.guidance.complaints')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="العنوان أو النصّ أو الكود…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">السبب</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason }}" @selected($filters['category'] === $reason)>{{ $reason }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
    </x-filters>

    @if ($complaints->isEmpty())
        <x-empty message="لا شكاوى — كلّ شيء هادئ." />
    @else
        <div class="space-y-3">
            @foreach ($complaints as $complaint)
                <div class="card p-4">
                    <div class="flex items-start gap-3 flex-wrap">
                        <x-avatar :user="$complaint->user" size="10" />

                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.guidance.complaints.show', $complaint) }}"
                               class="font-semibold underline">{{ $complaint->title }}</a>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                #{{ $complaint->number }} · {{ $complaint->user?->name }}
                                · {{ $complaint->category ?: 'بلا سبب محدَّد' }}
                                @if ($complaint->wants_contact)
                                    · يرغب في التواصل ({{ $complaint->contact_channel ?: 'بلا قناة' }})
                                @endif
                            </div>
                            <div class="text-xs mt-1">
                                المعيَّن له: {{ $assigneeNames[$complaint->assigned_to] ?? '— محدّش —' }}
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <x-state-badge :state="$complaint->status === 'closed' ? 'idle' : ($complaint->status === 'in_review' ? 'warn' : 'ok')"
                                           :label="$statuses[$complaint->status] ?? $complaint->status" />
                            {{-- عمر الشكوى مقابل SLA — يتلوّن عند التأخّر (24.3) --}}
                            <x-state-badge :state="$sla[$complaint->id] ?? 'ok'"
                                           :label="$complaint->created_at?->diffForHumans()" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $complaints->links() }}</div>
    @endif

    @include('admin.courses.partials.toast')
@endsection
