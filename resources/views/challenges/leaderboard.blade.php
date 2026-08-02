@extends('layouts.app')
@section('title', 'لوحة الأبطال')

@php
    // لقطة اللوحة لزرّ [استخراج كصورة] (12.14-هـ)
    $exportSubtitle = 'آخر '.$filters['days'].' يوم';
    $exportRows = collect($board['rows'] ?? [])->values()->map(fn ($row, $i) => [
        'rank' => $row['rank'] ?? $i + 1,
        'u' => $row['user']->id ?? null,
        'name' => $row['user']->name ?? '',
        'value' => (string) ($row['points'] ?? $row['score'] ?? $row['wins'] ?? ''),
    ])->all();
@endphp

@section('content')
    <x-page-header
        title="لوحة الأبطال"
        subtitle="ترتيب المتحدّين خلال آخر {{ $filters['days'] }} يوم."
        :breadcrumbs="[['label' => 'التحديات', 'url' => route('challenges.index')], ['label' => 'لوحة الأبطال']]">
        {{-- ⭐ [استخراج كصورة] — ليدر بورد التحديات (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image title="لوحة الأبطال" :subtitle="$exportSubtitle" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('challenges.leaderboard')">
        <label class="block">
            <span class="block text-sm mb-1">التحدّي</span>
            <select name="challenge" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ الحروب</option>
                @foreach ($challenges as $challenge)
                    <option value="{{ $challenge->id }}" @selected($filters['challenge'] === $challenge->id)>{{ $challenge->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">الفترة</span>
            <select name="days" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ([7 => 'آخر 7 أيّام', 30 => 'آخر 30 يوم', 90 => 'آخر 90 يوم'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['days'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">بحث بالاسم</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم البطل…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>
    </x-filters>

    @if ($board['rows']->isEmpty())
        <x-empty message="لسّه بدري على أوّل ترتيب — ادخل أوّل حرب وابدأ."
                 action="التحدّيات المتاحة" :href="route('challenges.index')" />
    @else
        {{-- صفوف كروت رأسيّة: تشتغل على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="space-y-2">
            @foreach ($board['rows'] as $row)
                @include('challenges.components.champion-row', ['row' => $row])
            @endforeach
        </div>

        {{-- ⭐ صفّي مثبَّت (Sticky) أسفل القائمة دائمًا — فلا أفقد موقعي --}}
        @if ($board['me'])
            <div class="sticky bottom-0 z-30 pt-3 pb-2 mt-2"
                 style="background: linear-gradient(to top, var(--surface) 70%, transparent)">
                @include('challenges.components.champion-row', ['row' => $board['me'], 'pinned' => true])
            </div>
        @else
            <p class="text-xs mt-4 text-center" style="color: var(--text-muted)">
                لسّه مالكش ترتيب في الفترة دي — أوّل تحدّي هيحطّك على اللوحة.
            </p>
        @endif
    @endif
@endsection
