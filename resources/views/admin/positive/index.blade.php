@extends('layouts.admin')

@section('title', setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة'))

@php
    /**
     * إدارة مكتبة الرسائل الإيجابيّة (2.6-ب · 2.13).
     * سؤال واحد للشاشة: «إيه الرسائل اللي بتظهر ومتى؟» — والفعل الرئيسيّ واحد
     * (+ رسالة)، والباقي داخل كلّ صفّ. والجدول كروت رأسيّة على الموبايل
     * بلا أيّ تمرير أفقيّ (2.15-ج).
     */
    $canCreate = auth()->user()?->can('positive_messages.create');
    $canEdit = auth()->user()?->can('positive_messages.edit');
    $canDelete = auth()->user()?->can('positive_messages.delete');
    $canManage = auth()->user()?->can('positive_messages.manage');
@endphp

@section('content')
    <x-page-header :title="setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة')"
                   :subtitle="setting('admin.positive.index.klma_tshjya_fy_wqtha_bnbra_almnsa_wbla', 'كلمة تشجيع في وقتها — بنبرة المنصّة وبلا مبالغة، ولكلّ رسالة سياقها.')"
                   :breadcrumbs="[['label' => setting('admin.positive.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.positive.index.alrsayl_aliyjabya', 'الرسائل الإيجابيّة')]]">
        @if ($canCreate)
            <x-slot:action>
                <button type="button" data-modal-open="positive-modal" data-positive-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.positive.index.rsala', '+ رسالة') }}</button>
            </x-slot:action>
        @endif
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.positive.index.kl_alrsayl', 'كلّ الرسائل')" :value="$counts['total']" icon="envelope" />
        <x-kpi :label="setting('admin.positive.index.almfala', 'المفعّلة')" :value="$counts['active']" icon="check" />
        <x-kpi :label="setting('admin.positive.index.alsyaqat', 'السياقات')" :value="$counts['contexts']" icon="compass" />
        <x-kpi :label="setting('admin.positive.index.mrat_alzhwr', 'مرّات الظهور')" :value="$counts['shown']" icon="eye" />
    </div>

    {{-- ثلاثة فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) --}}
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

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.fltra', 'فلترة') }}</button>
    </x-filters>

    @if ($messages->isEmpty())
        {{-- تمييز «لسّه مافيش رسائل أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
        <x-empty :message="setting('admin.positive.index.lsh_mafysh_rsayl_adf_awl_klma_tshjya_mn_zr', 'لسّه مافيش رسائل — أضف أوّل كلمة تشجيع من زرّ «+ رسالة» فوق.')"
                 :filtered="$context !== '' || $state !== ''" />
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($messages as $message)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs shrink-0"
                              style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $contexts[$message->context] ?? $message->context }}
                        </span>
                        <x-state-badge :state="$message->is_active ? 'ok' : 'idle'"
                                       :label="$message->is_active ? setting('admin.positive.index.mfala', 'مفعّلة') : setting('admin.positive.index.mwqwfa', 'موقوفة')" />
                    </div>

                    <p class="mt-3 text-sm break-words">
                        @if ($message->emoji)<span aria-hidden="true">{{ $message->emoji }}</span> @endif{{ $message->body_ar }}
                    </p>

                    <div class="mt-3 pt-3 flex flex-wrap items-center justify-between gap-2 text-xs"
                         style="border-top: 1px solid var(--border); color: var(--text-muted)">
                        <span>{!! strtr(setting('admin.positive.index.zhrt_v1_mra', 'ظهرت :v1 مرّة'), [':v1' => e($message->shown_count)]) !!}</span>

                        <span class="flex items-center gap-3">
                            @if ($canEdit)
                                <button type="button" class="underline" data-positive-edit
                                        data-id="{{ $message->id }}"
                                        data-action="{{ route('admin.positive.update', $message) }}"
                                        data-context="{{ $message->context }}"
                                        data-body="{{ $message->body_ar }}"
                                        data-emoji="{{ $message->emoji }}"
                                        data-sort="{{ $message->sort_order }}"
                                        data-active="{{ $message->is_active ? 1 : 0 }}">{{ setting('admin.positive.index.tadyl', 'تعديل') }}</button>

                                <form method="post" action="{{ route('admin.positive.toggle', $message) }}">
                                    @csrf
                                    <button type="submit" class="underline">{{ $message->is_active ? setting('admin.positive.index.awqf', 'أوقف') : setting('admin.positive.index.fal', 'فعّل') }}</button>
                                </form>
                            @endif

                            @if ($canDelete)
                                <form method="post" action="{{ route('admin.positive.destroy', $message) }}"
                                      onsubmit="return confirm('{{ setting('admin.positive.index.thdhf_alrsala_dy_nhayya', 'تحذف الرسالة دي نهائيًّا؟') }}')">
                                    @csrf @method('delete')
                                    <button type="submit" class="underline" style="color: var(--color-state-danger)">{{ setting('admin.positive.index.hdhf', 'حذف') }}</button>
                                </form>
                            @endif
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $messages->links() }}</div>
    @endif

    {{-- معاينة حيّة قبل ما يشوفها الناس (2.13-د) --}}
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
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.positive.index.ashb_rsala', 'اسحب رسالة') }}</button>
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.positive.index.btshwf_ally_hyshwfh_almstkhdm_balzbt', 'بتشوف اللي هيشوفه المستخدم بالظبط.') }}</span>
        </form>
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

                <label class="block text-sm font-semibold mb-1" for="positive-body">{{ setting('admin.positive.index.ns_alrsala', 'نصّ الرسالة') }}</label>
                <textarea name="body_ar" id="positive-body" required rows="3" maxlength="400"
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
                    open();
                });
            });
        })();
    </script>
@endpush
