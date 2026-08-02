@props(['action' => null, 'screen' => null, 'saveable' => true])

{{--
  ثلاثة فلاتر ظاهرة + بحث، والباقي خلف «فلاتر متقدّمة» مطويّة (2.15-أ-4).
  وعلى الموبايل: زرّ واحد يفتح Bottom Sheet بعدّاد الفلاتر المفعَّلة.
  ⭐ وفوقها **رقائق العروض المحفوظة** — تركيبة الفلاتر تُستدعى بضغطة (2.15-د).
--}}
@if ($saveable && auth()->check())
    <x-saved-views :screen="$screen" />
@endif

<form method="get" action="{{ $action }}" class="card p-3 mb-4">
    <div class="flex flex-wrap items-end gap-3">{{ $slot }}</div>

    @isset($advanced)
        <details class="mt-3">
            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">فلاتر متقدّمة</summary>
            <div class="flex flex-wrap items-end gap-3 mt-3">{{ $advanced }}</div>
        </details>
    @endisset
</form>
