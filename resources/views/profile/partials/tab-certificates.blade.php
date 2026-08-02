@php
    // الشهادات من **المصدر الواحد** (12.5 / مكتبتي 20) — لا حساب موازٍ (10.0-أ)
    $canSee = $visibility->canSee('certificates', $viewer, $owner, $level);
@endphp

@if (! $canSee)
    <x-empty message="الشهادات مش متاحة على البروفايل ده." />
@elseif ($certificates->isEmpty())
    <x-empty message="لسّه بدري — أوّل شهادة مستنّياك." />
@else
    <div class="grid md:grid-cols-2 gap-3">
        @foreach ($certificates as $certificate)
            <article class="card p-4 animate-fadeup">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm truncate">{{ $certificate->certificate_type?->name_ar ?? 'شهادة' }}</h2>
                        <p class="text-xs mt-1 font-mono" style="color: var(--text-muted)">{{ $certificate->code }}</p>
                    </div>
                    {{-- شارة «شهادة معتمدة» ذهبيّة — شرف لا حالة تشغيليّة (2.16) --}}
                    <x-state-badge state="honor" label="شهادة معتمدة" />
                </div>

                <p class="text-xs mt-3" style="color: var(--text-muted)"
                   title="{{ $certificate->issued_at?->format('Y-m-d') }}">
                    صدرت {{ $certificate->issued_at?->diffForHumans() }}
                </p>
            </article>
        @endforeach
    </div>
@endif
