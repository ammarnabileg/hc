@extends('layouts.admin')

@section('title', setting('admin.store.finance.index.almalyat', 'الماليّات'))

@section('content')
    <x-page-header :title="setting('admin.store.finance.index.almalyat', 'الماليّات')"
                   :subtitle="setting('admin.store.finance.index.msdr_alhqyqa_alwhyd_lkl_rqm_maly_mjmwaa', 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ — مجموعة محميّة لمالك المنصّة.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.finance.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.finance.index.almtjr_walmalyat', 'المتجر والماليّات'), 'url' => route('admin.store.index')],
                       ['label' => setting('admin.store.finance.index.almalyat', 'الماليّات')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.finance.audit') }}" class="rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-raised)">{{ setting('admin.store.finance.index.sjl_tdqyq_almalyat', 'سجلّ تدقيق الماليّات') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-[220px_1fr]">
        {{-- Side Nav لاصق بالمجموعات (24.3) — وعلى الموبايل رقائق أفقيّة --}}
        <nav class="flex md:flex-col gap-2 min-w-0 overflow-x-auto no-scrollbar md:sticky md:top-4 md:self-start">
            @foreach ($groups as $key => $meta)
                <a href="{{ route('admin.finance.index', ['group' => $key]) }}"
                   class="shrink-0 rounded-xl px-3 py-2 text-sm motion-standard"
                   style="{{ $group === $key
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $meta['label'] }}</a>
            @endforeach
        </nav>

        <div class="space-y-4">
            <div class="card p-4">
                <p class="text-sm" style="color: var(--text-muted)">{{ $groups[$group]['hint'] }}</p>
            </div>

            @if ($group === 'refund')
                @include('admin.store.finance.refund-policy')
            @else
                <div class="card p-4 space-y-4">
                    @forelse ($settings as $setting)
                        @include('admin.settings.partials.field', [
                            'setting' => $setting,
                            'registry' => $registry,
                            'endpoint' => route('admin.finance.save'),
                            'requiresReason' => true,
                        ])
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.store.finance.index.mjmwaa_fadya_dyf_mfatyhha_mn_sydr_almjal', 'مجموعة فاضية — ضيف مفاتيحها من سيدر المجال.') }}</p>
                    @endforelse
                </div>

                {{-- ⭐ صندوق معاينة لحظيّة: المطلوب · الرسوم · الصافي --}}
                <div class="card p-4">
                    <div class="text-xs mb-2" style="color: var(--text-muted)">{{ setting('admin.store.finance.index.maayna_lhzya', 'معاينة لحظيّة') }}</div>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div>{{ setting('admin.store.finance.index.almtlwb', 'المطلوب:') }} <strong>{{ $preview['amount'] }}</strong></div>
                        <div style="color: var(--color-state-danger)">{{ setting('admin.store.finance.index.alrswm', 'الرسوم:') }} {{ $preview['fee'] }}</div>
                        <div style="color: var(--color-brand-500)">{{ setting('admin.store.finance.index.alsafy', 'الصافي:') }} {{ $preview['net'] }}</div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $preview['note'] }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
