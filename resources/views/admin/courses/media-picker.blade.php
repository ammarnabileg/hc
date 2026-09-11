@php
    /**
     * «اختَر من المكتبة / ارفع جديد» (12.4-د · 24.1) — المستدعى من أيّ حقل رفع.
     *
     * ⭐ **لماذا كان ميّتًا؟** لم تكن العلّة وصلةً ناقصة: الفيو بُني **صفحةً كاملة**
     * تمتدّ من `layouts.admin` وتنتهي بـ«اتنسخ المسار — الزقه في حقل الملفّ»،
     * والدستور يطلب **بوب-أب** لا صفحة: «بوب-أب **«اختَر من المكتبة / ارفع
     * جديد»** المستدعى من أيّ حقل رفع» (24.1 — مكتبة الوسائط) و«أيّ حقل رفع
     * (غلاف/مرفق/صورة سؤال) يفتح **«اختَر من المكتبة»** أو **«ارفع جديد»** —
     * يترفع مرّة ويُعاد استخدامه» (12.4-هـ). فصفحةٌ بالنسخ اليدويّ لا يمكن
     * وصلها بحقلٍ أصلًا — ولذلك بقيت بلا مستدعٍ.
     *
     * والعلاج: **نفس الفيو بوجهين** — صفحةً كاملة كما كان، وجزءًا عاريًا يُحقَن
     * في البوب-أب حين يُطلَب بـ`?fragment=1`. مصدر واحد لا نسختان تتباعدان.
     */
    $fragment = request()->boolean('fragment');
@endphp

@extends($fragment ? 'admin.courses.partials.bare' : 'layouts.admin')

@section('title', setting('media.picker.title'))

@section('content')
    @unless ($fragment)
        <x-page-header :title="setting('media.picker.title')" :subtitle="setting('media.picker.subtitle')" />
    @endunless

    @if ($multiple)
        {{-- ⭐ وضع الاختيار المتعدّد: مرفقات الدرس تُختار **بالبحث** لا من أوّل 12 ملفًّا --}}
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('media.picker.multi_hint') }}</p>
    @endif

    {{-- البحث: في وضع البوب-أب يعترضه الجافاسكربت فيحدّث الشبكة بلا مغادرة الفورم --}}
    <form method="get" class="mb-4" data-picker-search action="{{ route('admin.media.picker') }}">
        <input type="hidden" name="target" value="{{ $target }}">
        <input type="hidden" name="fragment" value="{{ $fragment ? 1 : 0 }}">
        {{-- الوضع يسافر مع البحث والترقيم — وإلّا انقلب المتعدّد مفردًا في الصفحة الثانية --}}
        <input type="hidden" name="multiple" value="{{ $multiple ? 1 : 0 }}">
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="{{ setting('media.picker.search_placeholder') }}"
               class="w-full md:w-80 rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </form>

    @if ($items->isEmpty())
        {{-- تمييز «المكتبة فاضية أصلًا» عن «الفلتر الحاليّ ما طابقش حاجة» — فلا تُعرَض
             رسالة «ارفع أوّل ملفّ» المضلّلة لمّا يكون السبب بحثًا نشطًا لا مكتبةً خاوية. --}}
        <x-empty :message="setting('media.picker.empty')" :action="setting('media.picker.open_library')" :href="route('admin.media.index')"
                 :filtered="$filters['q'] !== '' || $filters['kind'] !== '' || $filters['folder'] !== '' || $filters['tag'] !== '' || $filters['unused'] || $filters['date_from'] !== '' || $filters['date_to'] !== '' || $filters['size_min'] !== '' || $filters['size_max'] !== ''" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3" @if ($multiple) data-picker-multiple @endif>
            @foreach ($items as $item)
                {{-- الكارت يحمل مساره ورابطه: البوب-أب يملأ الحقل ويعرض المعاينة بلا طلبٍ ثانٍ --}}
                {{-- و`data-pick-id` للوضع المتعدّد: المرفق يُربَط بـ**آيدي** عنصر المكتبة لا بمساره --}}
                <button type="button" class="card p-3 text-start" data-pick="{{ $item->path }}"
                        data-pick-id="{{ $item->id }}"
                        data-pick-url="{{ \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($item->path) }}"
                        data-pick-name="{{ $item->name }}"
                        data-pick-image="{{ str_starts_with((string) $item->mime, 'image/') ? 1 : 0 }}"
                        style="min-height: 44px">
                    @if (str_starts_with((string) $item->mime, 'image/'))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->disk ?: 'public')->url($item->path) }}"
                             alt="{{ $item->name }}" loading="lazy" class="w-full h-24 object-cover rounded-lg mb-2">
                    @else
                        <div class="w-full h-24 rounded-lg mb-2 flex items-center justify-center text-2xl"
                             style="background: var(--surface-sunken)" aria-hidden="true"><x-icon name="document" size="16" /></div>
                    @endif
                    <div class="text-sm truncate">{{ $item->name }}</div>
                    @if ($multiple)
                        {{-- علامة الاختيار: يُظهرها الجافاسكربت على الكارت المحدَّد --}}
                        <span class="text-xs mt-1 hidden" data-pick-mark aria-hidden="true"
                              style="color: var(--color-brand-500)">✓</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="mt-4" data-picker-pagination>{{ $items->withQueryString()->links() }}</div>
    @endif

    @unless ($fragment)
        @include('admin.courses.partials.toast')
    @endunless
@endsection
