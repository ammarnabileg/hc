@extends('layouts.admin')

@section('title', setting('admin.stats.index.alihsayyat', 'الإحصائيّات'))

@php
    /*
     | لقطة لوحة الإحصاءات لزرّ [استخراج كصورة] (12.14-هـ):
     | نأخذ كروت الـKPI كما تعرضها الشاشة — بلا حساب موازٍ ولا مصدر ثانٍ.
     */
    $exportRows = collect($data['kpis'] ?? $data['cards'] ?? [])
        ->values()
        ->map(fn ($card, $i) => [
            'rank' => $i + 1,
            'name' => (string) ($card['label'] ?? $card['title'] ?? ''),
            'value' => (string) ($card['value'] ?? ''),
        ])
        ->filter(fn ($row) => $row['name'] !== '')
        ->values()
        ->all();
@endphp

@section('content')
    <x-page-header :title="setting('admin.stats.index.alihsayyat', 'الإحصائيّات')"
                   :subtitle="setting('admin.stats.index.arqam_llard_fqt_bfltr_ftra_wahd_ala_kl', 'أرقام للعرض فقط — بفلتر فترة واحد على كلّ التابات.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.stats.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.stats.index.alihsayyat', 'الإحصائيّات')],
                   ]">
        <x-slot:action>
            {{-- ⭐ «وكلّ اللوحات في المنصّة عمومًا قابلة للاستخراج كصورة» (12.14-هـ) --}}
            {{-- التاب مصفوفة [label · permission · owner_only] — العنوان منها لا منها كلّها --}}
            <x-export-image kind="stats" :title="setting('admin.stats.index.ihsayyat', 'إحصائيّات — ').($tabs[$tab]['label'] ?? $tab)"
                            :subtitle="$period['from']->format('Y/m/d').' — '.$period['to']->format('Y/m/d')"
                            :rows="$exportRows" />

            {{-- ⭐ «تصدير CSV/Excel/PDF» (24.3-خامسًا) — والثلاثة تقع فعلًا:
                 XLSX حزمة OOXML وPDF بخطٍّ عربيّ مضمَّن، بلا أيّ مكتبة خارجيّة.
                 والزرّ يفتح **بوب-أب [تصدير]** لأنّ النصّ نفسه يوجبه بأربعة بنود:
                 «**[تصدير]** الصيغة + **الأعمدة المختارة** + الفترة + Toggle «ضمّ
                 المقارنة»» — وثلاثة روابط مباشرة تُسقِط **الأعمدة المختارة** كلّها. --}}
            <button type="button" data-modal-open="stats-export"
                    class="rounded-xl px-3 py-2 text-sm motion-standard"
                    style="background: var(--surface-raised)">{{ setting('stats.export.popup.open', 'تصدير CSV/Excel/PDF') }}</button>
        </x-slot:action>
    </x-page-header>

    {{--
        ⭐⭐ بوب-أب **[تصدير]** (24.3-خامسًا) — أربعة بنوده كلّها تقع في الملفّ:
          · **الصيغة** — من إعداد `stats.export.formats` (CSV/Excel/PDF).
          · **الأعمدة المختارة** — `columns[]` تصفّيها `StatsService::exportColumns()`
            نفسها، فالعمود غير المعلَّم **لا يخرج في الملفّ** لا أنّه يخرج مخفيًّا.
          · **الفترة** — تفتح على فترة الشاشة، وللأدمن أن يصدّر غيرها بلا إعادة فلترة.
          · **Toggle «ضمّ المقارنة»** — يضيف عمود «الفترة السابقة» بقيمه، لا علامةً
            في الرابط (قاعدة الوعد: زرٌّ يَعِد بشيء يُخرِجه — 2.17-ب).
    --}}
    <x-modal id="stats-export" :title="setting('stats.export.popup.title', 'تصدير')">
        <form method="get" action="{{ route('admin.stats.export') }}" class="space-y-4">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('stats.export.popup.format', 'الصيغة') }}</span>
                <select name="format" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($exportFormats as $format => $label)
                        <option value="{{ $format }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <fieldset>
                <legend class="block text-sm mb-1">{{ setting('stats.export.popup.columns', 'الأعمدة المختارة') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($exportColumns as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="columns[]" value="{{ $key }}" checked>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('stats.export.popup.columns_hint', 'لو مافيش عمود متعلّم هيتصدّر الجدول كامل.') }}</p>
            </fieldset>

            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.stats.index.mn', 'من') }}</span>
                    <input type="date" name="from" value="{{ $period['from']->toDateString() }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.stats.index.ila', 'إلى') }}</span>
                    <input type="date" name="to" value="{{ $period['to']->toDateString() }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="compare" value="1" @checked($period['compare'])>
                <span>{{ setting('stats.export.popup.compare', 'ضمّ المقارنة') }}</span>
            </label>

            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('stats.export.popup.submit', 'تنزيل الملفّ') }}</button>
        </form>
    </x-modal>

    {{-- فلتر الفترة العامّ + Toggle المقارنة بالفترة السابقة (12.8) --}}
    <x-filters :action="route('admin.stats.index')">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.stats.index.mn', 'من') }}</span>
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">{{ setting('admin.stats.index.ila', 'إلى') }}</span>
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex items-center gap-2 text-xs mt-4">
            <input type="checkbox" name="compare" value="1" @checked($period['compare'])>
            <span>{{ setting('admin.stats.index.qarn_balftra_alsabqa', 'قارن بالفترة السابقة') }}</span>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.stats.index.tbq', 'طبّق') }}</button>
    </x-filters>

    {{-- التاب الماليّ لا يظهر أصلًا لغير المخوَّل — التصفية في الخادم (24.3) --}}
    <x-tabs :current="$tab" :tabs="collect($tabs)->map(fn ($t, $key) => [
        'key' => $key,
        'label' => $t['label'],
        'url' => route('admin.stats.index', ['tab' => $key, 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString(), 'compare' => $period['compare'] ? 1 : null]),
    ])->values()->all()" />

    @if (! empty($data['kpis']))
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
            @foreach (array_slice($data['kpis'], 0, (int) setting('ux.kpi.max_cards', 4)) as $kpi)
                <x-kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" />
            @endforeach
        </div>
    @endif

    @includeIf('admin.stats.tabs.' . $tab)
@endsection
