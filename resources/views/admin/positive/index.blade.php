@extends('layouts.admin')

@section('title', setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة'))

@php
    /**
     * إدارة مكتبة الرسائل الإيجابيّة (2.6-ب · 2.13).
     * سؤال واحد للشاشة: «إيه الرسائل اللي بتظهر ومتى؟» — والفعل الرئيسيّ واحد
     * (+ رسالة)، والباقي داخل كلّ صفّ.
     *
     * ⭐ [2026-09-14] جدولٌ حقيقيّ لا كروت (2.6-ب حرفًا: «جدول: النصّ · اللغة ·
     * الفئة · عدد مرّات الظهور · الحالة · إجراءات») — عبر `<x-table>` المشترك
     * فيحصر أيّ تمرير أفقيّ داخل الجدول وحده لا جسم الصفحة (2.15-ج). وأُضيف
     * عمود **اللغة** (فلترةً وحفظًا) وزرّ **نسخ** وزرّ **استيراد CSV** —
     * الثلاثة منصوصون في 2.6-ب ولم يوجدوا أصلًا (§25 v5.9، سجلّ الفجوة).
     */
    $canCreate = auth()->user()?->can('positive_messages.create');
    $canEdit = auth()->user()?->can('positive_messages.edit');
    $canDelete = auth()->user()?->can('positive_messages.delete');
    $canManage = auth()->user()?->can('positive_messages.manage');
    $languageLabels = [
        'ar' => setting('admin.positive.index.lgha_arby', 'عربي'),
        'en' => setting('admin.positive.index.lgha_ingizy', 'English'),
    ];
@endphp

@section('content')
    <x-page-header :title="setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة')"
                   :subtitle="setting('admin.positive.index.klma_tshjya_fy_wqtha_bnbra_almnsa_wbla', 'كلمة تشجيع في وقتها — بنبرة المنصّة وبلا مبالغة، ولكلّ رسالة سياقها.')"
                   :breadcrumbs="[['label' => setting('admin.positive.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة')]]">
        <x-slot:action>
            <div class="flex items-center gap-2 flex-wrap">
                @if ($canCreate)
                    <button type="button" data-modal-open="positive-import-modal"
                            class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.astyrad_csv', 'استيراد CSV') }}</button>
                @endif
                @if ($canCreate)
                    <button type="button" data-modal-open="positive-modal" data-positive-new
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.positive.index.rsala', '+ رسالة') }}</button>
                @endif
            </div>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.positive.index.kl_alrsayl', 'كلّ الرسائل')" :value="$counts['total']" icon="envelope" />
        <x-kpi :label="setting('admin.positive.index.almfala', 'المفعّلة')" :value="$counts['active']" icon="check" />
        <x-kpi :label="setting('admin.positive.index.alsyaqat', 'السياقات')" :value="$counts['contexts']" icon="compass" />
        <x-kpi :label="setting('admin.positive.index.mrat_alzhwr', 'مرّات الظهور')" :value="$counts['shown']" icon="eye" />
    </div>

    {{-- ثلاثة فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) — السياق · الحالة · اللغة --}}
    <x-filters :action="route('admin.positive.index')">
        <label class="text-sm">{{ setting('admin.positive.index.alsyaq', 'السياق') }}
            <select name="context" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.positive.index.alkl', 'الكلّ') }}</option>
                @foreach ($contexts as $key => $label)
                    <option value="{{ $key }}" @selected($context === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">{{ setting('admin.positive.index.alhala', 'الحالة') }}
            <select name="state" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.positive.index.alkl', 'الكلّ') }}</option>
                <option value="active" @selected($state === 'active')>{{ setting('admin.positive.index.mfala', 'مفعّلة') }}</option>
                <option value="paused" @selected($state === 'paused')>{{ setting('admin.positive.index.mwqwfa', 'موقوفة') }}</option>
            </select>
        </label>

        <label class="text-sm">{{ setting('admin.positive.index.allgha', 'اللغة') }}
            <select name="language" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.positive.index.alkl', 'الكلّ') }}</option>
                @foreach ($languageLabels as $key => $label)
                    <option value="{{ $key }}" @selected($language === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.fltra', 'فلترة') }}</button>
    </x-filters>

    @if ($messages->isEmpty())
        {{-- تمييز «لسّه مافيش رسائل أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
        <x-empty :message="setting('admin.positive.index.lsh_mafysh_rsayl_adf_awl_klma_tshjya_mn_zr', 'لسّه مافيش رسائل — أضف أوّل كلمة تشجيع من زرّ «+ رسالة» فوق.')"
                 :filtered="$context !== '' || $state !== '' || $language !== ''" />
    @else
        <div class="card p-2">
            <x-table :label="setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة')">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border)">
                        <th class="p-3 text-start">{{ setting('admin.positive.index.alns', 'النصّ') }}</th>
                        <th class="p-3 text-start">{{ setting('admin.positive.index.allgha', 'اللغة') }}</th>
                        <th class="p-3 text-start">{{ setting('admin.positive.index.alfia', 'الفئة') }}</th>
                        <th class="p-3 text-start">{{ setting('admin.positive.index.idd_mrat_alzhwr', 'عدد مرّات الظهور') }}</th>
                        <th class="p-3 text-start">{{ setting('admin.positive.index.alhala', 'الحالة') }}</th>
                        <th class="p-3 text-start">{{ setting('admin.positive.index.ijraat', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr style="border-bottom: 1px solid var(--border)">
                            <td class="p-3 max-w-xs">
                                @if ($message->emoji)<span aria-hidden="true">{{ $message->emoji }}</span>@endif
                                {{ \Illuminate\Support\Str::limit($message->body, 70) }}
                            </td>

                            <td class="p-3 text-xs" style="color: var(--text-muted)">{{ $languageLabels[$message->language] ?? $message->language }}</td>

                            <td class="p-3">
                                <span class="rounded-full px-2 py-0.5 text-xs"
                                      style="background: var(--surface-sunken); color: var(--text-muted)">
                                    {{ $contexts[$message->context] ?? $message->context }}
                                </span>
                            </td>

                            <td class="p-3 text-sm tabular-nums">{{ number_format($message->shown_count) }}</td>

                            <td class="p-3">
                                <x-state-badge :state="$message->is_active ? 'ok' : 'idle'"
                                               :label="$message->is_active ? setting('admin.positive.index.mfala', 'مفعّلة') : setting('admin.positive.index.mwqwfa', 'موقوفة')" />
                            </td>

                            <td class="p-3">
                                <div class="flex items-center gap-3 flex-wrap text-xs">
                                    <button type="button" class="underline" data-positive-row-preview
                                            data-body="{{ $message->body }}" data-emoji="{{ $message->emoji }}">{{ setting('admin.positive.index.mianah', 'معاينة') }}</button>

                                    @if ($canEdit)
                                        <button type="button" class="underline" data-positive-edit
                                                data-id="{{ $message->id }}"
                                                data-action="{{ route('admin.positive.update', $message) }}"
                                                data-context="{{ $message->context }}"
                                                data-body="{{ $message->body }}"
                                                data-emoji="{{ $message->emoji }}"
                                                data-language="{{ $message->language }}"
                                                data-sort="{{ $message->sort_order }}"
                                                data-active="{{ $message->is_active ? 1 : 0 }}">{{ setting('admin.positive.index.tadyl', 'تعديل') }}</button>

                                        <form method="post" action="{{ route('admin.positive.toggle', $message) }}">
                                            @csrf
                                            <button type="submit" class="underline">{{ $message->is_active ? setting('admin.positive.index.awqf', 'أوقف') : setting('admin.positive.index.fal', 'فعّل') }}</button>
                                        </form>
                                    @endif

                                    @if ($canCreate)
                                        {{-- نسخ: بداية سريعة لرسالةٍ شبيهة — تُحفَظ موقوفةً حتى تُراجَع (2.6-ب) --}}
                                        <form method="post" action="{{ route('admin.positive.duplicate', $message) }}">
                                            @csrf
                                            <button type="submit" class="underline">{{ setting('admin.positive.index.nskh', 'نسخ') }}</button>
                                        </form>
                                    @endif

                                    @if ($canDelete)
                                        <form method="post" action="{{ route('admin.positive.destroy', $message) }}"
                                              onsubmit="return confirm('{{ setting('admin.positive.index.thdhf_alrsala_dy_nhayya', 'تحذف الرسالة دي نهائيًّا؟') }}')">
                                            @csrf @method('delete')
                                            <button type="submit" class="underline" style="color: var(--color-state-danger)">{{ setting('admin.positive.index.hdhf', 'حذف') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>

        <div class="mt-4">{{ $messages->links() }}</div>
    @endif

    {{--
      ⭐ معاينة الظرف بسياق (2.10.1-27 · §25 v5.8): سحبةٌ حقيقيّة من سياقٍ
      محدَّد — مفيدة لمعاينة «إيه اللي هيظهر فعلًا» لا رسالةً بعينها (تلك
      معاينتها الفوريّة أعلاه في كلّ صفّ، بلا رحلة خادمٍ).
    --}}
    @if ($canEdit)
        <form method="post" action="{{ route('admin.positive.preview') }}" class="card p-4 mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm">{{ setting('admin.positive.index.jrb_syaqa', 'جرّب سياقًا') }}
                <select name="context" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($contexts as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" data-positive-preview class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.mianah_alzrf', 'معاينة الظرف') }}</button>
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.positive.index.btshwf_ally_hyshwfh_almstkhdm_balzbt', 'بتشوف اللي هيشوفه المستخدم بالظبط.') }}</span>
        </form>

        @if ($preview = session('previewEnvelope'))
            <div class="card p-6 mt-3 text-center">
                @if (isset($preview['empty']))
                    <p class="text-sm" style="color: var(--text-muted)">{{ $preview['empty'] }}</p>
                @else
                    <div class="envelope" aria-hidden="true">
                        <div class="envelope-pocket"></div>
                        <div class="envelope-letter-tab" style="transform: translateY(-65px)"></div>
                        <div class="envelope-flap" style="transform: rotateX(180deg)"></div>
                    </div>
                    <div class="rounded-2xl p-4 mt-4 text-sm max-w-xs mx-auto"
                         style="background: var(--surface-sunken); border: 1px solid var(--border)">
                        @if ($preview['emoji'])
                            <div class="text-2xl mb-1" aria-hidden="true">{{ $preview['emoji'] }}</div>
                        @endif
                        <p class="leading-relaxed">{{ $preview['body'] }}</p>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- معاينة استيراد CSV — صفوفٌ سليمة/فاسدة قبل أيّ حفظ (2.6-ب حرفًا) --}}
    @if ($canCreate && ($importRows = session('importPreviewRows')))
        @php($validCount = count(array_filter($importRows, fn ($r) => $r['valid'])))
        <div class="card p-4 mt-4" id="positive-import-preview">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <h2 class="font-bold">{{ setting('admin.positive.index.mianah_alastyrad', 'معاينة الاستيراد') }}</h2>
                <span class="text-xs" style="color: var(--text-muted)">
                    {{ strtr((string) setting('admin.positive.index.v1_sf_slym_mn_v2', ':v1 صفٍّ سليم من :v2'), [':v1' => (string) $validCount, ':v2' => (string) count($importRows)]) }}
                </span>
            </div>

            <div class="max-h-72 overflow-y-auto space-y-1">
                @foreach ($importRows as $row)
                    <div class="flex items-start gap-2 text-xs p-2 rounded-lg"
                         style="background: {{ $row['valid'] ? 'var(--surface-sunken)' : 'color-mix(in srgb, var(--color-state-danger) 10%, transparent)' }}">
                        <span class="shrink-0 font-mono" style="color: var(--text-muted)">#{{ $row['row'] }}</span>
                        @if ($row['valid'])
                            <span class="flex-1 break-words">{{ \Illuminate\Support\Str::limit($row['data']['body'], 90) }}</span>
                        @else
                            <span class="flex-1" style="color: var(--color-state-danger)">{{ $row['error'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <form method="post" action="{{ route('admin.positive.import.confirm') }}" class="mt-3">
                @csrf
                <button type="submit" @disabled($validCount === 0) class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ strtr((string) setting('admin.positive.index.aatmad_v1_sf', 'اعتماد :v1 صفّ'), [':v1' => (string) $validCount]) }}
                </button>
            </form>
        </div>
    @endif

    @if ($canManage)
        @include('admin.positive.partials.settings', ['settings' => $settings])
    @endif
@endsection

@section('mobile_action')
    @if ($canCreate)
        <button type="button" data-modal-open="positive-modal" data-positive-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-bold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.positive.index.rsala', '+ رسالة') }}</button>
    @endif
@endsection

@push('modals')
    @if ($canCreate || $canEdit)
        <x-modal id="positive-modal" :title="setting('admin.positive.index.rsala_iyjabya', 'رسالة إيجابيّة')">
            <form method="post" action="{{ route('admin.positive.store') }}" data-positive-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-positive-method>

                <label class="block text-sm font-semibold mb-1" for="positive-context">{{ setting('admin.positive.index.alsyaq_imta_tzhr_alrsala', 'السياق — إمتى تظهر الرسالة؟') }}</label>
                <select name="context" id="positive-context" required
                        class="w-full rounded-xl px-3 py-2 text-sm mb-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($contexts as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.positive.index.alsyaqat_nfsha_iadad_tqdr_tzwdha_mn_iadadat', 'السياقات نفسها إعداد — تقدر تزوّدها من «إعدادات الميزة» تحت.') }}
                </p>

                <label class="block text-sm font-semibold mb-1" for="positive-language">{{ setting('admin.positive.index.allgha', 'اللغة') }}</label>
                <select name="language" id="positive-language"
                        class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($languageLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="positive-body">{{ setting('admin.positive.index.ns_alrsala', 'نصّ الرسالة') }}</label>
                <textarea name="body" id="positive-body" required rows="3" maxlength="400"
                          placeholder="{{ setting('admin.positive.index.mthal_khlst_aldrs_kml_bhdw_int_mashy_sh', 'مثال: خلّصت الدرس — كمّل بهدوء، إنت ماشي صحّ.') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-1"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.positive.index.nbra_almnsa_mbsta_mhtrma_dafya_bla_mbalgha', 'نبرة المنصّة: مبسّطة محترمة دافئة — بلا مبالغة وبلا لوم للمستخدم (2.17-ج).') }}
                </p>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">{{ setting('admin.positive.index.rmz_sghyr_akhtyary', 'رمز صغير (اختياريّ)') }}
                        <input type="text" name="emoji" id="positive-emoji" maxlength="16"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.positive.index.altrtyb', 'الترتيب') }}
                        <input type="number" name="sort_order" id="positive-sort" min="0" max="9999" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm mb-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="positive-active" value="1" checked>
                    <span>{{ setting('admin.positive.index.mfala_tzhr_llmstkhdmyn', 'مفعّلة — تظهر للمستخدمين') }}</span>
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.positive.index.ahfz_alrsala', 'احفظ الرسالة') }}</button>
            </form>
        </x-modal>
    @endif

    @if ($canCreate)
        {{-- استيراد CSV: رفعٌ ثمّ معاينة صفًّا بصفّ قبل أيّ اعتماد (2.6-ب حرفًا) --}}
        <x-modal id="positive-import-modal" :title="setting('admin.positive.index.astyrad_csv', 'استيراد CSV')">
            <form method="post" action="{{ route('admin.positive.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="positive-import-file">{{ setting('admin.positive.index.mlf_csv', 'ملفّ CSV') }}</label>
                <input type="file" name="file" id="positive-import-file" accept=".csv,text/csv" required
                       class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <p class="text-xs mb-4" style="color: var(--text-muted)">
                    {{ setting('admin.positive.index.aamda_alml', 'الأعمدة: context, body, emoji, language, sort_order, is_active — السطر الأوّل عناوين.') }}
                </p>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.main_alsfwf', 'عاين الصفوف') }}</button>
            </form>
        </x-modal>
    @endif

    {{-- معاينة رسالةٍ محدَّدة فوريًّا — بلا رحلة خادم، نفس مكوّن المظروف (2.10.1-27) --}}
    <div id="positive-row-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
         style="background: rgb(0 0 0 / .6)" role="dialog" aria-modal="true"
         aria-label="{{ setting('admin.positive.index.mianah', 'معاينة') }}">
        <div class="modal-shell card w-full max-w-sm text-center p-6 animate-fadeup">
            <div class="envelope" aria-hidden="true">
                <div class="envelope-pocket"></div>
                <div class="envelope-letter-tab" data-row-preview-letter></div>
                <div class="envelope-flap" data-row-preview-flap></div>
            </div>

            <div class="rounded-2xl p-4 mt-4 text-sm" data-row-preview-message
                 style="background: var(--surface-sunken); border: 1px solid var(--border); opacity: 0">
                <div class="text-2xl mb-1" data-row-preview-emoji aria-hidden="true"></div>
                <p class="leading-relaxed" data-row-preview-body></p>
            </div>

            <button type="button" data-row-preview-close
                    class="btn rounded-xl px-4 py-2 text-sm motion-standard mt-5"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.tmam', 'تمام') }}</button>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('positive-modal');
            if (!modal) return;

            const form = modal.querySelector('[data-positive-form]');
            const method = modal.querySelector('[data-positive-method]');
            const storeUrl = @json(route('admin.positive.store'));

            const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };

            document.querySelectorAll('[data-positive-new]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = storeUrl;
                    method.value = 'POST';
                    form.querySelector('#positive-body').value = '';
                    form.querySelector('#positive-emoji').value = '';
                    form.querySelector('#positive-sort').value = '0';
                    form.querySelector('#positive-active').checked = true;
                    form.querySelector('#positive-language').value = 'ar';
                });
            });

            document.querySelectorAll('[data-positive-edit]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = btn.dataset.action;
                    method.value = 'PUT';
                    form.querySelector('#positive-context').value = btn.dataset.context;
                    form.querySelector('#positive-body').value = btn.dataset.body;
                    form.querySelector('#positive-emoji').value = btn.dataset.emoji || '';
                    form.querySelector('#positive-sort').value = btn.dataset.sort || '0';
                    form.querySelector('#positive-active').checked = btn.dataset.active === '1';
                    form.querySelector('#positive-language').value = btn.dataset.language || 'ar';
                    open();
                });
            });
        })();

        (() => {
            /* معاينة صفّ فوريّة: نفس حركتَي المظروف الحقيقيّتين (flap/letter)
               بلا رحلة خادم — الرسالة معروضةٌ في الجدول أصلًا (2.17-أ). */
            const modal = document.getElementById('positive-row-preview-modal');
            if (!modal) return;

            const flap = modal.querySelector('[data-row-preview-flap]');
            const letter = modal.querySelector('[data-row-preview-letter]');
            const message = modal.querySelector('[data-row-preview-message]');
            const emojiEl = modal.querySelector('[data-row-preview-emoji]');
            const bodyEl = modal.querySelector('[data-row-preview-body]');

            const open = (emoji, body) => {
                emojiEl.textContent = emoji || '';
                emojiEl.hidden = !emoji;
                bodyEl.textContent = body;

                modal.classList.remove('hidden');
                modal.classList.add('flex');

                flap?.classList.add('animate-flap');
                letter?.classList.add('animate-letter');

                if (message) {
                    message.style.transition = 'opacity 300ms var(--ease-standard) 550ms';
                    message.style.opacity = '1';
                }
            };

            const close = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');

                flap?.classList.remove('animate-flap');
                letter?.classList.remove('animate-letter');

                if (message) {
                    message.style.transition = '';
                    message.style.opacity = '0';
                }
            };

            document.querySelectorAll('[data-positive-row-preview]').forEach((btn) => {
                btn.addEventListener('click', () => open(btn.dataset.emoji, btn.dataset.body));
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.closest('[data-row-preview-close]')) close();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        })();

        {{-- معاينة الاستيراد تصل من الخادم — نزول تلقائيّ لها فور ظهورها --}}
        @if (session('importPreviewRows'))
            document.getElementById('positive-import-preview')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        @endif
    </script>
@endpush
