@extends('layouts.guest')
@section('title', (string) \App\Services\Setup\SetupSettings::text('setup.platform_view.section_1', 'تنصيب المنصّة — بيانات المنصّة'))

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.title_1', 'بيانات المنصّة') }}"
            subtitle="{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.subtitle_1', 'الاسم والشعار والرابط والوقت واللغة — وكلّها تتعدّل بعدين من لوحة الإدارة.') }}" />

        @include('setup.partials.alert', ['keys' => ['setup']])

        <form method="post" action="{{ route('setup.platform.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <x-form.input name="app_name" label="{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.label_1', 'اسم المنصّة') }}" :value="$draft['app_name']" required
                          hint="{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.hint_1', 'الاسم اللي هيظهر في الهيدر وعنوان المتصفّح.') }}" />

            <label class="block">
                <span class="block text-sm mb-1">{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.text_1', 'الشعار (اختياريّ)') }}</span>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <span class="block text-xs mt-1" style="color: var(--text-muted)">PNG {{ \App\Services\Setup\SetupSettings::text('setup.platform_view.text_2', 'أو JPG أو WEBP أو SVG — وتقدر ترفعه بعدين من اللوحة.') }}</span>
                @error('logo')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
            </label>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c">{{ \App\Services\Setup\SetupSettings::text('setup.platform_view.text_7', 'احفظ وكمّل لحساب المالك') }}</button>
        </form>
    </div>
</div>
@endsection
