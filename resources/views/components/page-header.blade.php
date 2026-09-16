@props(['title' => '', 'subtitle' => null, 'action' => null, 'breadcrumbs' => [], 'pinnable' => true, 'eyebrow' => null])

@php
    /*
     | ⭐ الـBreadcrumb وزرّ التثبيت وسويتش «وضع متقدّم» ليسوا هنا: المرجع يضعهم
     | في الـTopbar (`#topbar`: Breadcrumb على جهة البداية و`.top-actions` على
     | جهة النهاية). فالمكوّن يشارك بيانات الصفحة مع `partials/header` — قالب
     | الصفحة يُنفَّذ قبل الـLayout فيراها الهيدر — ويرسم `.page-head` وحدها.
     */
    view()->share('hcPage', ['title' => $title, 'breadcrumbs' => $breadcrumbs, 'pinnable' => $pinnable]);
@endphp

{{--
  ⭐ تقسيمة `.page-head` حرفيًّا من ملف الهويّة المرجعيّ (`head()` في app.js):
  عنوان علويّ (eyebrow) + H1 + سطر فرعيّ على جهة، وفعل رئيسيّ واحد على الجهة
  الأخرى.
--}}
<header class="page-head mb-2">
    <div class="min-w-0">
        {{--
          ⭐ العنوان العلويّ (eyebrow) زحمة مكرّرة حين يظهر فوق Breadcrumb
          أصلًا موجود في كلّ شاشة داخليّة (2.15-د) — الاثنان يجاوبان نفس
          سؤال «أنا فين؟». ولأنّ ولا شاشة تمرّر eyebrow مخصَّصًا (القيمة
          الافتراضيّة «رحلتك التعليمية» دايمًا)، يظهر فقط حين لا يوجد
          Breadcrumb (كلوحة القيادة نفسها)، أو حين يُمرَّر صراحةً.
        --}}
        @if ($eyebrow || ! $breadcrumbs)
            <span class="eyebrow">{{ $eyebrow ?? setting('ux.page_head.eyebrow', 'رحلتك التعليمية') }}</span>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>

    {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
    @if ($action)
        <div class="cluster">{{ $action }}</div>
    @endif
</header>
