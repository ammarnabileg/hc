@extends('layouts.admin')

@section('title', setting('admin.task_types.index.anwaa_almham', 'أنواع المهامّ'))

@section('content')
    {{-- 23-0.3: النوع وسم وقالب فقط — نفس الحالات ونفس جدول Rep ونفس محرّك التصعيد --}}
    <x-page-header :title="setting('admin.task_types.index.anwaa_almham', 'أنواع المهامّ')"
                   :subtitle="setting('admin.task_types.index.qalb_ywfr_alktaba_tshyk_lyst_jahza_wbryf', 'قالب يوفّر الكتابة: تشيك ليست جاهزة وبريف وشكل مخرجات وقيم مقترحة — بلا أيّ تغيير في السلوك.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.task_types.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.task_types.index.anwaa_almham', 'أنواع المهامّ')],
                   ]">
        <x-slot:action>
            <a href="#type-form" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.task_types.index.nwa_jdyd', '+ نوع جديد') }}</a>
        </x-slot:action>
    </x-page-header>

    @if ($types->isEmpty())
        <x-empty :message="setting('admin.task_types.index.mfysh_anwaa_lsh_abda_bawl_nwa', 'مفيش أنواع لسه — ابدأ بأوّل نوع.')" :action="setting('admin.task_types.index.nwa_jdyd', '+ نوع جديد')" href="#type-form" />
    @else
        <div class="card overflow-hidden mb-5">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">{{ setting('admin.task_types.index.alnwa', 'النوع') }}</th>
                        <th class="text-start p-3">{{ setting('admin.task_types.index.altshyk_lyst', 'التشيك ليست') }}</th>
                        <th class="text-start p-3">{{ setting('admin.task_types.index.qym_mqtrha', 'قيم مقترحة') }}</th>
                        <th class="text-start p-3">{{ setting('admin.task_types.index.mham_alyh', 'مهامّ عليه') }}</th>
                        <th class="text-start p-3">{{ setting('admin.task_types.index.alhala', 'الحالة') }}</th>
                        <th class="text-start p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3">
                                <span aria-hidden="true">{{ $type->icon }}</span>
                                {{ $type->name_ar }}
                                <span class="text-xs font-mono" style="color: var(--text-muted)">{{ $type->key }}</span>
                            </td>
                            <td class="p-3 text-xs">{{ collect($type->checklist ?? [])->implode(' ⟵ ') ?: '—' }}</td>
                            <td class="p-3 text-xs">
                                {{ $type->default_vxp ? 'VXP '.rtrim(rtrim(number_format((float) $type->default_vxp, 2), '0'), '.') : 'VXP —' }}
                                · {{ $priorities[$type->default_priority] ?? '—' }}
                                · {{ $deliveryKinds[$type->default_delivery_kind] ?? '—' }}
                            </td>
                            <td class="p-3">{{ (int) ($usage[$type->id] ?? 0) }}</td>
                            <td class="p-3">
                                <x-state-badge :state="$type->is_active ? 'ok' : 'idle'"
                                               :label="$type->is_active ? setting('admin.task_types.index.shghal', 'شغّال') : setting('admin.task_types.index.mtwqf', 'متوقّف')" />
                            </td>
                            <td class="p-3 text-xs">
                                <a href="{{ route('admin.volunteer.task-types.index', ['edit' => $type->id]) }}#type-form">{{ setting('admin.task_types.index.tadyl_2', 'تعديل') }}</a>
                                <form method="post" action="{{ route('admin.volunteer.task-types.toggle', $type) }}" class="inline">
                                    @csrf
                                    <button type="submit" style="color: var(--text-muted)">
                                        {{ $type->is_active ? setting('admin.task_types.index.iyqaf', 'إيقاف') : setting('admin.task_types.index.tshghyl', 'تشغيل') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (دليل البناء 4) --}}
            <div class="md:hidden">
                @foreach ($types as $type)
                    <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                        <div class="flex items-center justify-between gap-2">
                            <span>{{ $type->icon }} {{ $type->name_ar }}</span>
                            <x-state-badge :state="$type->is_active ? 'ok' : 'idle'"
                                           :label="$type->is_active ? setting('admin.task_types.index.shghal', 'شغّال') : setting('admin.task_types.index.mtwqf', 'متوقّف')" />
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ collect($type->checklist ?? [])->implode(' ⟵ ') ?: setting('admin.task_types.index.bla_tshyk_lyst', 'بلا تشيك ليست') }}
                        </p>
                        <a class="text-xs" href="{{ route('admin.volunteer.task-types.index', ['edit' => $type->id]) }}#type-form">{{ setting('admin.task_types.index.tadyl_2', 'تعديل') }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form id="type-form" method="post" action="{{ route('admin.volunteer.task-types.save') }}" class="card p-4 space-y-3">
        @csrf
        <input type="hidden" name="id" value="{{ $editing?->id }}">

        <h2 class="font-bold text-sm">{{ $editing ? setting('admin.task_types.index.tadyl', 'تعديل: ').$editing->name_ar : setting('admin.task_types.index.nwa_jdyd_2', 'نوع جديد') }}</h2>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-form.input name="name_ar" :label="setting('admin.task_types.index.alasm', 'الاسم')" :value="$editing?->name_ar" required />
            <x-form.input name="key" :label="setting('admin.task_types.index.almftah_hrwf_injlyzya_sghyra_w', 'المفتاح (حروف إنجليزيّة صغيرة و_)')" :value="$editing?->key" required />
            <x-form.input name="icon" :label="setting('admin.task_types.index.alayqwna', 'الأيقونة')" :value="$editing?->icon" />
            <x-form.input name="default_vxp" :label="setting('admin.task_types.index.vxp_mqtrh', 'VXP مقترَح')" type="number" :value="$editing?->default_vxp" />
        </div>

        <label class="block text-sm">
            <span class="block mb-1">{{ setting('admin.task_types.index.hykl_albryf_almqtrh', 'هيكل البريف المقترح') }}</span>
            <textarea name="default_brief" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $editing?->default_brief }}</textarea>
        </label>

        <label class="block text-sm">
            <span class="block mb-1">{{ setting('admin.task_types.index.shkl_almkhrjat_alnmwdhjy', 'شكل المخرجات النموذجيّ') }}</span>
            <textarea name="default_deliverable_spec" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $editing?->default_deliverable_spec }}</textarea>
        </label>

        <label class="block text-sm">
            <span class="block mb-1">{{ setting('admin.task_types.index.altshyk_lyst_bnd_fy_kl_str', 'التشيك ليست — بند في كلّ سطر') }}</span>
            <textarea name="checklist" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ collect($editing?->checklist ?? [])->implode("\n") }}</textarea>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.task_types.index.alawlwya_almqtrha', 'الأولويّة المقترحة') }}</span>
                <select name="default_priority" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">—</option>
                    @foreach ($priorities as $key => $label)
                        <option value="{{ $key }}" @selected($editing?->default_priority === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="block mb-1">{{ setting('admin.task_types.index.nwa_altslym_almqtrh', 'نوع التسليم المقترح') }}</span>
                <select name="default_delivery_kind" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">—</option>
                    @foreach ($deliveryKinds as $key => $label)
                        <option value="{{ $key }}" @selected($editing?->default_delivery_kind === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.task_types.index.hfz', 'حفظ') }}</button>
    </form>
@endsection
