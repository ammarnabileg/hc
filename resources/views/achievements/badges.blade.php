@extends('layouts.app')
@section('title', setting('badges.screen.title', 'الشارات'))

@section('content')
    @php
        $percent = $totalCount > 0 ? round($unlockedCount / $totalCount * 100) : 0;
        // بيانات بطاقة الإنجاز القابلة للاستخراج كصورة (12.14-هـ)
        $exportSubtitle = str_replace(
            [':unlocked', ':total'],
            [$unlockedCount, $totalCount],
            (string) setting('badges.screen.export_subtitle', ':unlocked من :total شارة'),
        );
        $exportRows = $rows->where('unlocked', true)->take((int) setting('badges.export_rows', 6))
            ->map(fn (array $row) => [$row['badge']->name_ar, $row['badge']->condition_text_ar])
            ->values()->all();
    @endphp

    <x-page-header
        :title="setting('badges.screen.title', 'الشارات')"
        :subtitle="str_replace([':unlocked', ':total'], [$unlockedCount, $totalCount], (string) setting('badges.screen.subtitle', ':unlocked مفتوحة من :total'))"
        :breadcrumbs="[['label' => setting('badges.screen.breadcrumb_root', 'إنجازاتي')], ['label' => setting('badges.screen.title', 'الشارات')]]">
        {{-- ⭐ بطاقات الإنجاز قابلة للاستخراج كصورة كذلك (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image kind="card" :title="setting('badges.screen.export_title', 'شاراتي')" :subtitle="$exportSubtitle" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    {{-- بار الإنجاز --}}
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span style="color: var(--text-muted)">{{ setting('badges.screen.progress_label', 'إنجازي في الشارات') }}</span>
            <span class="font-bold">{{ $percent }}%</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
            <div class="h-full motion-standard" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
        </div>
    </div>

    <x-filters :action="route('achievements.badges')">
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('badges.screen.filter_state', 'الحالة') }}</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('badges.screen.filter_state_all', 'الكلّ') }}</option>
                <option value="unlocked" @selected($filters['state'] === 'unlocked')>{{ setting('badges.screen.filter_state_unlocked', 'مفتوحة') }}</option>
                <option value="locked" @selected($filters['state'] === 'locked')>{{ setting('badges.screen.filter_state_locked', 'مقفولة') }}</option>
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">{{ setting('badges.screen.filter_search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="{{ setting('badges.screen.filter_search_placeholder', 'اسم الشارة أو شرطها…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('badges.screen.filter_apply', 'طبّق') }}</button>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty :message="setting('badges.screen.empty', 'مفيش شارات بالوصف ده — جرّب بحثًا أوسع.')" />
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
                            ● {{ str_replace(':date', (string) $row['awarded_at']?->translatedFormat('j F Y'), (string) setting('badges.screen.unlocked_at', 'اتفتحت :date')) }}
                        </div>
                    @else
                        <div class="mt-auto pt-1">
                            <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                                <div class="h-full" style="width: {{ $row['progress'] }}%; background: var(--color-state-idle)"></div>
                            </div>
                            <div class="text-[11px] mt-1" style="color: var(--text-muted)">○ {{ str_replace(':percent', $row['progress'], (string) setting('badges.screen.progress_of_condition', ':percent% من الشرط')) }}</div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
