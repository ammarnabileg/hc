@php
    /*
     | ⭐ سويتش «وضع متقدّم» — **في كلّ صفحة** ويُحفَظ لكلّ مستخدم (2.15-أ-9).
     |
     | لماذا هنا (داخل `x-page-header`)؟ لأنّ 2.15 كلّها قائمة على أنّ البساطة
     | «إخفاء وتدرّج **لا تقليل**»: العمق موجود دائمًا خلف **خطوة واحدة**.
     | فلو غاب السويتش صار الإخفاء حذفًا وانقلبت القاعدة على نفسها.
     |
     | يعمل بلا جافاسكربت (فورم POST) — تحسينٌ تدريجيّ (2.1)، وعلى الموبايل
     | يفتح كصفحة كاملة لأنّ التبديل يعيد تحميل الصفحة نفسها (2.15-ج).
     | الشكل من 2.10.1-10: مسار 40×22 والإبهام 16px ينزلق بـ`inset-inline-start`.
     */
    $mode = view_mode();
    $viewer = auth()->user();
    $show = $mode->available($viewer);
    $on = $mode->isAdvanced($viewer);
    $label = $mode->label();
@endphp

@if ($show)
    <form method="post" action="{{ route('ui.mode.toggle') }}" class="shrink-0">
        @csrf
        <input type="hidden" name="back" value="{{ request()->fullUrl() }}">
        <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}"
                data-advanced-toggle="{{ $on ? '1' : '0' }}"
                title="{{ $on ? 'اقفل الوضع المتقدّم' : 'افتح كلّ اللي اتخفى' }}"
                class="inline-flex items-center gap-2 rounded-full px-3 text-xs motion-standard"
                style="min-height: 44px; color: {{ $on ? 'var(--color-brand-500)' : 'var(--text-muted)' }}">
            <span>{{ $label }}</span>
            <span class="relative inline-block rounded-full motion-standard"
                  style="width: 40px; height: 22px; background: {{ $on ? 'var(--color-brand-500)' : 'rgb(255 255 255 / .15)' }}"
                  aria-hidden="true">
                <span class="absolute rounded-full motion-standard"
                      style="width: 16px; height: 16px; top: 3px; background: #fff;
                             inset-inline-start: {{ $on ? '21px' : '3px' }}"></span>
            </span>
        </button>
    </form>
@endif
