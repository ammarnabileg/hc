@extends('layouts.app')

@section('title', 'تقرير السعة')

@section('content')
    <x-page-header
        title="تقرير السعة"
        subtitle="أكثر الأقسام تخمةً وأكثرها فراغًا — لفتح قسم جديد أو دمج قسمين."
        :breadcrumbs="[
            ['label' => 'التطوّع', 'url' => route('admin.volunteer.index')],
            ['label' => 'الهيكل والسعة', 'url' => route('admin.volunteer.org')],
            ['label' => 'تقرير السعة'],
        ]" />

    <x-filters :action="route('admin.volunteer.org.capacity')">
        <div>
            <label class="block text-xs mb-1" for="f-track" style="color: var(--text-muted)">المسار</label>
            <select id="f-track" name="track" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($tracks as $track)
                    <option value="{{ $track->id }}" @selected($filters['track'] === $track->id)>{{ $track->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">فلتر</button>
    </x-filters>

    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">إشغال الكيانات</h2>
        @forelse ($rows->sortByDesc('percent') as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['entity']->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">{{ $row['entity']->track?->name_ar }} · شواغر {{ $row['vacancies'] }}</div>
                </div>
                <x-state-badge :state="$row['state']" :label="$row['members'].'/'.$row['cap'].' · '.$row['percent'].'%'" />
            </div>
        @empty
            <x-empty message="مفيش كيانات في النطاق ده." />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-1">نطاق الإشراف لكلّ مسؤول</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">التجاوز تنبيه فقط — بلا منع ولا مبرّر إلزاميّ.</p>

        @forelse ($spans->sortByDesc('count')->take(30) as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['membership']->user?->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ $row['membership']->position?->name_ar }} · {{ $row['membership']->entity?->name_ar }}
                    </div>
                </div>
                <span class="flex items-center gap-2 shrink-0">
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ $row['min'] ?? '—' }} / {{ $row['default'] ?? '—' }} / {{ $row['max'] ?? 'بلا حدّ' }}
                    </span>
                    <x-state-badge :state="$row['state']" :label="$row['count'].' تحته'" />
                </span>
            </div>
        @empty
            <x-empty message="مفيش عضويّات نشطة." />
        @endforelse
    </section>
@endsection
