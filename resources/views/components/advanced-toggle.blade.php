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
     |
     | ⭐ الشكل صار من **مكوّن السويتش المشترك** (`.hc-switch` — 2.10.1-10) بدل
     | نسخةٍ مكتوبةٍ هنا بالأرقام: كانت 40 و22 و16 و3 و21 محروقةً في هذا الملفّ
     | وحده، فلو عدّلها المالك من «الهويّة والمظهر» تغيّر كلّ سويتشات المنصّة
     | **إلّا هذا**. والمصدر الواحد يمنع ذلك الانفصام.
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
                style="min-block-size: var(--touch-min, 44px); color: {{ $on ? 'var(--color-brand-500)' : 'var(--text-muted)' }}">
            <span>{{ $label }}</span>
            {{-- `aria-checked` على الزرّ أعلاه هو ما يقلب المسار (2.10.1-10) --}}
            <span class="hc-switch" aria-hidden="true"></span>
        </button>
    </form>
@endif
