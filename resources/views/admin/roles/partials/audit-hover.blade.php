@php
    /**
     * آخر تغيير فقط (لا سجلّ كامل) — يظهر بالـHover بتأخير قصير
     * مع رابط لبروفايل المحرّر (12.2.1-ز-4 · 2.13-هـ).
     */
@endphp

@if ($log)
    <span class="relative inline-block" data-audit-hover tabindex="0">
        <span class="text-xs cursor-help" style="color: var(--text-muted)">{{ setting('admin.roles.partials.audit_hover.akhr_tghyyr', 'آخر تغيير ⓘ') }}</span>

        <span data-audit-tip
              class="hidden absolute z-40 mt-1 end-0 w-64 card p-3 text-xs text-start"
              style="background: var(--surface); color: var(--text)">
            <span class="block font-semibold">{{ \App\Services\Admin\AuditTrail::label($log->action) }}</span>
            <span class="block mt-1" style="color: var(--text-muted)">{{ $log->created_at?->format('Y-m-d H:i') }}</span>

            @if ($log->user)
                <a href="{{ $log->user->profileUrl() }}" class="block mt-1 hover:underline"
                   style="color: var(--color-brand-500)">{{ $log->user->shortName() }}</a>
            @endif
        </span>
    </span>
@endif
