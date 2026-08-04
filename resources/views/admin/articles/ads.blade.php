@extends('layouts.admin')

@section('title', setting('admin.articles.ads.alialan_almdfwa', 'الإعلان المدفوع'))

@section('content')
    <x-page-header :title="setting('admin.articles.ads.alialan_almdfwa', 'الإعلان المدفوع')"
                   :subtitle="setting('admin.articles.ads.alshrayh_tbna_mn_byanatna_waltsdyr_mshfr', 'الشرائح تُبنى من بياناتنا، والتصدير مشفَّر — والبيانات الخام ما تغادرش خوادمنا.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.articles.ads.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.articles.ads.alialan_almdfwa', 'الإعلان المدفوع')],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card p-4">
            <h2 class="font-bold text-sm mb-3">{{ setting('admin.articles.ads.alshrayh', 'الشرائح') }}</h2>

            @forelse ($audiences as $audience)
                <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <div>
                        <div class="font-semibold">{{ $audience->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            {{ $rules[$audience->rule['key'] ?? ''] ?? '—' }} {!! strtr(setting('admin.articles.ads.slahya_v1_ywm', '· صلاحيّة :v1 يوم'), [':v1' => e($audience->ttl_days)]) !!}
                        </div>
                    </div>
                    @can('ad_audiences.export')
                        <form method="post" action="{{ route('admin.ads.audiences.export', $audience) }}">
                            @csrf<button class="text-xs underline">{{ setting('admin.articles.ads.tsdyr_mshfr', 'تصدير مشفَّر') }}</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.articles.ads.mafysh_shrayh_lsh', 'مافيش شرائح لسه.') }}</p>
            @endforelse

            <div class="mt-4">{{ $audiences->links() }}</div>
        </div>

        <div class="space-y-4">
            @can('ad_audiences.create')
                <form method="post" action="{{ route('admin.ads.audiences.store') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">{{ setting('admin.articles.ads.shryha', '+ شريحة') }}</h2>
                    <x-form.input name="name" :label="setting('admin.articles.ads.asm_alshryha', 'اسم الشريحة')" required />

                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('admin.articles.ads.alshrt', 'الشرط') }}</span>
                        <select name="rule" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($rules as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('admin.articles.ads.alnwa', 'النوع') }}</span>
                        <select name="kind" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="retargeting">{{ setting('admin.articles.ads.iaada_asthdaf', 'إعادة استهداف') }}</option>
                            <option value="lookalike_source">{{ setting('admin.articles.ads.msdr_jmhwr_mshabh', 'مصدر جمهور مشابه') }}</option>
                        </select>
                    </label>

                    <div class="grid grid-cols-2 gap-2">
                        <x-form.input name="ttl_days" :label="setting('admin.articles.ads.slahya_ayam', 'صلاحيّة (أيّام)')" type="number" value="30" required />
                        <x-form.input name="refresh_hours" :label="setting('admin.articles.ads.althdyth_saaat', 'التحديث (ساعات)')" type="number" value="24" required />
                    </div>

                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.articles.ads.hfz_alshryha', 'حفظ الشريحة') }}</button>
                </form>
            @endcan

            <div class="card p-4 space-y-4">
                <h2 class="font-bold text-sm">{{ setting('admin.articles.ads.iadadat_alttba', 'إعدادات التتبّع') }}</h2>
                @foreach ($pixelSettings as $setting)
                    @include('admin.settings.partials.field', [
                        'setting' => $setting,
                        'registry' => $registry,
                        'endpoint' => route('admin.ads.setting.save'),
                    ])
                @endforeach
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('admin.articles.ads.rfd_almstkhdm_llttba_bywqf_albksl_wahdath', 'رفض المستخدم للتتبّع بيوقف البكسل وأحداث الخادم له فعليًّا — مش شكليًّا.') }}
                </p>
            </div>

            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('admin.articles.ads.akhr_amlyat_altsdyr', 'آخر عمليّات التصدير') }}</h2>
                @forelse ($exports as $export)
                    <div class="text-xs py-1" style="border-top: 1px solid var(--border)">
                        {{ $export->ad_audience?->name }} · {{ $export->rows }} {{ setting('admin.articles.ads.sf', 'صفّ ·') }} {{ $export->hash_algo }} · {{ $export->created_at?->diffForHumans() }}
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.articles.ads.mafysh_tsdyr_lsh', 'مافيش تصدير لسه.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection
