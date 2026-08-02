{{-- معرض القوالب: المجّانيّ باب الدخول (21.2-ج) والمدفوع بعلامة سعر واضحة (24.5) --}}
<div class="card p-4 mt-4" data-templates>
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-semibold">{{ setting('cv.templates.title', 'القالب') }}</span>
        <span class="text-xs" style="color: var(--text-muted)">
            {{ str_replace(':n', (int) $ticketBalance, setting('cv.templates.balance_label', 'رصيدك: :n تذكرة')) }}
        </span>
    </div>

    <div class="grid gap-3 grid-cols-2 md:grid-cols-3">
        @foreach ($templates as $template)
            @php $owned = in_array($template->id, $ownedTemplateIds, true); @endphp

            <button type="button"
                    data-template="{{ $template->id }}"
                    data-owned="{{ $owned ? 1 : 0 }}"
                    data-price="{{ (int) $template->priceTickets() }}"
                    data-name="{{ $template->name }}"
                    data-url="{{ route('cv.template', $template) }}"
                    class="card p-2 text-start motion-standard"
                    style="{{ $templateId === $template->id ? 'outline: 2px solid var(--color-brand-500)' : '' }}">
                <span class="block rounded-lg mb-2 aspect-3/4 overflow-hidden" style="background: var(--surface-sunken)">
                    @if ($template->preview_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($template->preview_path) }}"
                             alt="{{ $template->name }}" loading="lazy" class="w-full h-full object-cover">
                    @endif
                </span>
                <span class="block text-sm font-semibold truncate">{{ $template->name }}</span>
                <span class="block text-xs mt-1">
                    @if ($template->is_free)
                        <x-state-badge state="ok" :label="setting('cv.template.free_badge', 'مجّانيّ')" />
                    @elseif ($owned)
                        <x-state-badge state="honor" :label="setting('cv.template.owned_badge', 'مملوك')" />
                    @else
                        <span style="color: var(--color-brand-400)">
                            {{ str_replace(':n', (int) $template->priceTickets(), setting('cv.template.price_label', ':n تذكرة')) }}
                        </span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <span class="text-xs block mt-2" data-template-note style="color: var(--color-state-ok)"></span>

    <p class="text-xs mt-2" style="color: var(--text-muted)">
        {{ setting('cv.templates.charge_hint', 'اختيار القالب مجّانيّ — والتذاكر بتتخصم لمّا تحمّل النسخة النظيفة.') }}
    </p>
</div>

{{--
  ⭐ لا بوب-أب شراء عند الاختيار: **الخصم لحظة الاستخراج النهائيّ لا لحظة الاختيار** (9).
  الاختيار مجّانيّ، والمعاينة موسومة، والرصيد قبل/بعد يظهر هنا ليعرف ما ينتظره.
--}}
@push('scripts')
<script>
(function () {
    const box = document.querySelector('[data-templates]');
    if (!box) return;

    const note = box.querySelector('[data-template-note]');
    const preview = document.querySelector('[data-preview]');
    const root = document.querySelector('[data-cv]');

    box.querySelectorAll('[data-template]').forEach((btn) => btn.addEventListener('click', async () => {
        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });
            const data = await res.json();

            box.querySelectorAll('[data-template]').forEach((b) => { b.style.outline = 'none'; });
            btn.style.outline = '2px solid var(--color-brand-500)';

            note.textContent = data.message || '';
            note.style.color = data.owned ? 'var(--color-state-ok)' : 'var(--color-state-warn)';

            if (preview && root) preview.src = root.dataset.previewUrl + '?t=' + Date.now();
        } catch {
            note.textContent = @json(setting('cv.template.error_label', 'مش قادرين ننفّذ دلوقتي — جرّب تاني.'));
            note.style.color = 'var(--color-state-warn)';
        }
    }));
})();
</script>
@endpush
