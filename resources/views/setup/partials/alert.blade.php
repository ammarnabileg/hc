{{-- رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) — ومعها رمز لا لون فقط (2.16-ب) --}}
@php($alertKeys = (array) ($keys ?? []))

@foreach ($alertKeys as $alertKey)
    @error($alertKey)
        <div class="rounded-xl p-3 mb-4 text-sm flex items-start gap-2"
             style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent);
                    border: 1px solid var(--color-state-danger)">
            <span aria-hidden="true">◉</span>
            <span>{{ $message }}</span>
        </div>
    @enderror
@endforeach

@if (session('setup_success'))
    <div class="rounded-xl p-3 mb-4 text-sm flex items-start gap-2"
         style="background: color-mix(in srgb, var(--color-state-ok) 12%, transparent);
                border: 1px solid var(--color-state-ok)">
        <span aria-hidden="true">●</span>
        <span>{{ session('setup_success') }}</span>
    </div>
@endif
