@php
    /**
     * ⭐ زرّ عائم «أكمل من حيث توقفت» (3.4-15).
     *
     * لماذا عائم لا سطر في القائمة؟ لأنّ الاحتكاك يُوجَّه للمسار الضارّ ويُزال عن
     * المسار الصحيح (2.9-11): استئناف التعلّم يجب أن يكون **بضغطة واحدة** من أيّ
     * موضع في الصفحة. ولا يظهر إطلاقًا إن لم يكن هناك درسٌ متاحٌ فعلًا — فزرٌّ
     * يقود إلى جدار مقفول أسوأ من غيابه (2.17).
     *
     * والموضع فوق شريط الفعل الرئيسيّ على الموبايل كي لا يزاحمه (2.15-ج).
     *
     * ويحسب نقطته بنفسه إن لم تُمرَّر، فيكفي سطرُ `@include` واحد في أيّ شاشة
     * بلا تعديل الكنترولر الذي يملكه مجالٌ آخر.
     */
    $resume = $resume ?? (auth()->check()
        ? app(App\Services\Learning\ProgressService::class)->resumePoint(auth()->user())
        : null);
@endphp

@if (! empty($resume))
    <a href="{{ route('learning.lesson', [$resume['course'], $resume['lesson_id']]) }}"
       class="btn fixed z-40 inline-flex items-center gap-2 rounded-full px-4 py-3 text-sm font-semibold motion-standard shadow-lg"
       style="inset-inline-end: 1rem; inset-block-end: 5.5rem; background: var(--color-brand-500); color: #04201c; min-block-size: 44px"
       title="{{ $resume['course']->name_ar }} — {{ $resume['title'] }}">
        <x-icon name="lesson" size="18" />
        <span>{{ setting('learning.cta.resume_where_left', 'أكمل من حيث توقفت') }}</span>
    </a>
@endif
