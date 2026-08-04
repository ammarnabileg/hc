{{-- 1) الاعتمادات (12.5-أ): اعتماد المنصّة ثابت ولا يُحذَف --}}
<div class="flex justify-end mb-3">
    @can('accreditations.create')
        <button type="button" data-modal-open="accreditation-form"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.accreditations.aatmad', '+ اعتماد') }}</button>
    @endcan
</div>

<div class="space-y-3">
    @foreach ($accreditations as $accreditation)
        <div class="card p-4 flex items-start gap-3">
            @if ($accreditation->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($accreditation->logo_path) }}"
                     alt="{{ $accreditation->name_ar }}" loading="lazy" class="w-12 h-12 object-contain rounded-lg">
            @else
                <div class="w-12 h-12 rounded-lg grid place-items-center" style="background: var(--surface-sunken)"
                     aria-hidden="true"><x-icon name="entity" size="16" /></div>
            @endif

            <div class="flex-1 min-w-0">
                <div class="font-semibold flex items-center gap-2">
                    {{ $accreditation->name_ar }}
                    @if ($accreditation->is_platform)
                        <span class="text-xs" title="{{ setting('admin.certificates.partials.accreditations.aatmad_almnsa_thabt_wla_ytshal', 'اعتماد المنصّة — ثابت ولا يتشال') }}" aria-label="{{ setting('admin.certificates.partials.accreditations.mqfwl', 'مقفول') }}"><x-icon name="lock" size="16" /></span>
                    @endif
                </div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $typeCounts[$accreditation->id] ?? 0 }} {!! strtr(setting('admin.certificates.partials.accreditations.nwa_shhada_v1_shhada_sadra', 'نوع شهادة · :v1 شهادة صادرة'), [':v1' => e($issuedCounts[$accreditation->id] ?? 0)]) !!}
                </div>
                @if ($accreditation->verify_note_ar)
                    <div class="text-xs mt-1">{{ setting('admin.certificates.partials.accreditations.fy_sfha_althqq', 'في صفحة التحقّق: «') }}{{ $accreditation->verify_note_ar }}»</div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-state-badge :state="$accreditation->is_active ? 'ok' : 'idle'"
                               :label="$accreditation->is_active ? setting('admin.certificates.partials.accreditations.nsht', 'نشط') : setting('admin.certificates.partials.accreditations.matl', 'معطّل')" />

                @can('accreditations.delete')
                    @unless ($accreditation->is_platform)
                        <form method="post" action="{{ route('admin.certificates.accreditations.destroy', $accreditation) }}"
                              onsubmit="return confirm('{{ setting('admin.certificates.partials.accreditations.nshyl_alaatmad_dh', 'نشيل الاعتماد ده؟') }}')">
                            @csrf @method('delete')
                            <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.certificates.partials.accreditations.hdhf', 'حذف') }}</button>
                        </form>
                    @endunless
                @endcan
            </div>
        </div>
    @endforeach
</div>

@can('accreditations.create')
    <x-modal id="accreditation-form" :title="setting('admin.certificates.partials.accreditations.aatmad_jdyd', 'اعتماد جديد')">
        <form method="post" action="{{ route('admin.certificates.accreditations.store') }}" class="space-y-3">
            @csrf
            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="name_ar" :label="setting('admin.certificates.partials.accreditations.alasm_arby', 'الاسم (عربيّ)')" required />
                <x-form.input name="name_en" :label="setting('admin.certificates.partials.accreditations.alasm_injlyzy', 'الاسم (إنجليزيّ)')" required />
            </div>
            <x-form.input name="logo_path" :label="setting('admin.certificates.partials.accreditations.alshaar_msar_mn_mktba_alwsayt', 'الشعار (مسار من مكتبة الوسائط)')" />
            <x-form.input name="verify_note_ar" :label="setting('admin.certificates.partials.accreditations.ns_sfha_althqq_arby', 'نصّ صفحة التحقّق (عربيّ)')"
                          :value="setting('certificates.accreditation.verify_note_ar', 'معتمدة من')" />
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked> {{ setting('admin.certificates.partials.accreditations.nsht', 'نشط') }}
            </label>
            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.accreditations.hfz', 'حفظ') }}</button>
        </form>
    </x-modal>
@endcan
