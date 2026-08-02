@extends('layouts.guest')
@section('title', 'تنصيب المنصّة — حساب مالك المنصّة')

@section('content')
<div class="w-full max-w-3xl">
    @include('setup.partials.stepper', ['steps' => $stepper])

    <div class="card p-6">
        <x-page-header
            title="حساب مالك المنصّة"
            subtitle="ده حسابك أنت: بيتفعّل على طول وبياخد أعلى صلاحيّة في المنصّة." />

        @include('setup.partials.alert', ['keys' => ['setup']])

        <form method="post" action="{{ route('setup.owner.store') }}" class="space-y-4">
            @csrf

            <x-form.input name="name" label="الاسم" :value="$draft['name']" required
                          hint="الاسم اللي هيظهر لك ولزمايلك جوّه المنصّة." />

            <x-form.input name="email" type="email" label="البريد" dir="ltr" :value="$draft['email']" required
                          hint="هتدخل بيه، وعليه هتوصلك تنبيهات المنصّة." />

            <x-form.input name="phone" label="رقم الموبايل" dir="ltr" :value="$draft['phone']" required
                          hint="بمفتاح الدولة، مثال: ‎+201000000000‎." />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="password" type="password" label="كلمة السرّ" required
                              hint="8 حروف على الأقلّ." />
                <x-form.input name="password_confirmation" type="password" label="تأكيد كلمة السرّ" required />
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c">أنشئ الحساب وكمّل</button>
        </form>
    </div>
</div>
@endsection
