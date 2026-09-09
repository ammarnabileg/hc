{{--
    1) الاعتمادات (12.5-أ · 24.1 سطر 4619-4622): جدولٌ حقيقيّ — الشعار · الاسم ·
    كم نوع شهادة يستخدمه (الضغط ← الأنواع المفلترة) · عدد الشهادات الصادرة ·
    الحالة، ببحثٍ وفلاتر. اعتماد المنصّة ثابتٌ لا يُحذَف.
--}}
<x-filters :action="route('admin.certificates.index')">
    <input type="hidden" name="tab" value="accreditations">

    <label class="block flex-1 min-w-[12rem]">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.accreditations.bhth', 'بحث') }}</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.certificates.partials.accreditations.balasm', 'بالاسم…') }}"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.accreditations.nsht', 'نشط') }}</span>
        <select name="status" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
            <option value="active" @selected($filters['status'] === 'active')>{{ setting('admin.certificates.partials.accreditations.nsht', 'نشط') }}</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>{{ setting('admin.certificates.partials.accreditations.matl', 'معطّل') }}</option>
        </select>
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.accreditations.alastkhdam', 'الاستخدام') }}</span>
        <select name="used" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('admin.certificates.partials.ledger.alkl', 'الكلّ') }}</option>
            <option value="used" @selected($filters['used'] === 'used')>{{ setting('admin.certificates.partials.accreditations.mstkhdm', 'مستخدَم') }}</option>
            <option value="unused" @selected($filters['used'] === 'unused')>{{ setting('admin.certificates.partials.accreditations.ghyr_mstkhdm', 'غير مستخدَم') }}</option>
        </select>
    </label>

    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.certificates.partials.ledger.tsfya', 'تصفية') }}</button>

    @can('accreditations.create')
        <button type="button" data-modal-open="accreditation-form"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.accreditations.aatmad', '+ اعتماد') }}</button>
    @endcan
</x-filters>

@if ($accreditations->isEmpty())
    <x-empty :message="setting('admin.certificates.partials.accreditations.mfysh_aatmadat_fy_alflatr_dy', 'مفيش اعتمادات في الفلاتر دي.')" />
@else
    <div class="card p-2">
        <x-table :label="setting('admin.certificates.partials.accreditations.alaatmadat', 'الاعتمادات')">
            <thead>
                <tr style="border-bottom: 1px solid var(--border)">
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.alshaar', 'الشعار') }}</th>
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.alasm', 'الاسم') }}</th>
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.km_nwa', 'كم نوع') }}</th>
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.shhadat_sadra', 'شهادات صادرة') }}</th>
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.alhala', 'الحالة') }}</th>
                    <th class="p-3 text-start">{{ setting('admin.certificates.partials.accreditations.ijraat', 'إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accreditations as $accreditation)
                    <tr style="border-bottom: 1px solid var(--border)">
                        <td class="p-3">
                            @if ($accreditation->logo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($accreditation->logo_path) }}"
                                     alt="{{ $accreditation->name_ar }}" loading="lazy" class="w-10 h-10 object-contain rounded-lg">
                            @else
                                <div class="w-10 h-10 rounded-lg grid place-items-center" style="background: var(--surface-sunken)"
                                     aria-hidden="true"><x-icon name="entity" size="16" /></div>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="font-semibold flex items-center gap-2">
                                {{ $accreditation->name_ar }}
                                @if ($accreditation->is_platform)
                                    <span title="{{ setting('admin.certificates.partials.accreditations.aatmad_almnsa_thabt_wla_ytshal', 'اعتماد المنصّة — ثابت ولا يتشال') }}" aria-label="{{ setting('admin.certificates.partials.accreditations.mqfwl', 'مقفول') }}"><x-icon name="lock" size="14" /></span>
                                @endif
                            </div>
                            @if ($accreditation->verify_note_ar)
                                <div class="text-xs mt-0.5" style="color: var(--text-muted)">{{ setting('admin.certificates.partials.accreditations.fy_sfha_althqq', 'في صفحة التحقّق: «') }}{{ $accreditation->verify_note_ar }}»</div>
                            @endif
                        </td>
                        <td class="p-3">
                            <a href="{{ route('admin.certificates.index', ['tab' => 'types', 'accreditation_id' => $accreditation->id]) }}" class="underline">
                                {{ $typeCounts[$accreditation->id] ?? 0 }}
                            </a>
                        </td>
                        <td class="p-3">{{ $issuedCounts[$accreditation->id] ?? 0 }}</td>
                        <td class="p-3">
                            <x-state-badge :state="$accreditation->is_active ? 'ok' : 'idle'"
                                           :label="$accreditation->is_active ? setting('admin.certificates.partials.accreditations.nsht', 'نشط') : setting('admin.certificates.partials.accreditations.matl', 'معطّل')" />
                        </td>
                        <td class="p-3">
                            <div class="flex items-center gap-2 text-xs">
                                @can('accreditations.edit')
                                    <button type="button" class="underline" data-modal-open="accreditation-edit-{{ $accreditation->id }}">{{ setting('admin.certificates.partials.accreditations.tadyl', 'تعديل') }}</button>

                                    <form method="post" action="{{ route('admin.certificates.accreditations.update', $accreditation) }}">
                                        @csrf @method('put')
                                        <input type="hidden" name="name_ar" value="{{ $accreditation->name_ar }}">
                                        <input type="hidden" name="name_en" value="{{ $accreditation->name_en }}">
                                        <input type="hidden" name="logo_path" value="{{ $accreditation->logo_path }}">
                                        <input type="hidden" name="verify_note_ar" value="{{ $accreditation->verify_note_ar }}">
                                        <input type="hidden" name="verify_note_en" value="{{ $accreditation->verify_note_en }}">
                                        <input type="hidden" name="is_active" value="{{ $accreditation->is_active ? '0' : '1' }}">
                                        <button class="underline">{{ $accreditation->is_active ? setting('admin.certificates.partials.accreditations.matl', 'معطّل') : setting('admin.certificates.partials.accreditations.nsht', 'نشط') }}</button>
                                    </form>
                                @endcan

                                @can('accreditations.delete')
                                    @unless ($accreditation->is_platform)
                                        <form method="post" action="{{ route('admin.certificates.accreditations.destroy', $accreditation) }}"
                                              onsubmit="return confirm('{{ setting('admin.certificates.partials.accreditations.nshyl_alaatmad_dh', 'نشيل الاعتماد ده؟') }}')">
                                            @csrf @method('delete')
                                            <button class="underline" style="color: var(--color-state-danger)">{{ setting('admin.certificates.partials.accreditations.hdhf', 'حذف') }}</button>
                                        </form>
                                    @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </div>

    @can('accreditations.edit')
        @foreach ($accreditations as $accreditation)
            <x-modal :id="'accreditation-edit-'.$accreditation->id" :title="$accreditation->name_ar">
                <form method="post" action="{{ route('admin.certificates.accreditations.update', $accreditation) }}" class="space-y-3">
                    @csrf @method('put')
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-form.input name="name_ar" :label="setting('admin.certificates.partials.accreditations.alasm_arby', 'الاسم (عربيّ)')" :value="$accreditation->name_ar" required />
                        <x-form.input name="name_en" :label="setting('admin.certificates.partials.accreditations.alasm_injlyzy', 'الاسم (إنجليزيّ)')" :value="$accreditation->name_en" required />
                    </div>
                    <x-form.input name="logo_path" :label="setting('admin.certificates.partials.accreditations.alshaar_msar_mn_mktba_alwsayt', 'الشعار (مسار من مكتبة الوسائط)')" :value="$accreditation->logo_path" />
                    <x-form.input name="verify_note_ar" :label="setting('admin.certificates.partials.accreditations.ns_sfha_althqq_arby', 'نصّ صفحة التحقّق (عربيّ)')" :value="$accreditation->verify_note_ar" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($accreditation->is_active)> {{ setting('admin.certificates.partials.accreditations.nsht', 'نشط') }}
                    </label>
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.accreditations.hfz', 'حفظ') }}</button>
                </form>
            </x-modal>
        @endforeach
    @endcan
@endif

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
