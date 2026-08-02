@extends('layouts.app')

@section('title', 'التدريبات')

@php
    /**
     * الأكاديمية ← التدريبات (13.4-ل · 24.4-9).
     * ⛔ **بلا أيّ شارة تسويقيّة على الكروت** — لا «مجّانيّ» ولا «حصريّ» ولا ما شابه.
     */
@endphp

@section('content')
    <x-page-header
        title="التدريبات"
        :subtitle="$completedPaths.' مسار مكتمل من '.$paths->count()"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'الأكاديمية'], ['label' => 'التدريبات']]" />

    <x-tabs current="paths" :tabs="[
        ['key' => 'paths', 'label' => 'التدريبات', 'url' => route('volunteer.academy')],
        ['key' => 'recordings', 'label' => 'التسجيلات', 'url' => route('volunteer.academy.recordings')],
    ]" />

    <x-filters :action="route('volunteer.academy')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="not_started" @selected($filters['status'] === 'not_started')>لم أبدأ</option>
                <option value="in_progress" @selected($filters['status'] === 'in_progress')>جارٍ</option>
                <option value="done" @selected($filters['status'] === 'done')>مكتمل</option>
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم المسار…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($paths->isEmpty())
        <x-empty message="مفيش تدريبات مرتبطة بقسمك حاليًّا" />
    @else
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($paths as $path)
                @php $state = $progress[$path->id]; @endphp

                <a href="{{ route('volunteer.academy.path', $path) }}" class="card p-4 animate-fadeup motion-standard">
                    <h2 class="font-bold text-sm">{{ $path->name_ar }}</h2>
                    @if ($path->description_ar)
                        <p class="mt-1 text-xs line-clamp-2" style="color: var(--text-muted)">{{ $path->description_ar }}</p>
                    @endif

                    <p class="mt-2 text-xs" style="color: var(--text-muted)">{{ $state['courses']->count() }} تدريبًا</p>

                    {{-- بار التقدّم بنسبة — بلون الهويّة لا كحالة (2.16) --}}
                    <div class="mt-3 h-2 rounded-full" style="background: var(--surface-sunken)">
                        <div class="h-2 rounded-full motion-standard"
                             style="width: {{ $state['percent'] }}%; background: var(--color-brand-500)"></div>
                    </div>
                    <div class="mt-1 text-xs" style="color: var(--text-muted)">{{ $state['percent'] }}%</div>

                    @if ($state['coverage'])
                        <p class="mt-2 text-xs" style="color: var(--text-muted)">
                            يغطّي {{ $state['coverage']['covered'] }} من {{ $state['coverage']['total'] }} تدريبات
                        </p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
@endsection
