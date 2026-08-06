@extends('layouts.guest')
@section('title', (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.section_1', 'تنصيب المنصّة — فحص المتطلّبات'))

@php
    $titles = [
        'php' => (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.php_1', 'إصدار PHP'),
        'extensions' => (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.php_2', 'الامتدادات'),
        'permissions' => (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.php_3', 'صلاحيّات الكتابة'),
    ];
@endphp

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="{{ \App\Services\Setup\SetupSettings::text('setup.requirements_view.title_1', 'فحص المتطلّبات') }}"
            subtitle="{{ \App\Services\Setup\SetupSettings::text('setup.requirements_view.subtitle_1', 'بنتأكّد إنّ الخادم جاهز، عشان التنصيب مايقفش في النصّ.') }}" />

        @include('setup.partials.alert', ['keys' => ['requirements', 'setup']])

        @foreach ($groups as $group => $checks)
            <h2 class="text-sm font-bold mt-5 mb-2">{{ $titles[$group] ?? $group }}</h2>

            <ul class="space-y-2">
                @foreach ($checks as $check)
                    <li class="rounded-xl p-3" style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <span class="text-sm flex items-center gap-2">
                                {{-- الرمز مع اللون دائمًا (2.16-ب) — والرمز أهمّ على الموبايل --}}
                                <span aria-hidden="true">{{ $check['ok'] ? '✅' : ($check['required'] ? '❌' : '⚠️') }}</span>
                                <span>{{ $check['label'] }}</span>
                            </span>

                            <span class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
                                <span dir="ltr">{{ $check['value'] }}</span>
                                <x-state-badge
                                    :state="$check['ok'] ? 'ok' : ($check['required'] ? 'danger' : 'warn')"
                                    :label="$check['ok'] ? (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.label_1', 'تمام') : ($check['required'] ? (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.label_2', 'لازم') : (string) \App\Services\Setup\SetupSettings::text('setup.requirements_view.label_3', 'اختياريّ'))" />
                            </span>
                        </div>

                        @unless ($check['ok'])
                            <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $check['fix'] }}</p>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endforeach

        <div class="flex items-center gap-2 mt-6 flex-wrap">
            <form method="post" action="{{ route('setup.requirements.store') }}">
                @csrf
                <button @disabled(! $passed)
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color:#04201c; {{ $passed ? '' : 'opacity:.5; cursor:not-allowed' }}">
                    {{ \App\Services\Setup\SetupSettings::text('setup.requirements_view.text_1', 'كمّل لقاعدة البيانات') }}
                </button>
            </form>

            <a href="{{ route('setup.requirements') }}"
               class="btn rounded-xl px-4 py-2 text-sm inline-flex items-center motion-standard"
               style="border: 1px solid var(--border)">{{ \App\Services\Setup\SetupSettings::text('setup.requirements_view.text_2', 'أعد الفحص') }}</a>
        </div>

        @unless ($passed)
            <p class="text-xs mt-3" style="color: var(--text-muted)">
                {{ \App\Services\Setup\SetupSettings::text('setup.requirements_view.text_3', 'صحّح اللي عليه ❌ من لوحة الاستضافة، وبعدين اضغط «أعد الفحص» — وهنكمّل على طول.') }}
            </p>
        @endunless
    </div>
</div>
@endsection
