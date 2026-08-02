@props(['action' => null, 'screen' => null, 'saveable' => true])

@php
    /*
     | ثلاثة فلاتر ظاهرة + بحث، والباقي **مطويّ لا محذوف** (2.15-أ-4).
     |
     | الحدّ نفسه إعدادٌ (`ux.filters.max_visible`) لا رقم محروق (2.13)، وسويتش
     | «وضع متقدّم» أعلى الصفحة يفتح المخفيّ كلّه — فالبساطة إخفاء وتدرّج لا
     | تقليل (2.15). قبل ذلك كان الإعداد بلا قارئ، فصار الإخفاء **حذفًا**.
     |
     | التنفيذ بـCSS خالص على ترتيب أبناء الصفّ: ما بعد الحدّ يُخفى **إلّا**
     | الأزرار (زرّ التطبيق يبقى دائمًا في متناول اليد)، وقيم الحقول المخفيّة
     | تُرسَل كما هي فلا يضيع «عرض محفوظ» بُني على فلترٍ مطويّ.
     */
    $isAdvancedMode = advanced_mode();
    $maxVisible = view_mode()->maxVisibleFilters();
@endphp

{{--
  ⭐ وفوق الفلاتر **رقائق العروض المحفوظة** — تركيبة الفلاتر تُستدعى بضغطة (2.15-د).
  وعلى الموبايل: الصفّ يلتفّ ولا يتمرّر أفقيًّا (2.15-ج).
--}}
@if ($saveable && auth()->check())
    <x-saved-views :screen="$screen" />
@endif

<form method="get" action="{{ $action }}" class="card p-3 mb-4" data-filters
      data-filters-mode="{{ $isAdvancedMode ? 'advanced' : 'simple' }}">
    <div class="flex flex-wrap items-end gap-3"
         @if (! $isAdvancedMode) data-filters-cap="{{ $maxVisible }}" @endif>{{ $slot }}</div>

    @isset($advanced)
        <details class="mt-3" @if ($isAdvancedMode) open @endif>
            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">فلاتر متقدّمة</summary>
            <div class="flex flex-wrap items-end gap-3 mt-3">{{ $advanced }}</div>
        </details>
    @endisset
</form>
