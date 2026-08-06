@extends('layouts.guest')
@section('title', (string) setting('setup.database_view.section_1', 'تنصيب المنصّة — قاعدة البيانات'))

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="{{ setting('setup.database_view.title_1', 'قاعدة البيانات') }}"
            subtitle="{{ setting('setup.database_view.subtitle_1', 'انسخ البيانات من لوحة الاستضافة، وجرّب الاتّصال الأوّل — وبعدين نحفظ.') }}" />

        @include('setup.partials.alert', ['keys' => ['connection', 'setup']])

        <form method="post" class="space-y-4">
            @csrf

            <x-form.input name="db_database" label="{{ setting('setup.database_view.label_3', 'اسم قاعدة البيانات') }}" dir="ltr" :value="$draft['db_database']" required />
            <x-form.input name="db_username" label="{{ setting('setup.database_view.label_4', 'مستخدم قاعدة البيانات') }}" dir="ltr" :value="$draft['db_username']" required />
            <x-form.input name="db_password" type="password" label="{{ setting('setup.database_view.label_5', 'كلمة سرّ قاعدة البيانات') }}" dir="ltr" :value="$draft['db_password']"
                          hint="{{ setting('setup.database_view.hint_3', 'سيبها فاضية لو المستخدم بلا كلمة سرّ.') }}" />

            <div class="flex items-center gap-2 flex-wrap">
                {{-- الاختبار قبل الحفظ: مش هنكتب حاجة قبل ما نتأكّد إنّها شغّالة --}}
                <button formaction="{{ route('setup.database.test') }}"
                        class="btn rounded-xl px-4 py-2 text-sm inline-flex items-center motion-standard"
                        style="border: 1px solid var(--border)">{{ setting('setup.database_view.text_1', 'اختبار الاتّصال') }}</button>

                <button formaction="{{ route('setup.database.store') }}"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color:#04201c">{{ setting('setup.database_view.text_2', 'احفظ وكمّل') }}</button>

                @if ($verified)
                    <x-state-badge state="ok" label="{{ setting('setup.database_view.label_6', 'الاتّصال متجرَّب') }}" />
                @else
                    <x-state-badge state="idle" label="{{ setting('setup.database_view.label_7', 'لسّه ماتجرّبش') }}" />
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
