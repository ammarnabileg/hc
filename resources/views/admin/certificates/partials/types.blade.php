{{-- 2) الأنواع والقوالب (12.5-ب) --}}
<div class="flex justify-between items-center gap-2 mb-3 flex-wrap">
    @can('certificate_templates.edit')
        {{-- ⭐ تفعيل اللغات مجمَّعًا لكلّ الشهادات — والإفراديّ داخل فورم النوع (12.5-ب) --}}
        <form method="post" action="{{ route('admin.certificates.types.languages') }}"
              class="card p-2 flex items-center gap-3 text-sm">
            @csrf
            <span>اللغات لكلّ الأنواع:</span>
            <label class="flex items-center gap-1">
                <input type="checkbox" name="lang_ar_enabled" value="1" checked> عربيّة
            </label>
            <label class="flex items-center gap-1">
                <input type="checkbox" name="lang_en_enabled" value="1"> إنجليزيّة
            </label>
            <button class="underline text-xs">طبّق</button>
        </form>
    @endcan

    @can('certificate_templates.create')
        <button type="button" data-modal-open="type-form"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ نوع شهادة</button>
    @endcan
</div>

@if ($types->isEmpty())
    <x-empty message="مفيش أنواع لسّه — ضيف أوّل نوع." />
@else
    <div class="space-y-3">
        @foreach ($types as $type)
            <div class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $type->name_ar }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            الاعتماد: {{ $type->accreditation?->name_ar ?? '—' }} ·
                            {{ $templateCounts[$type->id] ?? 0 }} قالب ·
                            {{ $issuedCounts[$type->id] ?? 0 }} صادرة ·
                            الترقيم: {{ $type->numbering_prefix ?: 'HC' }}-{{ now()->year }}-000001
                        </div>
                        <div class="text-xs mt-1">
                            اللغات:
                            {{ $type->lang_ar_enabled ? 'عربيّة' : '' }}
                            {{ $type->lang_en_enabled ? 'إنجليزيّة' : '' }}
                            {{ ! $type->lang_ar_enabled && ! $type->lang_en_enabled ? '— محدّش مفعّل' : '' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-state-badge :state="$type->auto_issue ? 'ok' : 'idle'"
                                       :label="$type->auto_issue ? 'إصدار تلقائيّ' : 'يدويّ'" />
                        @can('certificate_templates.view')
                            <a href="{{ route('admin.certificates.designer', $type) }}"
                               class="btn rounded-xl px-3 py-2 text-sm"
                               style="background: var(--color-brand-500); color: #04201c">مصمّم القالب</a>
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@can('certificate_templates.create')
    <x-modal id="type-form" title="نوع شهادة جديد">
        <form method="post" action="{{ route('admin.certificates.types.store') }}" class="space-y-3">
            @csrf

            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="name_ar" label="الاسم (عربيّ)" required />
                <x-form.input name="name_en" label="الاسم (إنجليزيّ)" required />
            </div>

            {{-- أهمّ حقل: اختلاف الاعتماد = شهادة مختلفة كليًّا (12.5-ب) --}}
            <label class="block">
                <span class="block text-sm mb-1">جهة الاعتماد</span>
                <select name="accreditation_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($accreditations as $accreditation)
                        <option value="{{ $accreditation->id }}">{{ $accreditation->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="numbering_prefix" label="بادئة الترقيم" placeholder="HC"
                              hint="الشكل: بادئة-سنة-تسلسل بلا فجوات." />
                <x-form.input name="numbering_padding" label="طول التسلسل" type="number"
                              :value="setting('certificates.numbering.padding', 6)" />
            </div>

            <div class="grid md:grid-cols-3 gap-3 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="lang_ar_enabled" value="1" checked> عربيّة
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="lang_en_enabled" value="1"> إنجليزيّة
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked> نشط
                </label>
            </div>

            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">خيارات متقدّمة</summary>
                <div class="mt-3 space-y-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="auto_issue" value="1"> إصدار تلقائيّ عند الحدث المرتبط
                    </label>
                    <x-form.input name="auto_issue_event" label="مفتاح الحدث" placeholder="course.completed" />

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="signature_enabled" value="1"> ختم/توقيع معتمِد
                    </label>
                    <x-form.input name="signature_path" label="مسار التوقيع" />
                    <x-form.input name="stamp_path" label="مسار الختم" />

                    {{-- الربط بأعمدة قاعدة البيانات بلا حدود — من قائمة آمنة (12.5-ب) --}}
                    <fieldset class="card p-3">
                        <legend class="text-sm px-1">الربط بقاعدة البيانات</legend>
                        @foreach ($bindableTables as $table => $meta)
                            @foreach ($meta['columns'] as $column => $label)
                                <label class="flex items-center gap-2 text-sm mt-1">
                                    <input type="checkbox" name="bindings[]" value="{{ $table }}.{{ $column }}">
                                    <span>{{ $meta['label'] }} ← {{ $label }}</span>
                                </label>
                            @endforeach
                        @endforeach
                    </fieldset>
                </div>
            </details>

            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">حفظ النوع</button>
        </form>
    </x-modal>
@endcan
