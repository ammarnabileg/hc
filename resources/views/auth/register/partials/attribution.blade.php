{{--
    ⚖️ **إسناد ODbL v1.0 حيث تُستهلَك البيانات** (2.5-ج).

    النصّ: «مصدر الداتا (مُعتمَد): ريبو GitHub **dr5hn/countries-states-cities-database**
    … الرخصة **ODbL v1.0** ⇒ نضيف **إسنادًا (Attribution)** وقت البناء.»

    وكان الإسناد في **تاب الإعدادات وحده** — شاشةٌ لا يراها إلّا الأدمن، بينما
    البيانات تُستهلَك في **صفحة التسجيل العامّة** (أكواد الدول بالأعلام · قائمة
    الدول · قائمة المحافظات). والرخصة تشترط الإسناد على كلّ عملٍ **يُعرَض
    للجمهور** مشتقٍّ من قاعدتها — فمكانه هنا، عند نقطة الاستهلاك نفسها.

    والنصّ والرابط **إعدادان** (2.13) يملكهما المالك من تاب «بيانات الدول».
--}}
@php
    $attribution = trim((string) setting('countries.attribution', 'بيانات الدول والمحافظات من dr5hn/countries-states-cities-database — برخصة ODbL v1.0.'));
    $sourceUrl = trim((string) setting('countries.source_url', 'https://github.com/dr5hn/countries-states-cities-database'));
@endphp

@if ($attribution !== '')
    <p class="text-xs mt-5 pt-3" data-odbl-attribution
       style="color: var(--text-muted); border-top: 1px solid var(--border); line-height: 1.7">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block; vertical-align:-1px">
            <circle cx="12" cy="12" r="9" /><path d="M12 11v5" /><path d="M12 7.6v.6" />
        </svg>
        {{ $attribution }}
        @if ($sourceUrl !== '')
            <a href="{{ $sourceUrl }}" rel="noopener noreferrer nofollow" target="_blank"
               style="color: var(--color-brand-500)">{{ setting('countries.attribution_link_label', 'المصدر') }}</a>
        @endif
    </p>
@endif
