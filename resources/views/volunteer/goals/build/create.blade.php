@extends('layouts.volunteer')

@section('title', setting('volunteer.goals_build_create.title', 'هدف جديد'))

@php
    /**
     * ⭐ 1.1 إنشاء الهدف (23 — 1.1) — مشرف عام التطوّع وحده.
     *
     * الفورم تسعة حقول > الحدّ ⟵ **Stepper بحفظ تلقائيّ** بين الخطوات (2.15-ب)،
     * وكلّ الحقول تبقى في فورم واحد فلا يضيع حقل عند الإرسال.
     *
     * و**معيار التحقّق شرط الحفظ**: المطلوب هنا `required` في الواجهة للراحة —
     * أمّا الفرض الحقيقيّ فعلى الخادم في `GoalBuildService::assertCriteria`،
     * لأنّ الواجهة أوّل ما يُتخطّى.
     */
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.goals_build_create.title', 'هدف جديد')"
        :subtitle="setting('volunteer.goals_build_create.subtitle', 'الهدف أوّل ما يتحفظ مايشوفوش حدّ — بيظهر لحظة ما تربطه بمسار، فيوصل لمشرفي المسارات دول وحدهم.')"
        :breadcrumbs="[['label' => setting('volunteer.goals_build_create.label', 'رحلة بناء الهدف'), 'url' => route('volunteer.goals.build')], ['label' => setting('volunteer.goals_build_create.title', 'هدف جديد')]]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger)">
            <x-state-badge state="danger" :label="setting('volunteer.goals_build_create.label_2', 'مااتحفظش')" />
            <ul class="mt-2 space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('volunteer.goals.build.store') }}" class="card p-4">
        @csrf

        <x-form.stepper id="goal-create" :labels="[setting('volunteer.goals_build_create.labels', 'الهدف'), setting('volunteer.goals_build_create.labels_2', 'المعيار'), setting('volunteer.goals_build_create.labels_3', 'المسارات')]">
            <x-form.input name="name" :label="setting('volunteer.goals_build_create.label_3', 'اسم الهدف')" required />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.goals_build_create.field', 'وصف الهدف') }}</span>
                <textarea name="description" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('description') }}</textarea>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.goals_build_create.field_2', 'سبب الهدف') }}</span>
                <textarea name="reason" rows="2" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('reason') }}</textarea>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.goals_build_create.field_3', 'ليه بنعمل ده؟ — السبب بيمشي مع الهدف لكلّ طبقة تحته.') }}</span>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.goals_build_create.field_4', 'الأولويّة') }}</span>
                <select name="priority" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($priorities as $key => $label)
                        <option value="{{ $key }}" @selected((string) old('priority', 2) === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <x-form.input name="end_date" type="date" :label="setting('volunteer.goals_build_create.label_4', 'تاريخ النهاية')" required />

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.goals_build_create.field_5', 'معيار التحقّق') }}</span>
                <select name="verification_type" data-criteria-type class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="numeric" @selected(old('verification_type', 'numeric') === 'numeric')>{{ setting('volunteer.goals_build_create.option', 'رقم من X إلى Y') }}</option>
                    <option value="boolean" @selected(old('verification_type') === 'boolean')>{{ setting('volunteer.goals_build_create.option_2', 'حالة تتفحص بنعم/لا') }}</option>
                </select>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('volunteer.goals_build_create.field_6', 'هدف بلا معيار تحقّق مش هيتحفظ.') }}</span>
            </label>

            <div data-criteria="numeric" class="grid grid-cols-2 gap-3">
                <x-form.input name="target_from" type="number" step="any" :label="setting('volunteer.goals_build_create.label_5', 'من (X)')" />
                <x-form.input name="target_to" type="number" step="any" :label="setting('volunteer.goals_build_create.label_6', 'إلى (Y)')" />
            </div>

            <div data-criteria="boolean" class="hidden">
                <x-form.input name="verification_statement" :label="setting('volunteer.goals_build_create.label_7', 'الحالة اللي هتتفحص بنعم/لا')"
                              :hint="setting('volunteer.goals_build_create.hint', 'اكتبها جملة واحدة تتفحص: «اتنشرت 12 فيديو معتمدة؟»')" />
            </div>

            <fieldset class="block">
                <legend class="block text-sm mb-1">{{ setting('volunteer.goals_build_create.legend', 'المسارات (اختياريّ دلوقتي)') }}</legend>
                <div class="space-y-1">
                    @foreach ($tracks as $track)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="tracks[]" value="{{ $track->id }}">
                            <span>{{ $track->name_ar }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                    {{ setting('volunteer.goals_build_create.field_7', 'سيب المربّعات فاضية لو لسّه مش عايزه يظهر — تقدر تربطه بعدين من لوحة الرحلة.') }}
                </span>
            </fieldset>
        </x-form.stepper>

        <div class="mt-4">
            <button type="submit" class="btn w-full md:w-auto rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.goals_build_create.action', 'احفظ الهدف') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
/* تبديل صورة معيار التحقّق — إظهارٌ بصريّ فقط، والفرض على الخادم (23 — 1.1) */
(function () {
    var select = document.querySelector('[data-criteria-type]');
    if (!select) { return; }

    function paint() {
        document.querySelectorAll('[data-criteria]').forEach(function (box) {
            box.classList.toggle('hidden', box.getAttribute('data-criteria') !== select.value);
        });
    }

    select.addEventListener('change', paint);
    paint();
})();
</script>
@endpush
