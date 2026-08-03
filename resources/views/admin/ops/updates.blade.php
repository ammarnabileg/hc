@extends('layouts.admin')

@section('title', 'التحديثات والترحيل')

@section('content')
    <x-page-header title="التحديثات والترحيل"
                   subtitle="نقرة آمنة: نشوف الأوّل، ناخد نسخة، ننفّذ — ولو حصل حاجة نرجع."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الإعدادات والنظام', 'url' => route('admin.settings.index')],
                       ['label' => 'التحديثات والترحيل'],
                   ]">
        <x-slot:action>
            @can('updates.manage')
                {{-- فعل رئيسيّ واحد: الـDry-run أوّلًا، والتنفيذ لا يظهر بارزًا إلّا بعد المعاينة --}}
                @if ($hasFreshDryRun && $pending !== [])
                    <button type="button" data-modal-open="migrate-confirm"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">تنفيذ التحديث</button>
                @else
                    <form method="post" action="{{ route('admin.ops.updates.dry-run') }}">
                        @csrf
                        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">Dry-run</button>
                    </form>
                @endif
            @endcan

            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">⋯</summary>
                <div class="absolute end-0 mt-2 w-64 card p-2 z-20 text-sm space-y-1">
                    <form method="post" action="{{ route('admin.ops.updates.check') }}">
                        @csrf
                        <button class="w-full text-start px-2 py-2 rounded hover:opacity-80">🔎 فحص المايجريشنز المعلّقة</button>
                    </form>
                    @can('updates.manage')
                        <form method="post" action="{{ route('admin.ops.updates.dry-run') }}">
                            @csrf
                            <button class="w-full text-start px-2 py-2 rounded hover:opacity-80">👁 Dry-run (بلا تنفيذ)</button>
                        </form>
                    @endcan
                    @can('updates.restore')
                        <button type="button" data-modal-open="rollback-confirm"
                                class="w-full text-start px-2 py-2 rounded hover:opacity-80"
                                style="color: var(--color-state-danger)">↩ استرجاع آخر دفعة</button>
                    @endcan
                    @can('version_history.list')
                        <a class="block px-2 py-2 rounded hover:opacity-80" href="{{ route('admin.ops.updates.history') }}">🗂 سجلّ الإصدارات</a>
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

    {{-- أربعة كروت بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
        <div class="card p-4">
            <div class="text-sm" style="color: var(--text-muted)">الإصدار الحاليّ</div>
            <div class="mt-2 text-2xl font-extrabold font-mono">{{ $version }}</div>
        </div>
        <x-kpi label="هجرات مطبَّقة" :value="(string) $applied" icon="✅" />
        <x-kpi label="هجرات معلّقة" :value="(string) count($pending)" icon="⏳"
               :state="$pending === [] ? 'ok' : 'warn'" />
        <x-kpi label="آخر دفعة" :value="(string) count($lastBatch)" icon="↩" />
    </div>

    <x-tabs :current="$tab" :tabs="array_values(array_filter([
        ['key' => 'overview', 'label' => 'الحالة والترحيل', 'url' => route('admin.ops.updates')],
        auth()->user()->can('version_history.list')
            ? ['key' => 'history', 'label' => 'سجلّ الإصدارات', 'url' => route('admin.ops.updates.history')]
            : null,
    ]))" />

    @if ($tab === 'history')
        @include('admin.ops.partials.updates-history')
    @else
        @include('admin.ops.partials.updates-overview')
    @endif

    @include('admin.ops.partials.updates-modals')
@endsection

@section('mobile_action')
    @can('updates.manage')
        @if ($hasFreshDryRun && $pending !== [])
            <button type="button" data-modal-open="migrate-confirm"
                    class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">تنفيذ التحديث</button>
        @else
            <form method="post" action="{{ route('admin.ops.updates.dry-run') }}">
                @csrf
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">Dry-run</button>
            </form>
        @endif
    @endcan
@endsection
