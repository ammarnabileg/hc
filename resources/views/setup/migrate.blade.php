@extends('layouts.guest')
@section('title', 'تنصيب المنصّة — تجهيز الجداول')

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="تجهيز الجداول والبيانات"
            subtitle="هنبني جداول المنصّة ونحمّل الأدوار والصلاحيّات والإعدادات — كلّه من هنا بلا تيرمينال." />

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
                            <x-state-badge :state="$line['ok'] ? 'ok' : 'danger'" :label="$line['ok'] ? 'تمّ' : 'وقف'" />
                        </div>
                        @if (! $line['ok'] && $line['output'])
                            <pre class="text-xs mt-2 overflow-x-auto" dir="ltr" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($line['output'], 600) }}</pre>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mb-5">
                <x-empty message="لسّه ماشغّلناش التجهيز — اضغط الزرّ وهنمشي خطوة خطوة قدّامك." />
            </div>
        @endif

        @if ($done)
            <a href="{{ route('setup.platform') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color:#04201c">كمّل لبيانات المنصّة</a>
        @else
            <form method="post" action="{{ route('setup.migrate.run') }}" data-setup-progress>
                @csrf

                {{-- ردّ فوريّ لكلّ فعل (2.17-ب): الزرّ يتقفل وشريط التقدّم يشتغل --}}
                <div class="hidden mb-4" data-progress-bar>
                    <div class="h-2 w-full rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-2 w-1/2 rounded-full animate-pulse" style="background: var(--color-brand-500)"></div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        بنجهّز الجداول… ممكن ياخد لحدّ دقيقة. سيب الصفحة مفتوحة.
                    </p>
                </div>

                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color:#04201c">ابدأ التجهيز</button>
            </form>

            <script>
                // بلا أيّ مكتبة خارجيّة: إظهار التقدّم ومنع الضغط المزدوج
                document.querySelectorAll('[data-setup-progress]').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        var bar = form.querySelector('[data-progress-bar]');
                        var button = form.querySelector('button');
                        if (bar) { bar.classList.remove('hidden'); }
                        if (button) { button.disabled = true; button.textContent = 'بنجهّز…'; }
                    });
                });
            </script>
        @endif
    </div>
</div>
@endsection
