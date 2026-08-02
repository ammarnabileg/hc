@extends('layouts.guest')
@section('title', 'تنصيب المنصّة — توكن التنصيب')

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="أهلًا بيك — نبدأ التنصيب"
            subtitle="خطوة أمان أولى: أثبت إنّ الخادم ده بتاعك قبل ما نفتح المعالج." />

        @include('setup.partials.alert', ['keys' => ['token']])

        @if ($unwritable)
            {{-- بلا توكن مفيش أمان: نقول المشكلة والحلّ بالظبط (2.17-ب) --}}
            <div class="rounded-xl p-3 mb-4 text-sm flex items-start gap-2"
                 style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent);
                        border: 1px solid var(--color-state-danger)">
                <span aria-hidden="true">◉</span>
                <span>
                    مقدرناش ننشئ ملفّ التوكن <span dir="ltr">{{ $tokenFile }}</span>.
                    من مدير الملفّات في الاستضافة اضبط صلاحيّة مجلّد <span dir="ltr">storage</span> على 775، وبعدين حدّث الصفحة.
                </span>
            </div>
        @elseif ($visibleToken)
            {{-- وضع التطوير فقط: نعرض التوكن على الشاشة توفيرًا للوقت --}}
            <div class="rounded-xl p-3 mb-4 text-sm"
                 style="background: var(--surface-sunken); border: 1px solid var(--border)">
                <p class="mb-2" style="color: var(--text-muted)">
                    وضع التطوير مفتوح، فالتوكن قدّامك:
                </p>
                <code class="block text-sm font-mono select-all" dir="ltr">{{ $visibleToken }}</code>
            </div>
        @else
            <p class="text-sm mb-4" style="color: var(--text-muted)">
                افتح الملفّ <span dir="ltr" class="font-mono">{{ $tokenFile }}</span> من مدير الملفّات في الاستضافة،
                وانسخ السطر اللي جوّاه هنا.
            </p>
        @endif

        <form method="post" action="{{ route('setup.token.verify') }}" class="space-y-4">
            @csrf
            <x-form.input
                name="token"
                label="توكن التنصيب"
                dir="ltr"
                autocomplete="off"
                autofocus
                required
                hint="التوكن اتولّد لوحده عند أوّل فتح للصفحة." />

            <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c">ادخل المعالج</button>
        </form>
    </div>
</div>
@endsection
