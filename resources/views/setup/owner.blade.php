@extends('layouts.guest')
@section('title', (string) setting('setup.owner_view.section_1', 'تنصيب المنصّة — حساب مالك المنصّة'))

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="{{ setting('setup.owner_view.title_1', 'حساب مالك المنصّة') }}"
            subtitle="{{ setting('setup.owner_view.subtitle_1', 'ده حسابك أنت: بيتفعّل على طول وبياخد أعلى صلاحيّة في المنصّة.') }}" />

        @include('setup.partials.alert', ['keys' => ['setup']])

        <form method="post" action="{{ route('setup.owner.store') }}" class="space-y-4">
            @csrf

            <x-form.input name="name" label="{{ setting('setup.owner_view.label_1', 'الاسم') }}" :value="$draft['name']" required
                          hint="{{ setting('setup.owner_view.hint_1', 'الاسم اللي هيظهر لك ولزمايلك جوّه المنصّة.') }}" />

            <x-form.input name="email" type="email" label="{{ setting('setup.owner_view.label_2', 'البريد') }}" dir="ltr" :value="$draft['email']" required
                          hint="{{ setting('setup.owner_view.hint_2', 'هتدخل بيه، وعليه هتوصلك تنبيهات المنصّة.') }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="password" type="password" label="{{ setting('setup.owner_view.label_4', 'كلمة السرّ') }}" required
                              hint="8 {{ setting('setup.owner_view.hint_4', 'حروف على الأقلّ.') }}" />
                <x-form.input name="password_confirmation" type="password" label="{{ setting('setup.owner_view.label_5', 'تأكيد كلمة السرّ') }}" required />
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c">{{ setting('setup.owner_view.text_1', 'أنشئ الحساب وكمّل') }}</button>
        </form>
    </div>
</div>
@endsection
