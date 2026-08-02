@extends('layouts.app')

@section('title', 'تحليلات التطوّع')

@section('content')
    <x-page-header
        title="تحليلات التطوّع"
        subtitle="تسرّب · أحمال · صحّة الكيانات · تقرير سعة — مادّة قرار لا أرقام زينة."
        :breadcrumbs="[['label' => 'التطوّع', 'url' => route('admin.volunteer.index')], ['label' => 'التحليلات']]" />

    @include('admin.volunteer.partials.tabs', ['current' => 'analytics'])

    <x-filters :action="route('admin.volunteer.analytics')">
        <div>
            <label class="block text-xs mb-1" for="f-days" style="color: var(--text-muted)">المدى (يوم)</label>
            <select id="f-days" name="days" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ([7, 30, 90] as $option)
                    <option value="{{ $option }}" @selected($days === $option)>آخر {{ $option }} يومًا</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">طبّق</button>
    </x-filters>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi label="متطوّعون نشطون" :value="$kpis['active']" icon="🤝" />
        <x-kpi label="شواغر" :value="$kpis['vacancies']" icon="🪑" />
        <x-kpi label="تجاوزات" :value="$kpis['overflows']" icon="⚠️" />
        <x-kpi label="خروج في المدى" :value="$kpis['exits']" icon="🚪" />
    </section>

    <div class="grid lg:grid-cols-2 gap-4 mt-4">
        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">أسباب التسرّب</h2>
            @forelse ($attrition as $row)
                <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <span>{{ $row['label'] }}</span>
                    <span class="font-bold">{{ $row['count'] }}</span>
                </div>
            @empty
                <x-empty message="لا بيانات كافية في المدى ده." />
            @endforelse
        </section>

        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">صحّة الكيانات</h2>
            <div class="text-sm space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span>كيانات تحت الحدّ الأدنى (اقتراح دمج أو إلغاء طبقة)</span>
                    <x-state-badge :state="$health['unhealthy']->count() ? 'warn' : 'ok'" :label="$health['unhealthy']->count()" />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span>تجاوز نطاق الإشراف (تنبيه لا منع)</span>
                    <x-state-badge :state="$health['overflows']->count() ? 'danger' : 'ok'" :label="$health['overflows']->count()" />
                </div>
            </div>
        </section>
    </div>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">تقرير السعة — الأكثر تخمةً والأكثر فراغًا</h2>
        @forelse ($capacity->sortByDesc('percent')->take(10) as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row['entity']->name_ar }}</span>
                <span class="flex items-center gap-2">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $row['members'] }}/{{ $row['cap'] }}</span>
                    <x-state-badge :state="$row['state']" :label="$row['percent'].'%'" />
                </span>
            </div>
        @empty
            <x-empty message="مفيش كيانات لسّه." />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">الأحمال</h2>
        @forelse ($loads as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row['membership']->user?->name }} — {{ $row['membership']->entity?->name_ar }}</span>
                <span class="flex items-center gap-2">
                    <span class="font-bold">{{ $row['open'] }}</span>
                    <x-state-badge :state="$row['state']" :label="$row['cap'] ? 'سقف '.$row['cap'] : 'بلا سقف'" />
                </span>
            </div>
        @empty
            <x-empty message="مفيش أحمال مفتوحة." />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">رقابة المانحين (معاملات السلوك)</h2>
        @forelse ($granters as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $row->granted_by?->name ?? '—' }}</span>
                <span class="text-xs" style="color: var(--text-muted)">{{ $row->c }} معاملة · معكوسة {{ $row->reversed }}</span>
            </div>
        @empty
            <x-empty message="مفيش معاملات سلوك في المدى ده." />
        @endforelse
    </section>

    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات التحليلات والمؤشّرات',
        'rows' => $settings,
        'action' => route('admin.volunteer.analytics.settings.save'),
        'resetAction' => route('admin.volunteer.reset', 'volunteer_analytics'),
        'lockedKeys' => ['volunteer.analytics.retention_risk_visible_to_volunteer'],
    ])
@endsection
