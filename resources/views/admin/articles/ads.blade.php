@extends('layouts.admin')

@section('title', 'الإعلان المدفوع')

@section('content')
    <x-page-header title="الإعلان المدفوع"
                   subtitle="الشرائح تُبنى من بياناتنا، والتصدير مشفَّر — والبيانات الخام ما تغادرش خوادمنا."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الإعلان المدفوع'],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card p-4">
            <h2 class="font-bold text-sm mb-3">الشرائح</h2>

            @forelse ($audiences as $audience)
                <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                    <div>
                        <div class="font-semibold">{{ $audience->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            {{ $rules[$audience->rule['key'] ?? ''] ?? '—' }} · صلاحيّة {{ $audience->ttl_days }} يوم
                        </div>
                    </div>
                    @can('ad_audiences.export')
                        <form method="post" action="{{ route('admin.ads.audiences.export', $audience) }}">
                            @csrf<button class="text-xs underline">تصدير مشفَّر</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">مافيش شرائح لسه.</p>
            @endforelse

            <div class="mt-4">{{ $audiences->links() }}</div>
        </div>

        <div class="space-y-4">
            @can('ad_audiences.create')
                <form method="post" action="{{ route('admin.ads.audiences.store') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">+ شريحة</h2>
                    <x-form.input name="name" label="اسم الشريحة" required />

                    <label class="block text-sm">
                        <span class="block mb-1">الشرط</span>
                        <select name="rule" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($rules as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="block mb-1">النوع</span>
                        <select name="kind" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="retargeting">إعادة استهداف</option>
                            <option value="lookalike_source">مصدر جمهور مشابه</option>
                        </select>
                    </label>

                    <div class="grid grid-cols-2 gap-2">
                        <x-form.input name="ttl_days" label="صلاحيّة (أيّام)" type="number" value="30" required />
                        <x-form.input name="refresh_hours" label="التحديث (ساعات)" type="number" value="24" required />
                    </div>

                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">حفظ الشريحة</button>
                </form>
            @endcan

            <div class="card p-4 space-y-4">
                <h2 class="font-bold text-sm">إعدادات التتبّع</h2>
                @foreach ($pixelSettings as $setting)
                    @include('admin.settings.partials.field', [
                        'setting' => $setting,
                        'registry' => $registry,
                        'endpoint' => route('admin.ads.setting.save'),
                    ])
                @endforeach
                <p class="text-xs" style="color: var(--text-muted)">
                    رفض المستخدم للتتبّع بيوقف البكسل وأحداث الخادم له فعليًّا — مش شكليًّا.
                </p>
            </div>

            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">آخر عمليّات التصدير</h2>
                @forelse ($exports as $export)
                    <div class="text-xs py-1" style="border-top: 1px solid var(--border)">
                        {{ $export->ad_audience?->name }} · {{ $export->rows }} صفّ · {{ $export->hash_algo }} · {{ $export->created_at?->diffForHumans() }}
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">مافيش تصدير لسه.</p>
                @endforelse
            </div>
        </div>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection
