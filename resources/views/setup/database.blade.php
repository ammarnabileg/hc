@extends('layouts.guest')
@section('title', 'تنصيب المنصّة — قاعدة البيانات')

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="قاعدة البيانات"
            subtitle="انسخ البيانات من لوحة الاستضافة، وجرّب الاتّصال الأوّل — وبعدين نحفظ." />

        @include('setup.partials.alert', ['keys' => ['connection', 'setup']])

        <form method="post" class="space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="db_host" label="المضيف" dir="ltr" :value="$draft['db_host']" required
                              hint="غالبًا 127.0.0.1 على نفس الخادم." />
                <x-form.input name="db_port" label="المنفذ" type="number" dir="ltr" :value="$draft['db_port']" required
                              hint="الافتراضيّ 3306." />
            </div>

            <x-form.input name="db_database" label="اسم قاعدة البيانات" dir="ltr" :value="$draft['db_database']" required />
            <x-form.input name="db_username" label="مستخدم قاعدة البيانات" dir="ltr" :value="$draft['db_username']" required />
            <x-form.input name="db_password" type="password" label="كلمة سرّ قاعدة البيانات" dir="ltr" :value="$draft['db_password']"
                          hint="سيبها فاضية لو المستخدم بلا كلمة سرّ." />

            <div class="flex items-center gap-2 flex-wrap">
                {{-- الاختبار قبل الحفظ: مش هنكتب حاجة قبل ما نتأكّد إنّها شغّالة --}}
                <button formaction="{{ route('setup.database.test') }}"
                        class="btn rounded-xl px-4 py-2 text-sm inline-flex items-center motion-standard"
                        style="border: 1px solid var(--border)">اختبار الاتّصال</button>

                <button formaction="{{ route('setup.database.store') }}"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color:#04201c">احفظ وكمّل</button>

                @if ($verified)
                    <x-state-badge state="ok" label="الاتّصال متجرَّب" />
                @else
                    <x-state-badge state="idle" label="لسّه ماتجرّبش" />
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
