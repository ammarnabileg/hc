@extends('layouts.admin')

@section('title', 'الإعدادات والنظام')

@section('content')
    <x-page-header title="الإعدادات والنظام"
                   subtitle="{{ $tabs[$tab]['hint'] ?? 'كلّ مفاتيح المنصّة في صفحة واحدة.' }}"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'الإعدادات والنظام'],
                       ['label' => $tabs[$tab]['label'] ?? ''],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.settings.export') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">تصدير JSON</a>
            @can('settings_general.import')
                <form method="post" action="{{ route('admin.settings.import') }}" enctype="multipart/form-data" class="flex items-center gap-1">
                    @csrf
                    <input type="file" name="file" accept="application/json" required
                           class="text-xs w-36" aria-label="ملفّ إعدادات JSON">
                    <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">استيراد</button>
                </form>
            @endcan
        </x-slot:action>
    </x-page-header>

    {{-- ⭐ بحث موحّد داخل كلّ الإعدادات — والنتيجة بمسارها الكامل وتنقلك للحقل بتظليل مؤقّت --}}
    <div class="card p-3 mb-4 relative">
        <input type="search" id="settings-search" placeholder="دوّر بالاسم أو بالمفتاح أو بالقيمة…"
               value="{{ $search }}" autocomplete="off"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        <div id="settings-search-results" class="absolute inset-x-3 top-full mt-1 card p-2 z-30 hidden max-h-72 overflow-y-auto text-sm"></div>
    </div>

    <div class="grid gap-4 md:grid-cols-[240px_1fr]">
        {{-- ⭐ صفحة واحدة بتابات جانبيّة (2.15-د) — وعلى الموبايل رقائق أفقيّة --}}
        {{--
         | `min-w-0` شرطُ أن يعمل `overflow-x-auto` أصلًا: عنصر الشبكة (Grid item)
         | افتراضيّه `min-width: auto` — أي **لا يصغر تحت مقاس محتواه**. فالرقائق
         | تمدّ التاب فيمدّ الصفحة، ويبقى صندوق التمرير موجودًا بلا ما يمرّره.
         | والنتيجة تمريرٌ أفقيّ للصفحة كلّها — وهو ممنوع نصًّا (2.15-ج).
         --}}
        <nav class="min-w-0 flex md:flex-col gap-2 overflow-x-auto no-scrollbar md:sticky md:top-4 md:self-start">
            @foreach ($tabs as $key => $meta)
                <a href="{{ route('admin.settings.index', ['tab' => $key]) }}"
                   class="shrink-0 rounded-xl px-3 py-2 text-sm motion-standard"
                   style="{{ $tab === $key
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $meta['label'] }}</a>
            @endforeach

            @if (! isset($tabs['finance']))
                {{-- 🔒 المجموعة الماليّة غير موجودة أصلًا لغير مالك المنصّة — ولا حتى كعنصر معطَّل --}}
            @else
                <a href="{{ route('admin.finance.index') }}"
                   class="shrink-0 rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">↗ صفحة الماليّات</a>
            @endif
        </nav>

        <div class="space-y-4">
            @if ($tab === 'maintenance')
                @include('admin.settings.tabs.maintenance')
            @elseif ($tab === 'audit')
                @include('admin.settings.tabs.audit')
            @elseif ($tab === 'countries')
                {{-- 12.7-د: الشاشة الكاملة (جدول + فروق قبل الدمج) ثمّ مفاتيحها --}}
                @include('admin.settings.tabs.countries')

                <div class="space-y-3" id="settings-list">
                    @foreach ($groups as $group => $rows)
                        @include('admin.settings.partials.group-card', [
                            'group' => $group,
                            'rows' => $rows,
                            'registry' => $registry,
                            'endpoint' => route('admin.settings.field'),
                            'open' => false,
                        ])
                    @endforeach
                </div>
            @else
                {{-- كلّ مجموعة كارت مطويّ بعنوان عربيّ — مولِّد عامّ لا 44 شاشة يدويّة --}}
                <div class="space-y-3" id="settings-list">
                    @forelse ($groups as $group => $rows)
                        @include('admin.settings.partials.group-card', [
                            'group' => $group,
                            'rows' => $rows,
                            'registry' => $registry,
                            'endpoint' => route('admin.settings.field'),
                            'open' => $loop->first || $search !== '' || $rows->contains('key', $highlight),
                        ])
                    @empty
                        <x-empty message="مافيش إعدادات في التاب ده لسه." />
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection
