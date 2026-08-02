@extends('layouts.app')
@section('title', 'الشارات')

@section('content')
    @php $percent = $totalCount > 0 ? round($unlockedCount / $totalCount * 100) : 0; @endphp

    <x-page-header
        title="الشارات"
        :subtitle="$unlockedCount.' مفتوحة من '.$totalCount"
        :breadcrumbs="[['label' => 'إنجازاتي'], ['label' => 'الشارات']]" />

    {{-- بار الإنجاز --}}
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span style="color: var(--text-muted)">إنجازي في الشارات</span>
            <span class="font-bold">{{ $percent }}%</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
            <div class="h-full motion-standard" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
        </div>
    </div>

    <x-filters :action="route('achievements.badges')">
        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="unlocked" @selected($filters['state'] === 'unlocked')>مفتوحة</option>
                <option value="locked" @selected($filters['state'] === 'locked')>مقفولة</option>
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="اسم الشارة أو شرطها…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مفيش شارات بالوصف ده — جرّب بحثًا أوسع." />
    @else
        {{-- شبكة مرنة: عمودان على الموبايل بلا ازدحام --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($rows as $row)
                @php
                    $badge = $row['badge'];
                    $unlocked = $row['unlocked'];
                @endphp

                <article class="card p-4 flex flex-col gap-2 animate-fadeup"
                         style="animation-delay: {{ $loop->index * 40 }}ms; {{ $unlocked ? '' : 'opacity: .72' }}">
                    <span style="color: {{ $unlocked ? 'var(--color-state-honor)' : 'var(--color-state-idle)' }}">
                        @include('achievements.components.badge-icon', ['size' => 44, 'unlocked' => $unlocked])
                    </span>

                    <h2 class="font-bold text-sm">{{ $badge->name_ar }}</h2>

                    {{-- شرط الفتح مكتوب صراحةً — لا ألغاز (24.5) --}}
                    <p class="text-xs" style="color: var(--text-muted)">{{ $badge->condition_text_ar }}</p>

                    @if ($unlocked)
                        <div class="mt-auto pt-1 text-xs" style="color: var(--color-state-ok)">
                            ● اتفتحت {{ $row['awarded_at']?->translatedFormat('j F Y') }}
                        </div>
                    @else
                        <div class="mt-auto pt-1">
                            <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                                <div class="h-full" style="width: {{ $row['progress'] }}%; background: var(--color-state-idle)"></div>
                            </div>
                            <div class="text-[11px] mt-1" style="color: var(--text-muted)">○ {{ $row['progress'] }}% من الشرط</div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
