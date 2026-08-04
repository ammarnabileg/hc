@extends('layouts.admin')

@section('title', setting('admin.settings.index.aliadadat_walnzam', 'الإعدادات والنظام'))

@section('content')
    <x-page-header :title="setting('admin.settings.index.aliadadat_walnzam', 'الإعدادات والنظام')"
                   :subtitle="$tabs[$tab]['hint'] ?? setting('admin.settings.index.kl_mfatyh_almnsa_fy_sfha_wahda', 'كلّ مفاتيح المنصّة في صفحة واحدة.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.settings.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.settings.index.aliadadat_walnzam', 'الإعدادات والنظام')],
                       ['label' => $tabs[$tab]['label'] ?? ''],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.settings.export') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.index.tsdyr_json', 'تصدير JSON') }}</a>
            @can('settings_general.import')
                <form method="post" action="{{ route('admin.settings.import') }}" enctype="multipart/form-data" class="flex items-center gap-1">
                    @csrf
                    <input type="file" name="file" accept="application/json" required
                           class="text-xs w-36" aria-label="{{ setting('admin.settings.index.mlf_iadadat_json', 'ملفّ إعدادات JSON') }}">
                    <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.index.astyrad', 'استيراد') }}</button>
                </form>
            @endcan
        </x-slot:action>
    </x-page-header>

    {{-- ⭐ بحث موحّد داخل كلّ الإعدادات — والنتيجة بمسارها الكامل وتنقلك للحقل بتظليل مؤقّت --}}
    <div class="card p-3 mb-4 relative">
        <input type="search" id="settings-search" placeholder="{{ setting('admin.settings.index.dwr_balasm_aw_balmftah_aw_balqyma', 'دوّر بالاسم أو بالمفتاح أو بالقيمة…') }}"
               value="{{ $search }}" autocomplete="off"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        <div id="settings-search-results" class="absolute inset-x-3 top-full mt-1 card p-2 z-30 hidden max-h-72 overflow-y-auto text-sm"></div>
    </div>

    {{-- عمودُ الشبكة الافتراضيّ `auto` يتّسع لمقاس محتواه، فشريطُ التابات
         الأفقيّ (`overflow-x-auto`) يمطّ العمود بدل أن ينزلق داخله فيخرج
         الصفحةُ عن 375px (2.15-ج). و`minmax(0,1fr)` تحسم الحدّ الأدنى.

         ⚠️ والصنفان أدناه **ذوا قيمة حرّة** — لا يوجدان في الحزمة إلّا بعد
         `npm run build`. وقد سقطا مرّةً صامتَين: القالب صحيح والحزمة قديمة،
         فرجع `grid-template-columns` إلى مسارٍ واحد `auto` — 6px تمريرٍ أفقيّ
         على الموبايل، وعمودان منهاران إلى واحدٍ مكدَّس على الديسكتوب، بلا
         خطأٍ في أيّ مكان. وحارسُه: `tests/Feature/Ui/CompiledUtilitiesTest`. --}}
    <div class="grid gap-4 min-w-0 grid-cols-[minmax(0,1fr)] md:grid-cols-[240px_minmax(0,1fr)]">
        {{-- ⭐ صفحة واحدة بتابات جانبيّة (2.15-د) — وعلى الموبايل رقائق أفقيّة --}}
        {{--
         | `min-w-0` شرطُ أن يعمل `overflow-x-auto` أصلًا: عنصر الشبكة (Grid item)
         | افتراضيّه `min-width: auto` — أي **لا يصغر تحت مقاس محتواه**. فالرقائق
         | تمدّ التاب فيمدّ الصفحة، ويبقى صندوق التمرير موجودًا بلا ما يمرّره.
         | والنتيجة تمريرٌ أفقيّ للصفحة كلّها — وهو ممنوع نصًّا (2.15-ج).
         --}}
        <nav class="min-w-0 w-full max-w-full flex md:flex-col gap-2 overflow-x-auto no-scrollbar md:sticky md:top-4 md:self-start">
            @foreach ($tabs as $key => $meta)
                {{-- 2.15-أ-7: المحظور **يُخفى** لا يُعطَّل — ومفاتيح المزايا لها مفتاحها --}}
                @continue($key === 'features' && ! auth()->user()->allows('feature_toggles.view')
                    && ! auth()->user()->allows('feature_toggles.list'))

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
                   class="shrink-0 rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.index.sfha_almalyat', '↗ صفحة الماليّات') }}</a>
            @endif
        </nav>

        {{--
         | `min-w-0` على **عمود المحتوى** لا على الشبكة وحدها: عنصر الشبكة
         | افتراضيّه `min-width: auto` — أي لا يصغر تحت **مقاس محتواه الأدنى**.
         | وتابّ المزايا وحده يرفع هذا المقاس إلى 365px (كارت الموبايل: مفتاحُ
         | الميزة `font-mono … truncate` — و`truncate` تعني `white-space: nowrap`
         | فمقاسه الأدنى سطرُه كاملًا — بجانب مفتاح التشغيل)، والمتاح 343px.
         | فيتّسع عمودُ الشبكة لمحتواه ويخرج 6px عن 375 — وهو التمرير الأفقيّ
         | الممنوع نصًّا (2.15-ج). و`min-w-0` تحسم الحدّ الأدنى فيقصّ الـ`truncate`
         | كما صُمِّم بدل أن يمدّ الصفحة.
         --}}
        <div class="min-w-0 space-y-4">
            @if ($tab === 'maintenance')
                @include('admin.settings.tabs.maintenance')
            @elseif ($tab === 'audit')
                @include('admin.settings.tabs.audit')
            @elseif ($tab === 'features')
                {{-- 24.3: شاشة مفاتيح المزايا الكاملة — وبلوك إعداداتها بداخلها --}}
                @include('admin.settings.tabs.features')
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
                        <x-empty :message="setting('admin.settings.index.mafysh_iadadat_fy_altab_dh_lsh', 'مافيش إعدادات في التاب ده لسه.')" />
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection
