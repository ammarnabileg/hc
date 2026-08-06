@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'setup.migrate_view.js_1' => (string) \App\Services\Setup\SetupSettings::text('setup.migrate_view.js_1', 'بنجهّز…'),
    ]);
@endphp

@extends('layouts.guest')
@section('title', (string) \App\Services\Setup\SetupSettings::text('setup.migrate_view.section_1', 'تنصيب المنصّة — تجهيز الجداول'))

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="{{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.title_1', 'تجهيز الجداول والبيانات') }}"
            subtitle="{{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.subtitle_1', 'هنبني جداول المنصّة ونحمّل الأدوار والصلاحيّات والإعدادات — كلّه من هنا بلا تيرمينال.') }}" />

        @include('setup.partials.alert', ['keys' => ['migrate', 'setup']])

        @if ($log)
            <ul class="space-y-2 mb-5">
                @foreach ($log as $line)
                    <li class="rounded-xl p-3" style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm flex items-center gap-2">
                                <span aria-hidden="true">{{ $line['ok'] ? '✅' : '❌' }}</span>
                                <span>{{ $line['label'] }}</span>
                            </span>
                            <x-state-badge :state="$line['ok'] ? 'ok' : 'danger'" :label="$line['ok'] ? (string) \App\Services\Setup\SetupSettings::text('setup.migrate_view.label_1', 'تمّ') : (string) \App\Services\Setup\SetupSettings::text('setup.migrate_view.label_2', 'وقف')" />
                        </div>
                        @if (! $line['ok'] && $line['output'])
                            <pre class="text-xs mt-2 min-w-0 overflow-x-auto" dir="ltr" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($line['output'], 600) }}</pre>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mb-5">
                <x-empty message="{{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.message_1', 'لسّه ماشغّلناش التجهيز — اضغط الزرّ وهنمشي خطوة خطوة قدّامك.') }}" />
            </div>
        @endif

        @if ($done)
            <a href="{{ route('setup.platform') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color:#04201c">{{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.text_1', 'كمّل لبيانات المنصّة') }}</a>
        @else
            <form method="post" action="{{ route('setup.migrate.run') }}" data-setup-progress>
                @csrf

                {{-- ردّ فوريّ لكلّ فعل (2.17-ب): الزرّ يتقفل وشريط التقدّم يشتغل --}}
                <div class="hidden mb-4" data-progress-bar>
                    <div class="h-2 w-full rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-2 w-1/2 rounded-full animate-pulse" style="background: var(--color-brand-500)"></div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.text_2', 'بنجهّز الجداول… ممكن ياخد لحدّ دقيقة. سيب الصفحة مفتوحة.') }}
                    </p>
                </div>

                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color:#04201c">{{ \App\Services\Setup\SetupSettings::text('setup.migrate_view.text_3', 'ابدأ التجهيز') }}</button>
            </form>

            <script>
                // بلا أيّ مكتبة خارجيّة: إظهار التقدّم ومنع الضغط المزدوج
                document.querySelectorAll('[data-setup-progress]').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        var bar = form.querySelector('[data-progress-bar]');
                        var button = form.querySelector('button');
                        if (bar) { bar.classList.remove('hidden'); }
                        if (button) { button.disabled = true; button.textContent = @json($hcWords['setup.migrate_view.js_1']); }
                    });
                });
            </script>
        @endif
    </div>
</div>
@endsection
