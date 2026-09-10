{{--
  🖥️ **مفاتيح المزايا (Feature Toggles)** — 24.3.

  «إطفاء/تشغيل أيّ ميزة في المنصّة بلا نشر كود — **البديل الوحيد للصيانة
  الجزئيّة الملغاة** (12.7-و)».

  ⚠️ **لا حرفَ عربيّ محروق في هذا الملفّ**: كلّ نصٍّ ظاهر يمرّ بـ`setting()`
     (2.13 · فحص `settings:hardcoded`). والتعليقات وحدها عربيّة — وهي لا تُعرَض.

  ⭐ **الأعمدة:** 24.3 يذكر ثمانية حقول، و2.15-د يحدّ الجدول بـ5–7 أعمدة، و24.3
     نفسه ينصّ: «عند التعارض **تسبق القواعدُ الوصفَ**». فالحقول الثمانية كلّها
     حاضرة، والمفتاح (Key) يسكن **تحت اسم الميزة** في نفس الخليّة بدل عمودٍ
     ثامن — سبعة أعمدة، وبيانٌ كامل.
--}}

{{-- **الحالة الرابعة — بلا صلاحيّة:** التاب مخفيٌّ أصلًا من القائمة الجانبيّة
     (2.15-أ-7)، ومَن وصل بالرابط مباشرةً يُقال له ماذا يفعل لا يُترَك أمام صفحةٍ
     فارغة (2.17-ب). --}}
@unless (auth()->user()->allows('feature_toggles.view') || auth()->user()->allows('feature_toggles.list'))
    <x-empty :message="setting('features.ui.state.denied', 'مالكش صلاحيّة على مفاتيح المزايا — كلّم مالك المنصّة لو محتاج وصولًا.')" />
    @php return; @endphp
@endunless

@php
    $rows = $features['rows'];
    $groups = $features['groups'];
    $filters = $features['filters'];
    $mayEdit = auth()->user()->allows('feature_toggles.edit');
@endphp

{{-- الغرض + القاعدة المثبّتة (24.3) --}}
<div class="card p-4">
    <p class="text-sm">{{ setting('features.ui.purpose', 'إطفاء أو تشغيل أيّ ميزة بلا نشر كود — وده البديل الوحيد للصيانة الجزئيّة الملغاة.') }}</p>
    {{-- القاعدة المثبّتة — بأيقونة SVG مرسومة، **لا شارةَ حالة**: قاموس الألوان
         (2.16) يعطي كلّ لونٍ معنًى واحدًا، ووسمُ نصٍّ تعريفيّ بلون «موقوف»
         يُفقِد اللونَ معناه في المنصّة كلّها. --}}
    <p class="text-xs mt-2 flex items-start gap-2" style="color: var(--text-muted)">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="shrink-0 mt-0.5"
             style="color: var(--color-brand-500)">
            <path d="M4 8.5 7 11.5 12.5 5" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>{{ setting('features.ui.pinned_rule', 'لا صيانة جزئيّة لميزة بعينها — أُلغيت؛ الإطفاء يتمّ من هنا فقط.') }}</span>
    </p>
</div>

{{-- الهيدر: شارة عدد الموقوفة + حفظ · تصدير/استيراد JSON · ↺ Reset الكلّ --}}
<div class="card p-3 flex flex-wrap items-center justify-between gap-2">
    <span class="text-xs rounded-full px-3 py-1"
          style="background: color-mix(in srgb, var(--color-state-{{ $features['paused'] > 0 ? 'warn' : 'ok' }}) 15%, transparent);
                 color: var(--color-state-{{ $features['paused'] > 0 ? 'warn' : 'ok' }})">
        {{ str_replace(':count', (string) $features['paused'], (string) setting('features.ui.paused_badge', 'موقوفة: :count')) }}
    </span>

    <div class="flex flex-wrap items-center gap-2">
        @if ($mayEdit)
            <button form="features-settings-form"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('features.ui.save', 'حفظ') }}</button>
        @endif

        <a href="{{ route('admin.features.export') }}" class="rounded-xl px-3 py-2 text-sm"
           style="background: var(--surface-raised)">{{ setting('features.ui.export', 'تصدير JSON') }}</a>

        @can('feature_toggles.manage')
            {{-- `input[type=file]` له عرضٌ داخليّ لا ينكمش تحت مقاس محتواه، فبلا
                 `min-w-0` يدفع الصفَّ خارج الشاشة على 375px (2.15-ج). --}}
            <form method="post" action="{{ route('admin.features.import') }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-center gap-1 min-w-0 max-w-full">
                @csrf
                <input type="file" name="file" accept="application/json" required
                       class="text-xs w-32 min-w-0 max-w-full"
                       aria-label="{{ setting('features.ui.file_label', 'ملفّ مفاتيح JSON') }}">
                <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('features.ui.import', 'استيراد JSON') }}</button>
            </form>

            <form method="post" action="{{ route('admin.features.reset-all') }}">
                @csrf
                <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('features.ui.reset_all', '↺ Reset الكلّ') }}</button>
            </form>
        @endcan
    </div>
</div>

{{-- تنبيه «ميزة موقوفة > N ساعة» — من الإعدادات لا من رقمٍ محروق --}}
@if ($features['long_outages']->isNotEmpty())
    <div class="card p-3 text-xs" style="color: var(--color-state-warn)">
        {{ str_replace(
            [':count', ':hours'],
            [$features['long_outages']->count(), (int) setting('features.alert_after_hours', 24)],
            (string) setting('features.ui.long_outage', 'فيه :count ميزة موقوفة من أكتر من :hours ساعة — راجعها.'),
        ) }}
    </div>
@endif

{{-- الفلاتر الثلاثة الظاهرة (2.15-د): بحث · المجموعة · الحالة --}}
<form method="get" class="card p-3 grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
    <input type="hidden" name="tab" value="features">

    <input type="search" name="fq" value="{{ $filters['q'] }}"
           placeholder="{{ setting('features.ui.filter_search', 'دوّر بالاسم أو بالمفتاح…') }}"
           class="w-full rounded-xl px-3 py-2 text-sm"
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

    <select name="fgroup" aria-label="{{ setting('features.ui.filter_group', 'المجموعة') }}"
            class="rounded-xl px-3 py-2 text-sm"
            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        <option value="">{{ setting('features.ui.filter_group', 'المجموعة') }} — {{ setting('features.ui.filter_all', 'الكلّ') }}</option>
        @foreach ($groups as $key => $label)
            <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="fstatus" aria-label="{{ setting('features.ui.filter_status', 'الحالة') }}"
            class="rounded-xl px-3 py-2 text-sm"
            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        <option value="">{{ setting('features.ui.filter_status', 'الحالة') }} — {{ setting('features.ui.filter_all', 'الكلّ') }}</option>
        <option value="on" @selected($filters['status'] === 'on')>{{ setting('features.ui.status.on', 'مشتغّل') }}</option>
        <option value="off" @selected($filters['status'] === 'off')>{{ setting('features.ui.status.off', 'موقوف') }}</option>
        <option value="partial" @selected($filters['status'] === 'partial')>{{ setting('features.ui.status.partial', 'جزئيّ') }}</option>
    </select>

    <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('features.ui.filter_apply', 'فلترة') }}</button>
</form>

{{-- نصوص السكربت وروابطه — من `setting()` لا من حروفٍ داخل الجافاسكربت (2.13) --}}
<div id="features-texts" class="hidden"
     data-texts="{{ json_encode([
         'loading' => setting('features.ui.state.loading', 'بنحمّل…'),
         'error' => setting('features.ui.state.error', 'حصل خطأ وإحنا بنحفظ — جرّب تاني، ولو فضل زيّه بلّغ التقنيّ.'),
         'audit_empty' => setting('features.ui.popup.audit_empty', 'مافيش تبديل مسجَّل لسه.'),
         'scope_empty' => setting('features.ui.scope.empty', 'مافيش Override — الميزة عامّة.'),
         'scope_on' => setting('features.ui.scope.value_on', 'شغّالة'),
         'scope_off' => setting('features.ui.scope.value_off', 'موقوفة'),
     ]) }}"
     data-urls="{{ json_encode([
         'toggle' => route('admin.features.toggle'),
         'scope' => route('admin.features.scope'),
         'reset' => route('admin.features.reset'),
         'audit' => route('admin.features.audit'),
     ]) }}"></div>

@if ($rows->isEmpty())
    {{-- الحالة الفارغة: سطر واحد (وهي «لا تحدث» في المعتاد — لكنّ الفلتر يفرغها).
         السجلّ ثابتٌ من الكود فلا يكون فارغًا أصلًا بلا فلتر — لكنّ :filtered
         يبقى صريحًا هنا اتّساقًا مع بقيّة الشاشات (24.2). --}}
    <x-empty :message="setting('features.ui.state.empty', 'مافيش مزايا في الفلتر ده — وسّع الفلتر شويّة.')"
             :filtered="$filters['q'] !== '' || $filters['group'] !== '' || $filters['status'] !== ''" />
@else
    <div class="card overflow-hidden" id="features-table">
        {{-- ديسكتوب: جدول بسبعة أعمدة --}}
        <table class="hidden md:table w-full text-sm">
            <thead style="background: var(--surface-sunken)">
                <tr class="text-xs" style="color: var(--text-muted)">
                    <th class="text-start p-3">{{ setting('features.ui.col.feature', 'الميزة') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.group', 'المجموعة') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.toggle', 'تشغيل/إيقاف') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.scope', 'النطاق') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.visible', 'مين يشوفها وهي موقوفة') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.last', 'آخر تبديل') }}</th>
                    <th class="text-start p-3">{{ setting('features.ui.col.actions', 'إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr style="border-top: 1px solid var(--border)"
                        data-feature="{{ $row['key'] }}" data-payload="{{ json_encode($row) }}">
                        <td class="p-3">
                            <div class="font-semibold flex items-center gap-2">
                                {{ $row['label_ar'] }}
                                @if ($row['is_beta'] && setting('features.show_beta_badge', true))
                                    <x-state-badge state="warn" :label="setting('features.ui.beta', 'تجريبيّة')" />
                                @endif
                            </div>
                            {{-- المفتاح (Key) — الحقل الثامن، تحت الاسم لا في عمودٍ زائد --}}
                            <div class="font-mono text-xs" style="color: var(--text-muted)">{{ $row['key'] }}</div>
                        </td>
                        <td class="p-3 text-xs">{{ $groups[$row['group']] ?? $row['group'] }}</td>
                        <td class="p-3">@include('admin.features.partials.toggle', ['row' => $row, 'mayEdit' => $mayEdit])</td>
                        <td class="p-3 text-xs">@include('admin.features.partials.scope', ['row' => $row, 'mayEdit' => $mayEdit])</td>
                        <td class="p-3 text-xs">{{ $features['visibility_labels'][$row['visibility']] ?? $row['visibility'] }}</td>
                        <td class="p-3 text-xs" style="color: var(--text-muted)">
                            @include('admin.features.partials.last-toggle', ['row' => $row, 'people' => $features['people']])
                        </td>
                        <td class="p-3">@include('admin.features.partials.actions', ['row' => $row, 'mayEdit' => $mayEdit])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- موبايل: كروت رأسيّة — **بلا تمرير أفقيّ** على 375px (2.15-ج) --}}
        <div class="md:hidden">
            @foreach ($rows as $row)
                <details class="p-3" style="border-top: 1px solid var(--border)"
                         data-feature="{{ $row['key'] }}" data-payload="{{ json_encode($row) }}">
                    <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold truncate">{{ $row['label_ar'] }}</div>
                            <div class="font-mono text-xs truncate" style="color: var(--text-muted)">{{ $row['key'] }}</div>
                        </div>
                        @include('admin.features.partials.toggle', ['row' => $row, 'mayEdit' => $mayEdit])
                    </summary>

                    <div class="mt-3 space-y-2 text-xs">
                        <div><span style="color: var(--text-muted)">{{ setting('features.ui.col.group', 'المجموعة') }}:</span> {{ $groups[$row['group']] ?? $row['group'] }}</div>
                        <div><span style="color: var(--text-muted)">{{ setting('features.ui.col.scope', 'النطاق') }}:</span> @include('admin.features.partials.scope', ['row' => $row, 'mayEdit' => $mayEdit])</div>
                        <div><span style="color: var(--text-muted)">{{ setting('features.ui.col.visible', 'مين يشوفها وهي موقوفة') }}:</span> {{ $features['visibility_labels'][$row['visibility']] ?? $row['visibility'] }}</div>
                        <div><span style="color: var(--text-muted)">{{ setting('features.ui.col.last', 'آخر تبديل') }}:</span> @include('admin.features.partials.last-toggle', ['row' => $row, 'people' => $features['people']])</div>
                        <div>@include('admin.features.partials.actions', ['row' => $row, 'mayEdit' => $mayEdit])</div>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endif

{{-- بلوك الإعدادات الخمسة (24.3) --}}
@include('admin.features.partials.settings-block', ['mayEdit' => $mayEdit])

{{-- البوب-أبات: واحدٌ لكلّ غرض يُملأ من صفّه — لا 34 نسخة في الصفحة (2.15) --}}
@include('admin.features.partials.modals', ['features' => $features, 'mayEdit' => $mayEdit])
@include('admin.features.partials.script')
