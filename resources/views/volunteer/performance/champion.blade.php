@extends('layouts.app')

@section('title', 'مشرف الشهر')

@php
    $winner = $board['winner'];
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@php
    // لقطة بطاقة مشرف الشهر لزرّ [استخراج كصورة] (12.14-هـ)
    $exportSubtitle = $nextUpdate->format('Y/m');
    $exportRows = collect($board['rows'] ?? $board ?? [])->values()->map(fn ($row, $i) => [
        'rank' => $i + 1,
        'u' => $row['user']->id ?? null,
        'name' => $row['user']->name ?? '',
        'value' => (string) ($row['score'] ?? $row['vxp'] ?? ''),
    ])->all();
@endphp

@section('content')
    <x-page-header
        title="مشرف الشهر"
        :subtitle="'التحديث القادم: '.$nextUpdate->format('Y/m/d H:i').' — واللوحة ثابتة حتى وقتها.'"
        :breadcrumbs="[['label' => 'الأداء', 'url' => route('volunteer.performance.vxp')], ['label' => 'مشرف الشهر']]">
        {{-- ⭐ [استخراج كصورة] — بطاقة مشرف الشهر (12.14-هـ) --}}
        <x-slot:action>
            <x-export-image title="مشرف الشهر" :subtitle="$exportSubtitle" :rows="$exportRows" />
        </x-slot:action>
    </x-page-header>

    <x-filters :action="route('volunteer.performance.champion')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النطاق</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ المتطوّعين</option>
                @foreach ($memberships as $membership)
                    <option value="{{ $membership->entity_id }}" @selected($filters['entity'] === (int) $membership->entity_id)>
                        {{ $membership->entity?->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الشهر</span>
            <select name="month" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">النافذة الجارية</option>
                @foreach ($archive as $month)
                    <option value="{{ $month }}" @selected($filters['month'] === $month)>{{ $month }}</option>
                @endforeach
            </select>
        </label>
    </x-filters>

    @if (! $winner)
        <x-empty message="لسّه بدري على أوّل حساب للشهر" action="شوف ليدر بورد VXP" :href="route('volunteer.performance.vxp')" />
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
                <x-state-badge state="honor" :label="'معدّل Rep: '.$num($winner['rep_average'])" />
            </div>
        </article>

        {{-- بيان معيار الحسم — كي لا يكون الترتيب صندوقًا أسود --}}
        <p class="text-xs mt-3 mb-3" style="color: var(--text-muted)">معيار الحسم: {{ $criteria }}</p>

        <div class="card overflow-hidden">
            <div class="hidden md:grid grid-cols-5 gap-2 px-4 py-2 text-xs" style="color: var(--text-muted)">
                <span>المركز</span><span class="col-span-2">الاسم</span><span>معدّل Rep</span><span>معدّل زيادة VXP</span>
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
                    <span class="text-xs" style="color: var(--text-muted)">{{ $num($row['vxp_rate']) }} / يوم</span>
                </div>
            @endforeach
        </div>
    @endif
@endsection
