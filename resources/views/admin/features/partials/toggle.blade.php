{{--
  عمود **Toggle** — والفعل نفسه هو ما يفتح بوب-أب الإيقاف (24.3):
  التشغيل يمرّ فورًا، والإيقاف **لا يمرّ بلا سبب** لأنّ السبب يدخل الـAudit.

  ⛔ ومَن لا يملك `feature_toggles.edit` **لا يرى الزرّ أصلًا** — يُخفى ولا
     يُعطَّل (2.15-أ-7)، ويبقى معه شارةُ الحالة وحدها.
--}}
@php
    $state = $row['status'];
    $label = [
        'on' => setting('features.ui.status.on', 'مشتغّل'),
        'off' => setting('features.ui.status.off', 'موقوف'),
        'partial' => setting('features.ui.status.partial', 'جزئيّ'),
    ][$state];
@endphp

<div class="flex items-center gap-2">
    <x-state-badge :state="$state === 'on' ? 'ok' : ($state === 'off' ? 'danger' : 'warn')" :label="$label" />

    @if ($mayEdit)
        @if ($row['enabled'])
            <button type="button" class="rounded-xl px-2 py-1 text-xs motion-standard" data-feature-disable
                    style="background: color-mix(in srgb, var(--color-state-danger) 18%, transparent); color: var(--color-state-danger)">
                {{ setting('features.ui.action.turn_off', 'أوقف') }}
            </button>
        @else
            <button type="button" class="rounded-xl px-2 py-1 text-xs motion-standard" data-feature-enable
                    style="background: color-mix(in srgb, var(--color-state-ok) 18%, transparent); color: var(--color-state-ok)">
                {{ setting('features.ui.action.turn_on', 'شغّل') }}
            </button>
        @endif
    @endif
</div>
