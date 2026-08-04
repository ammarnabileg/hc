@extends('layouts.admin')

@section('title', setting('admin.ops.system.alnskh_alahtyaty_wsha_alnzam', 'النسخ الاحتياطيّ وصحّة النظام'))

@section('content')
    <x-page-header :title="setting('admin.ops.system.alnskh_alahtyaty_wsha_alnzam', 'النسخ الاحتياطيّ وصحّة النظام')"
                   :subtitle="setting('admin.ops.system.alnzam_slym_wmaana_nskha_nqdr_nrja_lha', 'النظام سليم؟ ومعانا نسخة نقدر نرجع لها؟')"
                   :breadcrumbs="[
                       ['label' => setting('admin.ops.system.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.ops.system.aliadadat_walnzam', 'الإعدادات والنظام'), 'url' => route('admin.settings.index')],
                       ['label' => setting('admin.ops.system.alnskh_alahtyaty_wsha_alnzam', 'النسخ الاحتياطيّ وصحّة النظام')],
                   ]">
        <x-slot:action>
            @can('backups.create')
                {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
                <form method="post" action="{{ route('admin.ops.system.backups.store') }}">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $schedule['kind'] }}">
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.ops.system.nskha_ahtyatya_alan', 'نسخة احتياطيّة الآن') }}</button>
                </form>
            @endcan

            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">⋯</summary>
                <div class="absolute end-0 mt-2 w-64 card p-2 z-20 text-sm space-y-1">
                    @can('system_health.view')
                        <form method="post" action="{{ route('admin.ops.system.health') }}">
                            @csrf
                            <button class="w-full text-start px-2 py-2 rounded hover:opacity-80"><x-icon name="health" size="16" /> {{ setting('admin.ops.system.tshghyl_fhs_sha', 'تشغيل فحص صحّة') }}</button>
                        </form>
                    @endcan
                    @can('system_health.export')
                        <a class="block px-2 py-2 rounded hover:opacity-80" href="{{ route('admin.ops.system.health.export') }}"><x-icon name="download" size="16" /> {{ setting('admin.ops.system.tsdyr_tqryr_alsha', 'تصدير تقرير الصحّة') }}</a>
                    @endcan
                    @can('scheduled_jobs.view')
                        <a class="block px-2 py-2 rounded hover:opacity-80" href="{{ route('admin.ops.system', ['tab' => 'schedule']) }}"><x-icon name="clock" size="16" /> {{ setting('admin.ops.system.jdwla_alnskh', 'جدولة النسخ') }}</a>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- التنبيهات الاستباقيّة: تظهر فوق كلّ شيء لأنّها السبب الوحيد لدخولك الصفحة --}}
    @if ($alerts !== [])
        <div class="card p-4 mb-4" style="border: 1px solid var(--color-state-danger)">
            <div class="flex items-center gap-2 mb-2">
                <x-state-badge state="danger" :label="setting('admin.ops.system.mhtaj_ijra', 'محتاج إجراء')" />
                <h2 class="text-sm font-bold">{{ setting('admin.ops.system.tnbyhat_alnzam', 'تنبيهات النظام') }}</h2>
            </div>
            <ul class="space-y-1 text-sm">
                @foreach ($alerts as $alert)
                    <li>◉ {{ $alert['message'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'health', 'label' => setting('admin.ops.system.sha_alnzam', 'صحّة النظام'), 'url' => route('admin.ops.system', ['tab' => 'health'])],
        ['key' => 'backups', 'label' => setting('admin.ops.system.alnskh', 'النسخ'), 'url' => route('admin.ops.system', ['tab' => 'backups'])],
        ['key' => 'schedule', 'label' => setting('admin.ops.system.aljdwla_walsjl', 'الجدولة والسجلّ'), 'url' => route('admin.ops.system', ['tab' => 'schedule'])],
    ]" />

    @if ($tab === 'backups')
        @include('admin.ops.partials.system-backups')
    @elseif ($tab === 'schedule')
        @include('admin.ops.partials.system-schedule')
    @else
        @include('admin.ops.partials.system-health')
    @endif
@endsection

@section('mobile_action')
    @can('backups.create')
        <form method="post" action="{{ route('admin.ops.system.backups.store') }}">
            @csrf
            <input type="hidden" name="kind" value="{{ $schedule['kind'] }}">
            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.ops.system.nskha_ahtyatya_alan', 'نسخة احتياطيّة الآن') }}</button>
        </form>
    @endcan
@endsection
