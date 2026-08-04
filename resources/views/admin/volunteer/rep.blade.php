@extends('layouts.admin')

@section('title', setting('admin.volunteer.rep.dbt_rep', 'ضبط Rep'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.rep.dbt_rep', 'ضبط Rep')"
        :subtitle="setting('admin.volunteer.rep.kl_qyma_fy_jdwl_drja_alaltzam_tadl_mn_hna', 'كلّ قيمة في جدول درجة الالتزام تُعدَّل من هنا — ولكلّ قيمة رجوعٌ لافتراضيّها.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.rep.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.rep.dbt_rep', 'ضبط Rep')]]">
        <x-slot:action>
            @can('rep_manual.create')
                <button type="button" data-modal-open="behavior-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.rep.maamla_slwk', 'معاملة سلوك') }}</button>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none" style="background: var(--surface-raised)" aria-label="{{ setting('admin.volunteer.rep.khyarat_akhra', 'خيارات أخرى') }}">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @can('volunteer_central_settings.edit')
                        <button type="button" data-modal-open="violation-modal" class="block w-full text-start rounded-lg px-3 py-2 hover:opacity-80">{{ setting('admin.volunteer.rep.mkhalfa_mkwda_2', '+ مخالفة مكوَّدة') }}</button>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'rep'])

    {{-- قيم Rep مجمَّعة: مجموعة لكلّ سكشن مطويّ (2.15-ب) --}}
    @can('volunteer_central_settings.manage')
        {{--
          فورم الـReset منفصل خارج فورم الحفظ (الفورمات لا تتداخل)،
          وأزرار الصفوف تشير إليه بـ`form=` فيمرّ مفتاح القيمة مع الضغطة.
        --}}
        <form method="post" action="{{ route('admin.volunteer.rep.rules.reset') }}" id="rep-reset-form" class="hidden">
            @csrf
        </form>
    @endcan

    @can('volunteer_central_settings.edit')
        <form method="post" action="{{ route('admin.volunteer.rep.rules.save') }}">
            @csrf
            @foreach ($groups as $groupKey => $groupLabel)
                <details class="card p-4 md:p-5 mb-3" @if ($loop->first) open @endif>
                    <summary class="cursor-pointer font-bold select-none">{{ $groupLabel }}</summary>

                    <div class="mt-3">
                        @foreach ($rules->get($groupKey, collect()) as $rule)
                            @php $default = $defaults[$rule->key] ?? null; @endphp
                            <div class="flex items-center justify-between gap-3 flex-wrap py-2 {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold">
                                        {{ $rule->label_ar }}
                                        @if ($default !== null && abs((float) $rule->value - (float) $default) > 0.0001)
                                            <span class="text-xs rounded-full px-2 py-0.5"
                                                  style="background: color-mix(in srgb, var(--color-state-warn) 15%, transparent); color: var(--color-state-warn)">{{ setting('admin.volunteer.rep.madl', '▲ معدَّل') }}</span>
                                        @endif
                                    </div>
                                    <code class="text-xs" style="color: var(--text-muted)">{{ $rule->key }}</code>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="number" step="0.05" name="rules[{{ $rule->key }}]" value="{{ (float) $rule->value }}"
                                           aria-label="{{ $rule->label_ar }}"
                                           class="w-28 rounded-xl px-3 py-2 text-sm text-center"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    {{-- ↺ Reset لهذه القيمة وحدها إلى افتراضيّها المنصوص --}}
                                    @can('volunteer_central_settings.manage')
                                        <button type="submit" form="rep-reset-form" name="key" value="{{ $rule->key }}"
                                                class="text-xs underline" style="color: var(--text-muted)"
                                                title="{{ setting('admin.volunteer.rep.rja_llaftrady', 'رجّع للافتراضيّ') }}"><x-icon name="refresh" size="16" /> {{ $default !== null ? (float) $default : '—' }}</button>
                                    @else
                                        <span class="text-xs" style="color: var(--text-muted)" title="{{ setting('admin.volunteer.rep.alqyma_alaftradya', 'القيمة الافتراضيّة') }}"><x-icon name="refresh" size="16" /> {{ $default !== null ? (float) $default : '—' }}</span>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach

            <div class="flex items-center gap-2">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.rep.ahfz_qym_rep', 'احفظ قيم Rep') }}</button>
                <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.volunteer.rep.alqym_aljdyda_tsry_fwra_ala_almnsa_klha', 'القيم الجديدة تسري فورًا على المنصّة كلّها.') }}</span>
            </div>
        </form>

        {{-- ↺ Reset لكلّ مجموعة على حدة --}}
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach ($groups as $groupKey => $groupLabel)
                <form method="post" action="{{ route('admin.volunteer.rep.rules.reset_group') }}"
                      onsubmit="return confirm('{{ strtr(setting('admin.volunteer.rep.trja_llaftrady', 'ترجّع «:group» للافتراضيّ؟'), [':group' => $groupLabel]) }}')">
                    @csrf
                    <input type="hidden" name="group" value="{{ $groupKey }}">
                    <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> {{ $groupLabel }}</button>
                </form>
            @endforeach
        </div>
    @endcan

    {{-- قائمة المخالفات المكوَّدة — لا نصّ حرّ ليبقى التصنيف قابلًا للتحليل --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.rep.almkhalfat_almkwda', 'المخالفات المكوَّدة') }}</h2>

        @forelse ($violations as $violation)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $violation->code }} · {{ $violation->label_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ (float) $violation->default_value }}
                        @if ($violation->requires_higher_approval) {{ setting('admin.volunteer.rep.thtaj_mwafqa_mstwa_aala', '· تحتاج موافقة مستوى أعلى') }} @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-state-badge :state="$violation->is_active ? 'ok' : 'idle'" :label="$violation->is_active ? setting('admin.volunteer.rep.mfala', 'مفعّلة') : setting('admin.volunteer.rep.mwqwfa', 'موقوفة')" />
                    @can('volunteer_central_settings.edit')
                        <button type="button" class="text-xs underline" data-violation-edit
                                data-id="{{ $violation->id }}" data-code="{{ $violation->code }}"
                                data-label="{{ $violation->label_ar }}" data-value="{{ (float) $violation->default_value }}"
                                data-approval="{{ $violation->requires_higher_approval ? 1 : 0 }}">{{ setting('admin.volunteer.rep.tadyl', 'تعديل') }}</button>
                    @endcan
                </div>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.rep.mfysh_mkhalfat_mkwda_lsh_adf_awl_wahda', 'مفيش مخالفات مكوَّدة لسّه — أضف أوّل واحدة.')" />
        @endforelse
    </section>

    {{-- آخر معاملات السلوك + رقابة المانح --}}
    <section class="card p-4 md:p-5 mt-4">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
            <h2 class="font-bold">{{ setting('admin.volunteer.rep.akhr_maamlat_alslwk', 'آخر معاملات السلوك') }}</h2>
            <span class="text-xs" style="color: var(--text-muted)">
                {{ setting('admin.volunteer.rep.sqfk_alshhry', 'سقفك الشهريّ:') }} {{ $myQuota === null ? setting('admin.volunteer.rep.bla_sqf', 'بلا سقف') : $myQuota.setting('admin.volunteer.rep.mn', ' من ').$monthlyCap }}
            </span>
        </div>

        @forelse ($recent as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row->user?->name }} — {{ $row->behavior_violation?->label_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ setting('admin.volunteer.rep.bmarfa', 'بمعرفة') }} {{ $row->granted_by?->name }} · {{ \Illuminate\Support\Str::limit($row->justification, 70) }}
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="font-bold" style="color: var(--color-state-danger)">{{ (float) $row->value }}</span>
                    @if ($row->status === 'pending_approval')
                        <x-state-badge state="warn" :label="setting('admin.volunteer.rep.bantzar_mwafqa', 'بانتظار موافقة')" />
                        @can('rep_manual.approve')
                            <form method="post" action="{{ route('admin.volunteer.rep.behavior.approve', $row) }}">
                                @csrf
                                <button type="submit" class="text-xs underline">{{ setting('admin.volunteer.rep.aatmd', 'اعتمد') }}</button>
                            </form>
                        @endcan
                    @else
                        <x-state-badge state="ok" :label="setting('admin.volunteer.rep.mtbqa', 'مطبَّقة')" />
                    @endif
                </div>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.rep.mfysh_maamlat_slwk_wdh_mwshr_kwys', 'مفيش معاملات سلوك — وده مؤشّر كويّس.')" />
        @endforelse
    </section>

    @can('volunteer_central_settings.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.volunteer.rep.hdwd_rep_walslwk_waltsfyr_alshhry', 'حدود Rep والسلوك والتصفير الشهريّ'),
            'rows' => $settings,
            'action' => route('admin.volunteer.rep.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_rep'),
        ])
    @endcan
@endsection

@section('mobile_action')
    @can('rep_manual.create')
        <button type="button" data-modal-open="behavior-modal"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.rep.maamla_slwk', 'معاملة سلوك') }}</button>
    @endcan
@endsection

@push('modals')
    @can('rep_manual.create')
        <x-modal id="behavior-modal" :title="setting('admin.volunteer.rep.maamla_slwk_bmbrr_ilzamy', 'معاملة سلوك — بمبرّر إلزاميّ')">
            <form method="post" action="{{ route('admin.volunteer.rep.behavior.record') }}">
                @csrf

                <label class="block text-sm font-semibold mb-1" for="bh-code">{{ setting('admin.volunteer.rep.kwd_almttwa', 'كود المتطوّع') }}</label>
                <input type="text" name="code" id="bh-code" required maxlength="32"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="bh-violation">{{ setting('admin.volunteer.rep.nwa_almkhalfa', 'نوع المخالفة') }}</label>
                <select name="violation_id" id="bh-violation" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($violations->where('is_active', true) as $violation)
                        <option value="{{ $violation->id }}">{{ $violation->code }} · {{ $violation->label_ar }} ({{ (float) $violation->default_value }})</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="bh-justification">{{ setting('admin.volunteer.rep.almbrr_ilzamy', 'المبرّر (إلزاميّ)') }}</label>
                <textarea name="justification" id="bh-justification" rows="3" required maxlength="1000"
                          placeholder="{{ setting('admin.volunteer.rep.aktb_alwaqaa_bwdwh_alns_dh_hywsl_lladw_kma', 'اكتب الواقعة بوضوح — النصّ ده هيوصل للعضو كما هو.') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                {{-- معاينة الأثر قبل الحفظ: «Rep ينزل من كذا إلى كذا» --}}
                <button type="button" id="bh-preview-btn" class="text-xs underline mb-2">{{ setting('admin.volunteer.rep.aayn_alathr_qbl_alhfz', 'عاين الأثر قبل الحفظ') }}</button>
                <div id="bh-preview" class="text-sm rounded-xl p-3 mb-3 hidden" style="background: var(--surface-sunken)"></div>

                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    {!! strtr(setting('admin.volunteer.rep.sqfk_alshhry_v1_almkhalfa_aljsyma_tntzr', 'سقفك الشهريّ: :v1 · المخالفة الجسيمة تنتظر موافقة مستوى أعلى خلال :v2 ساعة.'), [':v1' => e($myQuota === null ? setting('admin.volunteer.rep.bla_sqf', 'بلا سقف') : $myQuota.setting('admin.volunteer.rep.mn', ' من ').$monthlyCap), ':v2' => e(setting('rep.behavior.severe_approval_window_hours', 24))]) !!}
                </p>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.rep.sjl_almaamla', 'سجّل المعاملة') }}</button>
            </form>
        </x-modal>
    @endcan

    @can('volunteer_central_settings.edit')
        <x-modal id="violation-modal" :title="setting('admin.volunteer.rep.mkhalfa_mkwda', 'مخالفة مكوَّدة')">
            <form method="post" action="{{ route('admin.volunteer.rep.violations.save') }}">
                @csrf
                <input type="hidden" name="id" id="vio-id">

                <label class="block text-sm font-semibold mb-1" for="vio-code">{{ setting('admin.volunteer.rep.alkwd', 'الكود') }}</label>
                <input type="text" name="code" id="vio-code" required maxlength="32"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="vio-label">{{ setting('admin.volunteer.rep.alwsf', 'الوصف') }}</label>
                <input type="text" name="label_ar" id="vio-label" required maxlength="180"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="vio-value">{{ setting('admin.volunteer.rep.alqyma', 'القيمة') }}</label>
                <select name="default_value" id="vio-value" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="{{ rep_rule('behavior.warning', -0.5) }}">{{ setting('admin.volunteer.rep.tnbyh_mwthq', 'تنبيه موثَّق (') }}{{ rep_rule('behavior.warning', -0.5) }})</option>
                    <option value="{{ rep_rule('behavior.severe', -1) }}">{{ setting('admin.volunteer.rep.mkhalfa_jsyma', 'مخالفة جسيمة (') }}{{ rep_rule('behavior.severe', -1) }})</option>
                </select>

                <label class="flex items-center gap-2 text-sm mb-3">
                    <input type="checkbox" name="requires_higher_approval" id="vio-approval" value="1">
                    {{ setting('admin.volunteer.rep.thtaj_mwafqa_mstwa_aala_2', 'تحتاج موافقة مستوى أعلى') }}
                </label>

                <input type="hidden" name="is_active" value="1">

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.rep.ahfz', 'احفظ') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    @php
        /*
         | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
         | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
         */
        $jsText = [
            'calculating' => setting('admin.volunteer.rep.bnhsb', 'بنحسب…'),
            'preview_line' => setting('admin.volunteer.rep.drja_alaltzam_htnzl_mn_ila', ':name: درجة الالتزام هتنزل من :before إلى :after.'),
            'preview_failed' => setting('admin.volunteer.rep.tadhrt_almaayna_tqdr_tkml_alhfz_aady', 'تعذّرت المعاينة — تقدر تكمّل الحفظ عادي.'),
        ];
    @endphp

    <script>
        const HC_REP_TEXT = @json($jsText);
        // معاينة الأثر: ردّ فوريّ بلا مغادرة الشاشة (2.17-ب)
        const previewBtn = document.getElementById('bh-preview-btn');
        if (previewBtn) {
            previewBtn.addEventListener('click', async () => {
                const box = document.getElementById('bh-preview');
                box.classList.remove('hidden');
                box.textContent = HC_REP_TEXT.calculating;

                try {
                    const res = await fetch('{{ route('admin.volunteer.rep.behavior.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({
                            code: document.getElementById('bh-code').value,
                            violation_id: document.getElementById('bh-violation').value,
                        }),
                    });
                    const data = await res.json();
                    box.textContent = data.ok
                        ? HC_REP_TEXT.preview_line
                            .replace(':name', data.name)
                            .replace(':before', data.preview.before)
                            .replace(':after', data.preview.after)
                        : data.message;
                } catch {
                    box.textContent = HC_REP_TEXT.preview_failed;
                }
            });
        }

        document.querySelectorAll('[data-violation-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('vio-id').value = btn.dataset.id;
                document.getElementById('vio-code').value = btn.dataset.code;
                document.getElementById('vio-label').value = btn.dataset.label;
                document.getElementById('vio-approval').checked = btn.dataset.approval === '1';
                const modal = document.getElementById('violation-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
