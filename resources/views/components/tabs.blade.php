@props(['tabs' => [], 'current' => null])

{{--
  تابات بخطٍّ سفليّ أحمر — حرفيًّا من ملف الهويّة المرجعيّ (`.tabs`، لا رقائق
  Pill ملوّنة). Sticky تحت الهيدر بـ12px، وعلى الموبايل شريط أفقيّ متمرّر
  بلا Scrollbar ظاهر (2.15-ج) — والصنف `.tabs` نفسه من `app.css`.
--}}
<div class="min-w-0 sticky-bar -mx-4 md:mx-0 px-4 md:px-0" style="background: var(--surface)">
    <div class="tabs">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] ?? '#' }}"
               @if ($current === ($tab['key'] ?? null)) aria-current="page" @endif>
                {{ $tab['label'] }}
                {{-- isset لا empty: empty(0) === true فيُخفي عدّاد الصفر رغم أنّ 0 عددٌ صحيحٌ مقصود (20.1: «كلٌّ برقمه» دومًا) --}}
                @if (isset($tab['count']))
                    <span class="opacity-70">({{ $tab['count'] }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
