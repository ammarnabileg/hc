@extends('layouts.app')
@section('title', setting('leaderboard.title', 'الليدر بورد'))

@php
    // لقطة اللوحة لزرّ [استخراج كصورة] (12.14-هـ) — نفس ما تعرضه الشاشة تمامًا
    $rangeLabel = str_replace(':days', (string) $filters['days'], (string) setting('leaderboard.range_label', 'آخر :days يومًا'));
    $exportSubtitle = $rangeLabel;
    $exportRows = $board['rows']->map(fn ($row) => [
        'rank' => $row['rank'],
        'u' => $row['user']->id,
        'name' => $row['user']->name,
        'value' => number_format($row['delta']).' '.setting('leaderboard.xp_label', 'XP'),
        'me' => (bool) ($board['me'] && $board['me']['rank'] === $row['rank']),
    ])->values()->all();
@endphp

@section('content')
    <x-page-header
        :title="setting('leaderboard.title', 'الليدر بورد')"
        :subtitle="$board['me'] ? setting('leaderboard.rank_prefix', 'ترتيبك دلوقتي').' #'.$board['me']['rank'].' '.setting('leaderboard.rank_of', 'من').' '.number_format($board['total']).' — '.$rangeLabel : setting('leaderboard.empty_hint', 'ابدأ أوّل تدريب وهتظهر هنا.')"
        :breadcrumbs="[['label' => setting('leaderboard.breadcrumb', 'إنجازاتي')], ['label' => setting('leaderboard.title', 'الليدر بورد')]]">
        {{-- ⭐ [استخراج كصورة] في كلّ ليدر بورد (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image :title="setting('leaderboard.title', 'الليدر بورد')" :subtitle="$exportSubtitle" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    {{--
        ⭐ كارت صاحب الحساب **في الأعلى** (7.3) — كان `sticky bottom-0` أي في الأسفل،
        والدستور يصفه صراحةً بأنّه «كارت ترتيب صاحب الحساب **في الأعلى**».
        وهو أوّل ما تبحث عنه العين، ومنه يبدأ التحفيز لا من ذيل الصفحة.
    --}}
    @if ($board['me'])
        {{-- المنافس القريب = الصفّ الذي فوقي مباشرةً، لتقول اللوحة «محتاج N XP» (2.9-5) --}}
        @include('achievements.components.me-card', [
            'me' => $board['me'],
            'total' => $board['total'],
            'rangeLabel' => $rangeLabel,
            'rival' => $board['rows']->firstWhere('rank', $board['me']['rank'] - 1),
        ])
    @endif

    <x-filters :action="route('achievements.leaderboard')">
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('leaderboard.filter.scope', 'النطاق') }}</span>
            <select name="scope" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="all" @selected($filters['scope'] === 'all')>{{ setting('leaderboard.scope.all', 'الكلّ') }}</option>
                <option value="country" @selected($filters['scope'] === 'country')>{{ setting('leaderboard.scope.country', 'دولتي') }}</option>
                <option value="governorate" @selected($filters['scope'] === 'governorate')>{{ setting('leaderboard.scope.governorate', 'محافظتي') }}</option>
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('leaderboard.filter.period', 'الفترة') }}</span>
            <select name="days" data-leaderboard-days class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($ranges as $value => $label)
                    <option value="{{ $value }}" @selected(! $filters['is_custom'] && (int) $filters['days'] === (int) $value)>{{ $label }}</option>
                @endforeach

                {{-- ⭐ فترة يحدّدها المستخدم بنفسه (7.3) --}}
                @if ($customEnabled)
                    <option value="custom" @selected($filters['is_custom'])>{{ setting('leaderboard.range.custom', 'فترة أحدّدها') }}</option>
                @endif
            </select>
        </label>

        @if ($customEnabled)
            <label class="block" data-leaderboard-custom @style(['display: none' => ! $filters['is_custom']])>
                <span class="block text-sm mb-1">{{ setting('leaderboard.range.custom_label', 'عدد الأيّام') }}</span>
                <input type="number" name="custom_days" min="1" max="{{ setting('leaderboard.max_range_days', 365) }}"
                       value="{{ $filters['days'] }}" class="w-28 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        @endif

        {{-- ⭐ فلترة بدولة/محافظة **بعينها** لا «دولتي» فقط (7.3) --}}
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('leaderboard.filter.country', 'الدولة') }}</span>
            <select name="country_id" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('leaderboard.filter.any', 'كلّ الدول') }}</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected((int) $filters['country_id'] === (int) $country->id)>{{ $country->name_ar }}</option>
                @endforeach
            </select>
        </label>

        @if ($governorates->isNotEmpty())
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('leaderboard.filter.governorate', 'المحافظة') }}</span>
                <select name="governorate_id" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('leaderboard.filter.any_governorate', 'كلّ المحافظات') }}</option>
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate->id }}" @selected((int) $filters['governorate_id'] === (int) $governorate->id)>{{ $governorateLabels[$governorate->id] ?? $governorate->name_ar }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">{{ setting('leaderboard.filter.search', 'بحث بالاسم') }}</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="{{ setting('leaderboard.filter.search_placeholder', 'اسم زميلك…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('leaderboard.filter.apply', 'طبّق') }}</button>
    </x-filters>

    @if ($board['rows']->isEmpty())
        <x-empty :message="setting('leaderboard.empty_hint', 'ابدأ أوّل تدريب وهتظهر هنا.')" />
    @else
        {{-- ⭐ منصّة تتويج للتوب 3 (7.3) --}}
        @if ($board['podium']->isNotEmpty())
            @include('achievements.components.podium', ['podium' => $board['podium']])
        @endif

        <div class="space-y-2">
            @foreach ($board['rows'] as $row)
                @include('achievements.components.xp-row', ['row' => $row])
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        /* «فترة أحدّدها» تكشف خانة الأيّام وترسلها مكان `days` — بلا صفحة ثانية (2.15) */
        (() => {
            const select = document.querySelector('[data-leaderboard-days]');
            const custom = document.querySelector('[data-leaderboard-custom]');
            if (!select || !custom) return;

            const sync = () => { custom.style.display = select.value === 'custom' ? '' : 'none'; };
            select.addEventListener('change', sync);
            sync();

            select.form?.addEventListener('submit', () => {
                if (select.value !== 'custom') return;
                const input = custom.querySelector('input');
                select.value = input.value || '30';
                input.disabled = true;
            });
        })();
    </script>
@endpush
