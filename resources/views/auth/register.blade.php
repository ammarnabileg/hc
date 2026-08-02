@extends('layouts.guest')
@section('title', 'إنشاء حساب')

@php
    $inputStyle = 'min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';
@endphp

@section('content')
<div class="card p-6 w-full max-w-xl">
    <h1 class="text-xl font-extrabold mb-1">إنشاء حساب</h1>
    {{-- التفعيل مجّانيّ باعتماد إداريّ — لا رسوم ولا اشتراك (2.5-د) --}}
    <p class="text-sm mb-1" style="color: var(--text-muted)">التسجيل والتفعيل <strong>مجّانيّان بالكامل</strong>.</p>
    {{-- 2.5-ج: هذه الصفحة عنوانها في الدستور «بيانات الشهادات والإفادات» --}}
    <p class="text-xs mb-5" style="color: var(--text-muted)">
        البيانات دي هي اللي بتطلع على شهاداتك وإفاداتك — اكتبها زيّ ما تحبّ تشوفها عليها.
    </p>

    @if (session('referral_celebrate'))
        {{-- تفعيل هديّة الدعوة: صوت واحتفال (2.5-أ) — الصوت بتوجل المستخدم --}}
        @include('onboarding.partials.celebration', ['celebration' => ['tier' => 2, 'sound' => false, 'sound_path' => null]])
        <p class="rounded-xl p-3 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-state-ok) 14%, transparent)">
            ● {{ setting('onboarding.referral.success_text', 'تمام ✓ هديّتك اتفعّلت — كمّل تسجيلك.') }}
        </p>
    @endif

    <form method="post" action="{{ route('register') }}" class="space-y-3" data-register-form>
        @csrf
        <input type="hidden" name="offer" value="{{ $referral }}">

        {{-- اللقب: Select مجمَّع بمجموعات (عامّة · مهنيّة · أكاديميّة) --}}
        <label class="block">
            <span class="block text-sm mb-1">اللقب</span>
            <select name="title" class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
                <option value="">اختر اللقب</option>
                @foreach ($titles as $group => $options)
                    <optgroup label="{{ $group }}">
                        @foreach ($options as $option)
                            <option value="{{ $option }}" @selected(old('title') === $option)>{{ $option }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('title')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </label>

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">الاسم بالعربيّ (ثلاثيّ)</span>
                <input type="text" name="name_ar" value="{{ old('name_ar') }}" dir="rtl" lang="ar"
                       class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field
                       data-script="arabic">
                <span class="block text-xs mt-1" data-field-error style="color: var(--color-state-danger)">@error('name_ar')◉ {{ $message }}@enderror</span>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">الاسم بالإنجليزيّ (ثلاثيّ)</span>
                <input type="text" name="name_en" value="{{ old('name_en') }}" dir="ltr" lang="en"
                       class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field
                       data-script="latin">
                <span class="block text-xs mt-1" data-field-error style="color: var(--color-state-danger)">@error('name_en')◉ {{ $message }}@enderror</span>
            </label>
        </div>

        {{-- النوع: خياران برسمَين (أفاتار مرسوم SVG لا مكتبة أيقونات) --}}
        <fieldset>
            <legend class="block text-sm mb-1">النوع</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([['male', 'ذكر'], ['female', 'أنثى']] as [$value, $label])
                    <label class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm cursor-pointer"
                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border)">
                        <input type="radio" name="gender" value="{{ $value }}" @checked(old('gender') === $value) required>
                        <span aria-hidden="true" style="color: var(--color-brand-500)">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="3.4" />
                                @if ($value === 'male')
                                    <path d="M5.5 20a6.5 6.5 0 0 1 13 0" />
                                @else
                                    <path d="M6 20c.6-4 2.4-6 6-6s5.4 2 6 6" /><path d="M8.6 5.6C9.6 7 14.4 7 15.4 5.6" />
                                @endif
                            </svg>
                        </span>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('gender')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </fieldset>

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">الدولة</span>
                <select name="country_id" class="{{ $inputClass }}" style="{{ $inputStyle }}" data-country data-required-field>
                    <option value="">اختر الدولة</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((int) old('country_id') === $country->id)>{{ $country->name_ar }}</option>
                    @endforeach
                </select>
                @error('country_id')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>

            {{-- المحافظة **مبنيّة على الدولة وتُملأ تلقائيًّا** لا يدويًّا (2.5-ج) --}}
            <label class="block">
                <span class="block text-sm mb-1">المحافظة</span>
                <select name="governorate_id" class="{{ $inputClass }}" style="{{ $inputStyle }}" data-governorate data-required-field>
                    <option value="">اختر المحافظة</option>
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate->id }}" data-country-id="{{ $governorate->country_id }}"
                                @selected((int) old('governorate_id') === $governorate->id)>{{ $governorate->name_ar }}</option>
                    @endforeach
                </select>
                @error('governorate_id')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>
        </div>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('onboarding.identity.address_label', 'العنوان الفرعيّ (المنطقة والشارع)') }}</span>
            <input type="text" name="address_line" value="{{ old('address_line') }}"
                   class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
            @error('address_line')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </label>

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">البريد الإلكترونيّ</span>
                <input type="email" name="email" value="{{ old('email') }}" dir="ltr"
                       class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
                @error('email')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">رقم الموبايل</span>
                <input type="tel" name="phone" value="{{ old('phone') }}" dir="ltr"
                       class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
                @error('phone')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>
        </div>

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">كلمة السرّ</span>
                <input type="password" name="password" class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
                @error('password')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="block text-sm mb-1">تأكيد كلمة السرّ</span>
                <input type="password" name="password_confirmation" class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
            </label>
        </div>

        {{-- زرّ الاستكمال **معطَّل حتى تكتمل كلّ البيانات** (2.5-ج) --}}
        <button data-submit class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color:#04201c">إنشاء الحساب</button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        عندك حساب؟ <a href="{{ route('login') }}" style="color: var(--color-brand-500)">ادخل من هنا</a>
    </p>
</div>

<script>
    (() => {
        const form = document.querySelector('[data-register-form]');
        if (!form) return;

        const submit = form.querySelector('[data-submit]');
        const country = form.querySelector('[data-country]');
        const governorate = form.querySelector('[data-governorate]');
        const scripts = {
            arabic: /^[؀-ۿ\sـ]+$/,
            latin: /^[A-Za-z\s'\-.]+$/,
        };

        // المحافظة تُملأ تلقائيًّا من الدولة — ولا تُعرَض محافظةُ دولةٍ أخرى (2.5-ج)
        const syncGovernorates = () => {
            if (!country || !governorate) return;
            const id = country.value;
            let visible = 0;
            governorate.querySelectorAll('option[data-country-id]').forEach((option) => {
                const match = option.dataset.countryId === id;
                option.hidden = !match;
                option.disabled = !match;
                if (match) visible += 1;
                if (!match && option.selected) governorate.value = '';
            });
            governorate.disabled = visible === 0;
        };

        const check = () => {
            let ready = true;

            form.querySelectorAll('[data-required-field]').forEach((field) => {
                if (!field.value.trim()) ready = false;
            });

            if (!form.querySelector('input[name="gender"]:checked')) ready = false;

            // الاسمان: كلٌّ بحروف لغته وثلاثيًّا — والرسالة تظهر بجواره فورًا (2.17-ب)
            form.querySelectorAll('[data-script]').forEach((field) => {
                const note = field.parentElement.querySelector('[data-field-error]');
                const value = field.value.trim();
                let message = '';

                if (value && !scripts[field.dataset.script].test(value)) {
                    message = field.dataset.script === 'arabic'
                        ? @json(setting('onboarding.identity.name_ar_error', 'اكتب اسمك ثلاثيًّا بالعربيّ — الشهادة هتطلع بالاسم ده.'))
                        : @json(setting('onboarding.identity.name_en_error', 'اكتب اسمك ثلاثيًّا بالإنجليزيّ — النسخة الإنجليزيّة من الشهادة بتطلع بيه.'));
                } else if (value && value.split(/\s+/).length < {{ (int) setting('onboarding.identity.name_words', 3) }}) {
                    message = 'الاسم لازم يكون {{ (int) setting('onboarding.identity.name_words', 3) }} كلمات على الأقلّ.';
                }

                if (note) note.textContent = message ? '◉ ' + message : '';
                if (message) ready = false;
            });

            submit.disabled = !ready;
            submit.style.opacity = ready ? '1' : '.45';
        };

        country?.addEventListener('change', () => { syncGovernorates(); check(); });
        form.addEventListener('input', check);
        form.addEventListener('change', check);

        syncGovernorates();
        check();
    })();
</script>
@endsection
