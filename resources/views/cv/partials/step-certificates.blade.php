@php
    $hidden = array_map('intval', (array) ($data['hidden_certificates'] ?? []));
    $pull = (array) ($data['pull'] ?? []);
@endphp

<div class="card p-4" data-step-panel="certificates" hidden>
    <form data-step-form="certificates" onsubmit="return false" class="space-y-3">
        <p class="text-sm">{{ setting('cv.certificates.intro', 'شهاداتك بتتسحب تلقائيًّا من المنصّة — شيل اللي مش عايزه يظهر.') }}</p>

        @forelse ($certificates as $certificate)
            <label class="card p-3 flex items-center justify-between gap-3">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold truncate">{{ $certificate->certificate_type?->name_ar }}</span>
                    <span class="block text-xs" style="color: var(--text-muted)">
                        {{ $certificate->code }} · {{ $certificate->issued_at?->translatedFormat('F Y') }}
                    </span>
                </span>
                <span class="flex items-center gap-2 text-xs shrink-0" style="color: var(--text-muted)">
                    {{ setting('cv.certificates.hide_label', 'إخفاء') }}
                    <input type="checkbox" name="data[hidden_certificates][]" value="{{ $certificate->id }}"
                           @checked(in_array($certificate->id, $hidden, true))>
                </span>
            </label>
        @empty
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('cv.certificates.empty', 'لسّه مافيش شهادات — أوّل تدريب هيجيبلك واحدة.') }}</p>
        @endforelse
    </form>

    {{-- مفاتيح السحب التلقائيّ — خارج فورم الخطوة لأنّها تُحفَظ بمسارها الخاصّ (9) --}}
    <div class="mt-4 pt-3 space-y-2" style="border-top: 1px solid var(--border)">
        @foreach ([
            'profile' => setting('cv.pull.profile_label', 'اسحب بيانات بروفايلي (الاسم · الدولة · التواصل)'),
            'certificates' => setting('cv.pull.certificates_label', 'اسحب شهاداتي من المنصّة'),
        ] as $source => $label)
            <label class="flex items-center justify-between gap-3 text-sm">
                <span>{{ $label }}</span>
                <input type="checkbox" data-pull="{{ $source }}" @checked($pull[$source] ?? true)
                       data-url="{{ \Illuminate\Support\Facades\Route::has('cv.pull') ? route('cv.pull', $source) : '' }}">
            </label>
        @endforeach
        <span class="text-xs block" data-pull-note style="color: var(--color-state-ok)"></span>
    </div>
</div>

@push('scripts')
<script>
/* مفاتيح السحب التلقائيّ: ردّ فوريّ و«اتحفظ ✓» (2.17-ب) */
document.querySelectorAll('[data-pull]').forEach((box) => box.addEventListener('change', async () => {
    const note = document.querySelector('[data-pull-note]');
    if (!box.dataset.url) return;

    try {
        const res = await fetch(box.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ enabled: box.checked ? 1 : 0 }),
        });
        const data = await res.json();
        note.textContent = data.label;
        note.style.color = 'var(--color-state-ok)';
        document.querySelector('[data-preview]')?.setAttribute('src', document.querySelector('[data-cv]').dataset.previewUrl + '?t=' + Date.now());
    } catch {
        note.textContent = @json(setting('cv.autosave.error_label', 'ما اتحفظش — راجع النت وجرّب تاني.'));
        note.style.color = 'var(--color-state-warn)';
    }
}));
</script>
@endpush
