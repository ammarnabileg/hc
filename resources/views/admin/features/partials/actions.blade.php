{{-- إجراءات الصفّ (24.3): تفاصيل · Audit · ↺ --}}
<div class="flex items-center gap-2 text-xs">
    <button type="button" class="underline" data-feature-details>{{ setting('features.ui.action.details', 'تفاصيل') }}</button>
    <button type="button" class="underline" data-feature-audit>{{ setting('features.ui.action.audit', 'Audit') }}</button>

    @if ($mayEdit)
        <button type="button" class="rounded-xl px-2 py-1" data-feature-reset
                style="background: var(--surface-sunken)"
                aria-label="{{ setting('features.ui.action.reset', '↺') }}">{{ setting('features.ui.action.reset', '↺') }}</button>
    @endif
</div>
