@extends('layouts.app')

@section('title', 'الإحصائيّات')

@section('content')
    <x-page-header title="الإحصائيّات"
                   subtitle="أرقام للعرض فقط — بفلتر فترة واحد على كلّ التابات."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الإحصائيّات'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.stats.export', array_filter([
                    'tab' => $tab,
                    'from' => $period['from']->toDateString(),
                    'to' => $period['to']->toDateString(),
                    'compare' => $period['compare'] ? 1 : null,
               ])) }}"
               class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">تصدير CSV</a>
        </x-slot:action>
    </x-page-header>

    {{-- فلتر الفترة العامّ + Toggle المقارنة بالفترة السابقة (12.8) --}}
    <x-filters :action="route('admin.stats.index')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">من</span>
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">إلى</span>
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex items-center gap-2 text-xs mt-4">
            <input type="checkbox" name="compare" value="1" @checked($period['compare'])>
            <span>قارن بالفترة السابقة</span>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>
    </x-filters>

    {{-- التاب الماليّ لا يظهر أصلًا لغير المخوَّل — التصفية في الخادم (24.3) --}}
    <x-tabs :current="$tab" :tabs="collect($tabs)->map(fn ($t, $key) => [
        'key' => $key,
        'label' => $t['label'],
        'url' => route('admin.stats.index', ['tab' => $key, 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString(), 'compare' => $period['compare'] ? 1 : null]),
    ])->values()->all()" />

    @if (! empty($data['kpis']))
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
            @foreach (array_slice($data['kpis'], 0, (int) setting('ux.kpi.max_cards', 4)) as $kpi)
                <x-kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" />
            @endforeach
        </div>
    @endif

    @includeIf('admin.stats.tabs.' . $tab)
@endsection
