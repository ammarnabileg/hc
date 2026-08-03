@extends('layouts.guest')
@section('title', setting('onboarding.identity.title', 'بيانات الشهادات والإفادات'))

@php
    /**
     * 🎓 **2.5-ج — صفحة المعلومات (بيانات الشهادات والإفادات)** — الشاشة الثانية.
     *
     * النصّ الحاكم حرفيًّا وبترتيبه:
     *  «**اللقب (Select مجمّع بمجموعات):** *ألقاب عامة* … *ألقاب مهنية* … *ألقاب أكاديمية* …»
     *  «**الاسم بالعربي (ثلاثي):** تحقّق أنه **عربي**؛ رسالة خطأ + **لا يُفعَّل زر
     *   الاستكمال** حتى يصحّح.»
     *  «**الاسم بالإنجليزي (ثلاثي):** تحقّق أنه **إنجليزي**؛ نفس المعالجة.»
     *  «**النوع:** ذكر / أنثى (برسوم أفاتار).»
     *  «**الدولة:** Select (نضيفه وإن لم يظهر في الاسكرين).»
     *  «**المحافظة:** Select **مبني على الدولة**، يُملأ **تلقائيًّا** (مش يدويًا من
     *   الأدمن). مطلوب **كل دول العالم ومحافظاتها كاملة**.»
     *  «**العنوان الفرعي:** Input عادي (المنطقة والشارع).»
     *  «**زر الاستكمال معطّل** حتى تكتمل **كل** البيانات. وعند الإتمام ⇒ **احتفال قوي 🎉**.»
     *
     * وهذا الترتيب هو ترتيب الحقول أدناه حرفيًّا — لا زيادة ولا نقصان ولا تقديم.
     */
    $inputClass = 'w-full rounded-xl px-3 py-2 text-sm';
    $inputStyle = 'min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
@endphp

@section('content')
<div class="card p-6 w-full max-w-xl">
    <p class="text-xs mb-2" style="color: var(--text-muted)">
        {{ setting('onboarding.identity.step_label', 'خطوة 2 من 2 — بيانات الشهادة') }}
        · <span dir="ltr">{{ $account['email'] ?? '' }}</span> ✓
    </p>
    <h1 class="text-xl font-extrabold mb-1">{{ setting('onboarding.identity.title', 'بيانات الشهادات والإفادات') }}</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">
        {{ setting('onboarding.identity.subtitle', 'البيانات دي هي اللي بتطلع على شهاداتك وإفاداتك — اكتبها زيّ ما تحبّ تشوفها عليها.') }}
    </p>

    @if (session('status'))
        <x-toast :message="session('status')" state="warn" />
    @endif

    <form method="post" action="{{ route('register') }}" class="space-y-3" data-identity-form>
        @csrf
        <input type="hidden" name="step" value="{{ \App\Http\Controllers\Auth\AuthController::STEP_IDENTITY }}">
        <input type="hidden" name="offer" value="{{ $referral }}">

        {{-- 1) اللقب: Select **مجمَّع بمجموعات** (عامّة · مهنيّة · أكاديميّة) --}}
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

        {{-- 2) الاسم بالعربيّ (ثلاثيّ) · 3) الاسم بالإنجليزيّ (ثلاثيّ) --}}
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

        {{-- 4) النوع: خياران **برسوم أفاتار** — SVG مرسوم لا مكتبة أيقونات --}}
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

        {{-- 5) الدولة · 6) المحافظة **مبنيّة على الدولة وتُملأ تلقائيًّا** --}}
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

            <label class="block">
                <span class="block text-sm mb-1">المحافظة</span>
                {{--
                    ⚠️ **لا تُطبَع 5,249 محافظة في الصفحة.** كانت تُطبَع كلّها وقتما
                    كانت محافظةً واحدة؛ ومع بيانات المصدر الكاملة صار ذلك مئاتِ
                    الكيلوبايتات على هاتفٍ في 375px — تمنعه 2.7 صراحةً. فتُجلَب
                    محافظات **الدولة المختارة وحدها** من نفس المسار (`?governorates=`).
                --}}
                <select name="governorate_id" class="{{ $inputClass }}" style="{{ $inputStyle }}"
                        data-governorate data-required-field data-url="{{ route('register') }}"
                        @disabled(! old('country_id'))>
                    <option value="">{{ old('country_id') ? 'اختر المحافظة' : 'اختر الدولة الأوّل' }}</option>
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate['id'] }}"
                                @selected((int) old('governorate_id') === $governorate['id'])>{{ $governorate['name'] }}</option>
                    @endforeach
                </select>
                @error('governorate_id')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
            </label>
        </div>

        {{-- 7) العنوان الفرعيّ: Input عاديّ (المنطقة والشارع) --}}
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('onboarding.identity.address_label', 'العنوان الفرعيّ (المنطقة والشارع)') }}</span>
            <input type="text" name="address_line" value="{{ old('address_line') }}"
                   class="{{ $inputClass }}" style="{{ $inputStyle }}" required data-required-field>
            @error('address_line')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">◉ {{ $message }}</span>@enderror
        </label>

        {{-- 8) زرّ الاستكمال **معطَّل حتى تكتمل كلّ البيانات** — واحتفال قويّ عند الإتمام --}}
        <button data-submit class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color:#04201c">
            {{ setting('onboarding.identity.submit_label', 'استكمال التسجيل') }}
        </button>
    </form>

    <p class="text-sm mt-4" style="color: var(--text-muted)">
        عايز تعدّل بريدك أو رقمك؟
        <a href="{{ route('register') }}?back=1" data-restart style="color: var(--color-brand-500)">ارجع للخطوة الأولى</a>
    </p>

    {{-- ⚖️ إسناد ODbL **حيث تُستهلَك البيانات** — الدول والمحافظات أعلاه (2.5-ج) --}}
    @include('auth.register.partials.attribution')
</div>

{{-- 🎉 «وعند الإتمام ⇒ **احتفال قوي**» (2.5-ج) — المستوى 3 من مصدر الاحتفالات الواحد (2.14) --}}
<div data-celebration hidden>
    @include('onboarding.partials.celebration', ['celebration' => ['tier' => 3, 'sound' => false, 'sound_path' => null]])
</div>

<script>
    (() => {
        const form = document.querySelector('[data-identity-form]');
        if (!form) return;

        const submit = form.querySelector('[data-submit]');
        const country = form.querySelector('[data-country]');
        const governorate = form.querySelector('[data-governorate]');
        const stage = document.querySelector('[data-celebration]');
        const scripts = {
            arabic: /^[؀-ۿ\sـ]+$/,
            latin: /^[A-Za-z\s'\-.]+$/,
        };

        // المحافظة **تُملأ تلقائيًّا من الدولة** (2.5-ج) — بجلبٍ صغير لا بقائمةٍ كاملة
        const loadGovernorates = async () => {
            if (!country || !governorate) return;

            const id = country.value;
            governorate.innerHTML = '';
            governorate.disabled = true;

            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = id ? '…' : 'اختر الدولة الأوّل';
            governorate.appendChild(blank);

            if (!id) { check(); return; }

            try {
                const res = await fetch(governorate.dataset.url + '?governorates=' + encodeURIComponent(id),
                    { headers: { Accept: 'application/json' } });
                const data = await res.json();

                blank.textContent = 'اختر المحافظة';
                (data.governorates || []).forEach((row) => {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.name;
                    governorate.appendChild(option);
                });
                governorate.disabled = false;
            } catch (_) {
                blank.textContent = 'تعذّر تحميل المحافظات — جرّب تاني';
            }

            check();
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

        // الاحتفال **لحظة الإتمام** لا بعد صفحةٍ كاملة (2.5-ج · 2.9 Peak-End)
        let celebrated = false;
        form.addEventListener('submit', (event) => {
            if (celebrated || !stage || submit.disabled) return;
            event.preventDefault();
            celebrated = true;
            stage.hidden = false;
            setTimeout(() => form.submit(), {{ max(0, (int) setting('celebrations.hold_ms', 900)) }});
        });

        country?.addEventListener('change', loadGovernorates);
        form.addEventListener('input', check);
        form.addEventListener('change', check);

        check();
    })();
</script>
@endsection
