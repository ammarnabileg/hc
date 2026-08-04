@php
    /**
     * بوب-أبّات البتّ (24.4-8): ردّ · تصعيد · تعديل/عكس · رفض.
     * التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6)، وبرأس ثابت وجسم متمرّر.
     * وكلّ زرٍّ منها مخفيٌّ عمّن لا يملك مفتاحه — لا معطَّلًا (2.15-أ-7).
     */
    $preview = $service->correctionPreview($objection);
@endphp

{{-- ردّ: نصّ + مرفق — ولا يُغلِق الاعتراض بل ينقله «قيد المراجعة» --}}
<x-modal :id="'obj-reply-'.$objection->id" :title="setting('volunteer.escalations_objection_desk_modals.tooltip', 'ردّ على اعتراض #').$objection->id">
    <form method="post" action="{{ route('volunteer.escalations.objections.reply', $objection) }}"
          enctype="multipart/form-data" class="space-y-3">
        @csrf
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('volunteer.escalations_objection_desk_modals.field', 'الردّ') }} <span style="color: var(--color-state-danger)">*</span></span>
            <textarea name="body" rows="4" required minlength="2" maxlength="2000"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
        </label>
        <input type="file" name="attachment" class="w-full text-xs">
        <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.escalations_objection_desk_modals.text', 'الردّ بيخلّي الحالة «قيد المراجعة» ويجدّد مهلتك.') }}</p>
        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c; min-block-size: 44px">{{ setting('volunteer.escalations_objection_desk_modals.action', 'إرسال الردّ') }}</button>
    </form>
</x-modal>

@can('objections.assign')
    {{-- تصعيد لمن فوقي بسبب — على المسار المستقلّ لا على محرّك التصعيد (23-6) --}}
    <x-modal :id="'obj-escalate-'.$objection->id" :title="setting('volunteer.escalations_objection_desk_modals.tooltip_2', 'تصعيد اعتراض #').$objection->id">
        <form method="post" action="{{ route('volunteer.escalations.objections.escalate', $objection) }}" class="space-y-3">
            @csrf
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.escalations_objection_desk_modals.field_2', 'سبب التصعيد') }} <span style="color: var(--color-state-danger)">*</span></span>
                <textarea name="reason" rows="4" required minlength="5" maxlength="2000"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <p class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.escalations_objection_desk_modals.text_2', 'الاعتراض هيروح لأبلاينك المباشر بمهلة جديدة، ويظهر في سلّم التصعيد للجميع.') }}
            </p>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c; min-block-size: 44px">{{ setting('volunteer.escalations_objection_desk_modals.action_2', 'تصعيد') }}</button>
        </form>
    </x-modal>
@endcan

@can('objections.approve')
    {{-- تعديل/عكس ⟵ معاملة تصحيحيّة شفّافة بمعاينة الأثر + إشعار للعضو --}}
    <x-modal :id="'obj-accept-'.$objection->id" :title="setting('volunteer.escalations_objection_desk_modals.tooltip_3', 'تعديل/عكس — اعتراض #').$objection->id">
        <form method="post" action="{{ route('volunteer.escalations.objections.accept', $objection) }}" class="space-y-3">
            @csrf
            <p class="rounded-xl px-3 py-2 text-sm"
               style="background: color-mix(in srgb, var(--color-state-warn) 12%, transparent)">
                <span aria-hidden="true">▲</span> {{ $notice }}
            </p>
            <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                {{ setting('volunteer.escalations_objection_desk_modals.text_3', 'معاينة الأثر:') }} <strong>Rep {{ setting('volunteer.escalations_objection_desk_modals.strong', 'يرتفع من') }} {{ $preview['from'] }} {{ setting('volunteer.common.to', 'إلى') }} {{ $preview['to'] }}</strong>
                ({{ $preview['delta'] > 0 ? '+' : '' }}{{ $preview['delta'] }})
            </div>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.escalations_objection_desk_modals.field_3', 'مبرّر القبول') }} <span style="color: var(--color-state-danger)">*</span></span>
                <textarea name="note" rows="3" required minlength="5" maxlength="2000"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c; min-block-size: 44px">
                {{ setting('volunteer.escalations_objection_desk_modals.action_3', 'قبول وإصدار المعاملة العكسيّة') }}
            </button>
        </form>
    </x-modal>
@endcan

@can('objections.reject')
    <x-modal :id="'obj-reject-'.$objection->id" :title="setting('volunteer.escalations_objection_desk_modals.tooltip_4', 'رفض اعتراض #').$objection->id">
        <form method="post" action="{{ route('volunteer.escalations.objections.reject', $objection) }}" class="space-y-3">
            @csrf
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('volunteer.escalations_objection_desk_modals.field_4', 'سبب الرفض') }} <span style="color: var(--color-state-danger)">*</span></span>
                <textarea name="note" rows="4" required minlength="5" maxlength="2000"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.escalations_objection_desk_modals.text_4', 'الرفض بيقفل الاعتراض بسببه، ويوصل إشعار للعضو.') }}</p>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-state-danger); color: #fff; min-block-size: 44px">{{ setting('volunteer.escalations_objection_desk_modals.action_4', 'رفض وإغلاق') }}</button>
        </form>
    </x-modal>
@endcan
