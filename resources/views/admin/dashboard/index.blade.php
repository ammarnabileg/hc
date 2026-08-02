@extends('layouts.admin')

@section('title', setting('admin.dashboard.title', 'لوحة القيادة'))

@section('content')
    <x-page-header :title="setting('admin.dashboard.title', 'لوحة القيادة')"
                   :subtitle="setting('admin.dashboard.subtitle', 'حالة المنصّة والقرارات المستنّياك')"
                   :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')], ['label' => 'لوحة القيادة']]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.users.approvals') && auth()->user()->allows('user_approvals.list'))
                <a href="{{ route('admin.users.approvals') }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">راجع طلبات الاعتماد</a>
            @endif

            @if (count($quickActions) > 1)
                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                             style="background: var(--surface-raised)" aria-label="اختصارات سريعة">⋯</summary>
                    <div class="card absolute end-0 mt-2 w-56 p-1 z-40">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['url'] }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ $action['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- تنبيهات استباقيّة بعتباتها من الإعدادات (12.3-17) --}}
    @foreach ($alerts as $alert)
        <div class="card p-3 mb-3 flex items-center gap-2" role="status">
            <x-state-badge :state="$alert['state']" label="" />
            <span class="text-sm">{{ $alert['text'] }}</span>
        </div>
    @endforeach

    {{-- فلتر فترة عامّ + مقارنة بالسابق — يطبَّق على كلّ الكروت والرسوم (12.3-1/2) --}}
    <form method="get" class="card p-3 mb-4 flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-xs" style="color: var(--text-muted)">الفترة</label>
        <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            @foreach ($rangeOptions as $option)
                <option value="{{ $option }}" @selected($days === $option)>آخر {{ $option }} يوم</option>
            @endforeach
        </select>

        <label class="text-xs flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="compare" value="1" @checked($compare) onchange="this.form.submit()">
            <span>مقارنة بالفترة السابقة</span>
        </label>
    </form>

    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($tab === 'details')
        @include('admin.dashboard.partials.details')
    @else
        @include('admin.dashboard.partials.overview')
    @endif
@endsection

@section('mobile_action')
    @if (\Illuminate\Support\Facades\Route::has('admin.users.approvals') && auth()->user()->allows('user_approvals.list'))
        <a href="{{ route('admin.users.approvals') }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">راجع طلبات الاعتماد</a>
    @endif
@endsection
