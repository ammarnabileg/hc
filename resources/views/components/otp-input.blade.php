@props(['length' => 4, 'name' => 'code', 'label' => null, 'autofocus' => false, 'required' => false])

@php
    $length = max(1, (int) $length);
    $label = $label ?? setting('ux.otp.aria', 'رمز التحقّق');
    $boxAria = (string) setting('ux.otp.box_aria', 'الرقم :n من :total');
@endphp

{{--
  رمز التحقّق كخاناتٍ منفصلة (`.otp` في ملف الهويّة): انتقال تلقائيّ بين الخانات،
  Backspace يرجع خانة، واللصق أو تعبئة النظام (one-time-code) توزّع الأرقام كلّها
  (resources/js/app.js). وبلا جافاسكربت يبقى حقلٌ واحد عاديّ يعمل كاملًا:
  الخانات مخفيّة حتى يفعّلها السكربت ويحوّل الحقل الواحد إلى مخفيّ يحمل القيمة.
  السكربتات الحاليّة للشاشات تقرأ `[data-otp-input]` كما هي (حدث input يُبثّ عند كلّ تغيير).
--}}
<div class="otp-field" data-otp-field @if ($autofocus) data-autofocus @endif dir="ltr">
    <input type="text" name="{{ $name }}" inputmode="numeric" autocomplete="one-time-code"
           maxlength="{{ $length }}" pattern="[0-9]{{ '{'.$length.'}' }}" value="{{ old($name) }}"
           data-otp-input data-length="{{ $length }}" aria-label="{{ $label }}" @if ($required) required @endif
           class="w-full rounded-xl px-3 py-3 text-center font-extrabold tabular-nums"
           style="min-height: 54px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); font-size: 1.5rem; letter-spacing: .5rem">

    <div class="otp-row" role="group" aria-label="{{ $label }}" data-otp-row hidden>
        @for ($i = 1; $i <= $length; $i++)
            <input type="text" class="otp-box" inputmode="numeric" maxlength="1"
                   autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}" data-otp-box
                   aria-label="{{ strtr($boxAria, [':n' => $i, ':total' => $length]) }}">
        @endfor
    </div>
</div>
