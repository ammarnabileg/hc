@extends('layouts.volunteer')

@section('title', setting('volunteer.performance_champion.title', 'مشرف الشهر'))

@php
    $winner = $board['winner'];
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@php
    // لقطة بطاقة مشرف الشهر لزرّ [استخراج كصورة] (12.14-هـ)
    $exportSubtitle = $nextUpdate->format('Y/m');
    // المفتاح `candidates` هو مصدر الصفوف (ChampionService::board) — و`$board`
    // نفسه فيه تواريخ لا صفوف، فالرجوع إليه كان يكسر الشاشة.
    $exportRows = collect($board['candidates'] ?? [])->values()->map(fn ($row, $i) => [
        'rank' => $row['rank'] ?? $i + 1,
        'u' => $row['user']?->id,
        'name' => $row['user']?->name ?? '',
        'value' => $num($row['rep_average'] ?? 0),
    ])->all();
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.performance_champion.title', 'مشرف الشهر')"
        :subtitle="setting('volunteer.performance_champion.subtitle', 'التحديث القادم: ').$nextUpdate->format('Y/m/d H:i').setting('volunteer.performance_champion.subtitle_2', ' — واللوحة ثابتة حتى وقتها.')"
        :breadcrumbs="[['label' => setting('volunteer.performance_champion.label', 'الأداء'), 'url' => route('volunteer.performance.vxp')], ['label' => setting('volunteer.performance_champion.title', 'مشرف الشهر')]]">
        {{-- ⭐ [استخراج كصورة] — بطاقة مشرف الشهر (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image :title="setting('volunteer.performance_champion.title', 'مشرف الشهر')" :subtitle="$exportSubtitle" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('volunteer.performance.champion')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.performance_champion.field', 'النطاق') }}</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.performance_champion.option', 'كلّ المتطوّعين') }}</option>
                @foreach ($memberships as $membership)
                    <option value="{{ $membership->entity_id }}" @selected($filters['entity'] === (int) $membership->entity_id)>
                        {{ $membership->entity?->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.performance_champion.field_2', 'الشهر') }}</span>
            <select name="month" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.performance_champion.option_2', 'النافذة الجارية') }}</option>
                @foreach ($archive as $month)
                    <option value="{{ $month }}" @selected($filters['month'] === $month)>{{ $month }}</option>
                @endforeach
            </select>
        </label>
    </x-filters>

    @if (! $winner)
        <x-empty :message="setting('volunteer.performance_champion.empty', 'لسّه بدري على أوّل حساب للشهر')" :action="setting('volunteer.performance_champion.action', 'شوف ليدر بورد VXP')" :href="route('volunteer.performance.vxp')" />
    @else
        {{-- كارت الفائز الكبير — أفاتار بلا هالة (24.4) --}}
        <article class="card p-6 text-center animate-fadeup" style="border: 1px solid var(--color-state-honor)">
            <div class="flex justify-center mb-3">
                <x-avatar :user="$winner['user']" size="20" />
            </div>
            <h2 class="text-xl font-extrabold">{{ $winner['user']->shortName() }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                {{ $winner['membership']?->position?->name_ar ?? '—' }} · {{ $winner['membership']?->entity?->name_ar ?? '—' }}
            </p>
            <div class="mt-3 inline-flex items-center gap-2">
                <x-state-badge state="honor" :label="setting('volunteer.performance_champion.label_2', 'معدّل Rep: ').$num($winner['rep_average'])" />
            </div>
        </article>

        {{-- بيان معيار الحسم — كي لا يكون الترتيب صندوقًا أسود --}}
        <p class="text-xs mt-3 mb-3" style="color: var(--text-muted)">{{ setting('volunteer.performance_champion.text', 'معيار الحسم:') }} {{ $criteria }}</p>

        <div class="card overflow-hidden">
            <div class="hidden md:grid grid-cols-5 gap-2 px-4 py-2 text-xs" style="color: var(--text-muted)">
                <span>{{ setting('volunteer.performance_champion.text_2', 'المركز') }}</span><span class="col-span-2">{{ setting('volunteer.performance_champion.text_3', 'الاسم') }}</span><span>{{ setting('volunteer.performance_champion.text_4', 'معدّل Rep') }}</span><span>{{ setting('volunteer.performance_champion.text_5', 'معدّل زيادة VXP') }}</span>
            </div>

            @foreach ($board['candidates'] as $row)
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-center px-4 py-3"
                     style="border-top: 1px solid var(--border)">
                    <span class="text-sm font-bold">{{ $row['rank'] }}</span>
                    <span class="md:col-span-2 flex items-center gap-2">
                        <x-avatar :user="$row['user']" size="8" />
                        <span class="text-sm truncate">{{ $row['user']->shortName() }}</span>
                    </span>
                    <span class="text-xs">{{ $num($row['rep_average']) }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">{{ $num($row['vxp_rate']) }} / {{ setting('volunteer.common.day', 'يوم') }}</span>
                </div>
            @endforeach
        </div>
    @endif
@endsection
