@extends('layouts.app')

@section('title', setting('library.page.title', 'مكتبتي'))

@section('content')
    @php
        // تابات بعدّاداتها (20.1) — والرابط يحفظ بقيّة الفلاتر معه
        $query = request()->only(['q', 'type', 'sort', 'currency']);
        $tabLabels = [
            'all' => setting('library.tab.all_label', 'الكلّ'),
            'courses' => setting('library.tab.courses_label', 'تدريبات'),
            'paths' => setting('library.tab.paths_label', 'مسارات'),
            'bundles' => setting('library.tab.bundles_label', 'بندلز'),
            'products' => setting('library.tab.products_label', 'منتجات'),
            'certificates' => setting('library.tab.certificates_label', 'شهادات'),
        ];
        $tabs = collect(\App\Services\Library\LibraryShelf::TABS)->map(fn ($key) => [
            'key' => $key,
            'label' => $tabLabels[$key],
            'count' => $counts[$key] ?? 0,
            'url' => route('library.index', $query + ['tab' => $key]),
        ])->all();
    @endphp

    <x-page-header
        :title="setting('library.page.title', 'مكتبتي')"
        :subtitle="str_replace(':count', $counts['all'] ?? 0, setting('library.page.subtitle', 'عندك :count عنصر بوصولٍ دائم.'))"
        :breadcrumbs="[
            ['label' => setting('library.breadcrumb.home', 'الرئيسيّة'), 'url' => \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/')],
            ['label' => setting('library.page.title', 'مكتبتي')],
        ]">
        <x-slot:action>
            <a href="{{ $storeUrl }}" class="btn hidden md:inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('library.action.store_label', 'المتجر') }}</a>
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($hasAnything)
        {{-- ثلاثة ظاهرة: بحث + النوع + الفرز — والعملة خلف «متقدّمة» (2.15-أ-4) --}}
        <x-filters :action="route('library.index')">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <label class="block grow min-w-40">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('library.filter.search_label', 'بحث') }}</span>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ setting('library.filter.search_placeholder', 'اكتب اسم العنصر…') }}"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="block min-w-36">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('library.filter.type_label', 'النوع') }}</span>
                <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('library.filter.type_any', 'كلّ الأنواع') }}</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block min-w-36">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('library.filter.sort_label', 'فرز') }}</span>
                <select name="sort" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('library.filter.apply_label', 'طبّق') }}</button>

            <x-slot:advanced>
                <label class="block min-w-40">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('library.filter.currency_label', 'العملة المُشترى بها') }}</span>
                    <input type="text" name="currency" value="{{ request('currency') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </x-slot:advanced>
        </x-filters>

        @if ($items->isEmpty())
            <x-empty :message="setting('library.empty.filtered_message', 'مفيش نتائج للفلتر ده')"
                     :action="setting('library.empty.reset_label', 'امسح الفلاتر')"
                     :href="route('library.index', ['tab' => $tab])" />
        @else
            {{-- الرفّ: شبكة مرنة بلا تمرير أفقيّ على الموبايل (2.15-ج) --}}
            <div class="grid gap-3 grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($items as $item)
                    @include('library.partials.card', ['item' => $item])
                @endforeach
            </div>
        @endif
    @else
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد، تشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
        <x-empty :message="setting('library.empty.message', 'مكتبتك لسّه فاضية')"
                 :action="setting('library.empty.action', 'اكتشف المتجر')"
                 :href="$storeUrl" />
    @endif

    @include('library.partials.item-modal')
@endsection

@section('mobile_action')
    <a href="{{ $storeUrl }}" class="btn flex items-center justify-center w-full rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('library.action.store_label', 'المتجر') }}</a>
@endsection
