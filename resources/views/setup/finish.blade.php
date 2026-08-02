@extends('layouts.guest')
@section('title', 'تنصيب المنصّة — الإنهاء')

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="فاضل ضغطة واحدة"
            subtitle="هنولّد مفتاح أمان جديد ونقفل صفحة التنصيب نهائيًّا." />

        @include('setup.partials.alert', ['keys' => ['finish', 'setup']])

        <ul class="space-y-2 mb-5">
            @foreach ($summary as $label => $value)
                <li class="flex items-center justify-between gap-3 rounded-xl p-3 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border)">
                    <span style="color: var(--text-muted)">{{ $label }}</span>
                    <span class="font-semibold">{{ $value !== '' ? $value : '—' }}</span>
                </li>
            @endforeach
        </ul>

        <p class="text-sm mb-4" style="color: var(--text-muted)">
            بعد الضغط هتتقفل كلّ صفحات التنصيب، ومحدّش هيقدر يفتحها تاني — لا أنت ولا غيرك.
        </p>

        <form method="post" action="{{ route('setup.finish.install') }}" data-setup-progress>
            @csrf

            <div class="hidden mb-4" data-progress-bar>
                <div class="h-2 w-full rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                    <div class="h-2 w-1/2 rounded-full animate-pulse" style="background: var(--color-brand-500)"></div>
                </div>
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c">أنهِ التنصيب</button>
        </form>

        <script>
            // ردّ فوريّ ومنع الضغط المزدوج (2.17-ب) — بلا أيّ مكتبة خارجيّة
            document.querySelectorAll('[data-setup-progress]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    var bar = form.querySelector('[data-progress-bar]');
                    var button = form.querySelector('button');
                    if (bar) { bar.classList.remove('hidden'); }
                    if (button) { button.disabled = true; button.textContent = 'بنقفل التنصيب…'; }
                });
            });
        </script>
    </div>
</div>
@endsection
