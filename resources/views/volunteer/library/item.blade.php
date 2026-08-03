@extends('layouts.volunteer')

@section('title', $item->title)

@php
    /** تفاصيل المُدخَل — والمقيَّد يبقى معروفًا بعنوانه ولا يُفتَح محتواه (23-3.3). */
@endphp

@section('content')
    <x-page-header
        :title="$item->title"
        :subtitle="$types[$item->type] ?? $item->type"
        :breadcrumbs="[
            ['label' => 'لوحة التطوّع', 'url' => url('/volunteer')],
            ['label' => 'المكتبة الداخليّة', 'url' => route('volunteer.library')],
            ['label' => $item->title],
        ]" />

    @if ($locked)
        <div class="card p-6 text-center">
            <p class="text-sm"><x-icon name="lock" size="16" /> المُدخَل ده خارج نطاقك دلوقتي.</p>
            <form method="post" action="{{ route('volunteer.library.request-access', $item) }}" class="mt-4 flex justify-center gap-2">
                @csrf
                <input type="text" name="reason" placeholder="سبب (اختياريّ)" class="rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">اطلب وصولًا</button>
            </form>
            <p class="mt-2 text-xs" style="color: var(--text-muted)">دايركتور الكيان هو اللي بيقرّ الطلب.</p>
        </div>
    @else
        <div class="card p-4 space-y-3">
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt style="color: var(--text-muted)">الكيان</dt>
                <dd>{{ $item->entity?->name_ar ?? '—' }}</dd>
                <dt style="color: var(--text-muted)">صاحب المخرج</dt>
                <dd>{{ $item->owner?->name ?? '—' }}</dd>
                <dt style="color: var(--text-muted)">تاريخ الاعتماد</dt>
                <dd>{{ $item->approved_at?->translatedFormat('j F Y') ?? '—' }}</dd>
                <dt style="color: var(--text-muted)">مستوى الوصول</dt>
                <dd>{{ $library->accessLevels()[$item->access_level] ?? $item->access_level }}</dd>
            </dl>

            @if ($item->content_text)
                <div>
                    <h2 class="text-xs mb-1" style="color: var(--text-muted)">المحتوى المفهرَس</h2>
                    <p class="text-sm leading-7 whitespace-pre-wrap">{{ $item->content_text }}</p>
                </div>
            @endif

            @if ($item->file_path)
                <a href="{{ \Illuminate\Support\Facades\Storage::url($item->file_path) }}"
                   class="btn inline-flex rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">فتح / تحميل</a>
            @endif
        </div>
    @endif
@endsection
