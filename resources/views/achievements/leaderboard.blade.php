@extends('layouts.app')
@section('title', 'الليدر بورد')

@section('content')
    <x-page-header
        title="الليدر بورد"
        :subtitle="$board['me'] ? 'ترتيبك دلوقتي #'.$board['me']['rank'].' من '.number_format($board['total']) : 'ابدأ أوّل تدريب وهتظهر هنا.'"
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الليدر بورد']]" />

    <x-filters :action="route('achievements.leaderboard')">
        <label class="block">
            <span class="block text-sm mb-1">النطاق</span>
            <select name="scope" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="all" @selected($filters['scope'] === 'all')>الكلّ</option>
                <option value="country" @selected($filters['scope'] === 'country')>دولتي</option>
                <option value="governorate" @selected($filters['scope'] === 'governorate')>محافظتي</option>
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
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="اسم زميلك…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>
    </x-filters>

    @if ($board['rows']->isEmpty())
        <x-empty message="ابدأ أوّل تدريب وهتظهر هنا." />
    @else
        <div class="space-y-2">
            @foreach ($board['rows'] as $row)
                @include('achievements.components.xp-row', ['row' => $row])
            @endforeach
        </div>

        {{-- ⭐ صفّي مثبَّت أسفل القائمة دائمًا --}}
        @if ($board['me'])
            <div class="sticky bottom-0 z-30 pt-3 pb-2 mt-2"
                 style="background: linear-gradient(to top, var(--surface) 70%, transparent)">
                @include('achievements.components.xp-row', ['row' => $board['me'], 'pinned' => true])
            </div>
        @endif
    @endif
@endsection
