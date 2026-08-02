@extends('layouts.app')

@section('title', 'شكوى #'.$complaint->number)

@section('content')
    {{-- بانل تفاصيل الشكوى: النصّ + المراسلات الداخليّة + الردّ والإغلاق (24.3) --}}
    <x-page-header
        :title="$complaint->title"
        :subtitle="'#'.$complaint->number.' · '.($complaint->category ?: 'بلا سبب محدَّد')"
        :breadcrumbs="[
            ['label' => 'الشكاوى', 'url' => route('admin.guidance.complaints')],
            ['label' => '#'.$complaint->number],
        ]" />

    <div class="grid lg:grid-cols-2 gap-4 items-start">
        <section class="card p-4 space-y-3">
            <div class="flex items-center gap-3">
                <x-avatar :user="$complaint->user" size="10" />
                <div class="min-w-0">
                    <div class="font-semibold">{{ $complaint->user?->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">#{{ $complaint->user?->code }}</div>
                </div>
                <div class="ms-auto">
                    <x-state-badge :state="$complaint->status === 'closed' ? 'idle' : 'warn'"
                                   :label="$statuses[$complaint->status] ?? $complaint->status" />
                </div>
            </div>

            <p class="text-sm whitespace-pre-line">{{ $complaint->body }}</p>

            @if ($complaint->wants_contact)
                <p class="text-xs" style="color: var(--text-muted)">
                    يرغب في التواصل — القناة: {{ $complaint->contact_channel ?: 'غير محدَّدة' }}
                </p>
            @endif

            @if ($complaint->close_reason)
                <p class="text-xs">سبب الإغلاق: {{ $complaint->close_reason }}</p>
            @endif

            @can('complaints.assign')
                <form method="post" action="{{ route('admin.guidance.complaints.assign', $complaint) }}" class="flex gap-2">
                    @csrf
                    <select name="assigned_to" class="flex-1 rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">— بلا إسناد —</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((int) $complaint->assigned_to === $assignee->id)>
                                {{ $assignee->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">أسنِد</button>
                </form>
            @endcan
        </section>

        <section class="card p-4 space-y-3">
            <h2 class="font-bold">المراسلات</h2>

            @forelse ($messages as $message)
                <div class="card p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ $message->user?->name }}</span>
                        @if ($message->is_internal)
                            <x-state-badge state="idle" label="داخليّ" />
                        @else
                            <x-state-badge state="ok" label="وصل للمستخدم" />
                        @endif
                    </div>
                    <p class="text-sm mt-1 whitespace-pre-line">{{ $message->body }}</p>
                    <div class="text-xs mt-1" style="color: var(--text-muted)"
                         title="{{ $message->created_at }}">{{ $message->created_at?->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">مفيش ردود لسّه.</p>
            @endforelse

            @can('complaints.edit')
                <form method="post" action="{{ route('admin.guidance.complaints.reply', $complaint) }}" class="space-y-2">
                    @csrf
                    <label class="block">
                        <span class="block text-sm mb-1">الردّ</span>
                        <textarea name="body" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_internal" value="1"> ملاحظة داخليّة (ما توصلش للمستخدم)
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">الحالة بعد الردّ</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($complaint->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">ابعت الردّ</button>
                </form>

                @if ($complaint->status !== 'closed')
                    <form method="post" action="{{ route('admin.guidance.complaints.close', $complaint) }}" class="flex gap-2">
                        @csrf
                        <select name="reason" required class="flex-1 rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($closeReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">إغلاق بسبب</button>
                    </form>
                @endif
            @endcan
        </section>
    </div>

    @include('admin.courses.partials.toast')
@endsection
