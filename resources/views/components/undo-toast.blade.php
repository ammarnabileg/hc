@props([])

@php
    // مدّة التراجع إعداد لا رقم محروق (2.13 · 2.15-د)
    $seconds = max(1, (int) setting('ux.undo.seconds', 5));
@endphp

{{--
  ⭐ التراجع خلال ثوانٍ بعد الأفعال القابلة للتراجع (2.15-د):
  «ما عدا الأفعال غير القابلة للتراجع يُنفَّذ فورًا مع Undo في الـToast».
  أيّ شاشة تُطلق `ui:undoable` بـ{token, message} فيظهر الشريط هنا.
--}}
<div data-undo-host data-undo-seconds="{{ $seconds }}"
     class="fixed z-[70] hidden items-center gap-3 rounded-xl px-4 py-3 text-sm animate-fadeup"
     style="inset-inline-start: 1rem; inset-block-end: 1rem; background: var(--surface-raised);
            border: 1px solid var(--border); color: var(--text); box-shadow: 0 8px 24px rgb(0 0 0 / .3)"
     role="status" aria-live="polite">
    <span data-undo-message></span>

    <button type="button" data-undo-action
            class="btn rounded-lg px-3 font-semibold motion-standard"
            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
        تراجع (<span data-undo-countdown>{{ $seconds }}</span>)
    </button>
</div>
