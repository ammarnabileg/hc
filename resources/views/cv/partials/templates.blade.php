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
                    data-price="{{ (int) $template->price_tickets }}"
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
                            {{ str_replace(':n', (int) $template->price_tickets, setting('cv.template.price_label', ':n تذكرة')) }}
                        </span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <span class="text-xs block mt-2" data-template-note style="color: var(--color-state-ok)"></span>
</div>

{{-- بوب-أب الشراء بالرصيد قبل/بعد (24.5) --}}
<x-modal id="cv-template-buy" :title="setting('cv.template.buy_title', 'شراء القالب')">
    <div class="space-y-2 text-sm">
        <p data-buy-name class="font-semibold"></p>
        <dl class="grid grid-cols-2 gap-2">
            <dt style="color: var(--text-muted)">{{ setting('cv.template.price_title', 'السعر') }}</dt><dd data-buy-price></dd>
            <dt style="color: var(--text-muted)">{{ setting('cv.template.balance_before', 'الرصيد قبل') }}</dt><dd data-buy-before></dd>
            <dt style="color: var(--text-muted)">{{ setting('cv.template.balance_after', 'الرصيد بعد') }}</dt><dd data-buy-after></dd>
        </dl>
        <p class="text-xs" data-buy-error style="color: var(--color-state-warn)"></p>
    </div>

    <x-slot:footer>
        <button type="button" data-buy-confirm class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('cv.template.confirm_label', 'أكّد الشراء') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
/* اختيار القالب: المجّانيّ فورًا، والمدفوع ببوب-أب الرصيد قبل/بعد (24.5) */
(function () {
    const box = document.querySelector('[data-templates]');
    if (!box) return;

    const modal = document.getElementById('cv-template-buy');
    const note = box.querySelector('[data-template-note]');
    const preview = document.querySelector('[data-preview]');
    const root = document.querySelector('[data-cv]');
    let pending = null;

    const post = async (url, confirm) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ confirm: confirm ? 1 : 0 }),
        });
        return res.json();
    };

    const select = (btn) => {
        box.querySelectorAll('[data-template]').forEach((b) => { b.style.outline = 'none'; });
        btn.style.outline = '2px solid var(--color-brand-500)';
        btn.dataset.owned = '1';
        if (preview && root) preview.src = root.dataset.previewUrl + '?t=' + Date.now();
    };

    box.querySelectorAll('[data-template]').forEach((btn) => btn.addEventListener('click', async () => {
        try {
            const data = await post(btn.dataset.url, false);

            if (data.ok) { select(btn); note.textContent = data.message; return; }

            pending = btn;
            modal.querySelector('[data-buy-name]').textContent = btn.dataset.name;
            modal.querySelector('[data-buy-price]').textContent = data.price;
            modal.querySelector('[data-buy-before]').textContent = data.balance_before;
            modal.querySelector('[data-buy-after]').textContent = data.balance_after;
            modal.querySelector('[data-buy-error]').textContent = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } catch {
            note.textContent = @json(setting('cv.template.error_label', 'مش قادرين ننفّذ دلوقتي — جرّب تاني.'));
            note.style.color = 'var(--color-state-warn)';
        }
    }));

    modal.querySelector('[data-buy-confirm]').addEventListener('click', async () => {
        if (!pending) return;
        const data = await post(pending.dataset.url, true);

        if (!data.ok) {
            modal.querySelector('[data-buy-error]').textContent = data.message ?? '';
            return;
        }

        select(pending);
        note.textContent = data.message;
        note.style.color = 'var(--color-state-ok)';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
})();
</script>
@endpush
