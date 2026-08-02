@php
    // كلّ نصوص البوب-أب من الإعدادات (2.13) — تُمرَّر للـJS كجسم واحد
    $modalTexts = [
        'invoice' => setting('library.modal.invoice_title', 'الفاتورة'),
        'number' => setting('library.modal.invoice_number', 'رقم الطلب'),
        'date' => setting('library.modal.invoice_date', 'التاريخ'),
        'total' => setting('library.modal.invoice_total', 'المبلغ'),
        'noInvoice' => setting('library.modal.no_invoice', 'العنصر ده مش مربوط بطلب شراء.'),
        'error' => setting('library.modal.error', 'مش قادرين نجيب التفاصيل دلوقتي — جرّب تاني.'),
        'copied' => setting('library.modal.copied', 'الرابط اتنسخ ✓'),
    ];
@endphp

{{-- بوب-أب العنصر: معاينة + الفاتورة/الإيصال + «أوصِ بهذا» (20.1 · 20.4) --}}
<x-modal id="library-item" :title="setting('library.modal.title', 'تفاصيل العنصر')">
    <div data-item-body class="space-y-4">
        <div class="h-24 rounded-xl animate-shimmer" style="background: var(--surface-sunken)"></div>
    </div>

    <x-slot:footer>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-recommend
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">
                {{ setting('library.modal.recommend_label', 'أوصِ بهذا') }}
            </button>
            <input type="text" data-recommend-url readonly hidden
                   class="grow min-w-40 rounded-xl px-3 py-2 text-xs"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <span class="text-xs" data-recommend-note style="color: var(--text-muted)"></span>
        </div>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
/* بوب-أب مكتبتي: يجلب التفاصيل عند الفتح — تحميل كسول لا مع الصفحة (2.15-أ) */
(function () {
    const modal = document.getElementById('library-item');
    if (!modal) return;

    const body = modal.querySelector('[data-item-body]');
    const recommendBtn = modal.querySelector('[data-recommend]');
    const recommendUrl = modal.querySelector('[data-recommend-url]');
    const note = modal.querySelector('[data-recommend-note]');
    const texts = {!! json_encode($modalTexts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

    let current = null;

    document.addEventListener('click', async (e) => {
        const opener = e.target.closest('[data-modal-open="library-item"]');
        if (!opener) return;

        current = opener.dataset.itemUrl;
        recommendUrl.hidden = true;
        note.textContent = '';
        body.innerHTML = '<div class="h-24 rounded-xl animate-shimmer" style="background: var(--surface-sunken)"></div>';

        try {
            const res = await fetch(current, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('http');
            const data = await res.json();
            recommendBtn.dataset.url = data.recommend_url;

            const invoice = data.invoice
                ? `<dl class="grid grid-cols-2 gap-2 text-sm">
                     <dt style="color: var(--text-muted)">${texts.number}</dt><dd>${data.invoice.number}</dd>
                     <dt style="color: var(--text-muted)">${texts.date}</dt><dd>${data.invoice.date ?? '—'}</dd>
                     <dt style="color: var(--text-muted)">${texts.total}</dt><dd>${data.invoice.total} ${data.invoice.currency}</dd>
                   </dl>`
                : `<p class="text-sm" style="color: var(--text-muted)">${texts.noInvoice}</p>`;

            body.innerHTML = `
                <h3 class="font-bold">${data.title}</h3>
                <p class="text-sm" style="color: var(--text-muted)">${data.description ?? ''}</p>
                <div class="card p-3">
                    <div class="text-xs mb-2" style="color: var(--text-muted)">${texts.invoice}</div>
                    ${invoice}
                </div>`;
        } catch {
            /* رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) */
            body.innerHTML = `<p class="text-sm">${texts.error}</p>`;
        }
    });

    recommendBtn?.addEventListener('click', async () => {
        if (!recommendBtn.dataset.url) return;
        recommendBtn.disabled = true;

        try {
            const res = await fetch(recommendBtn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            recommendUrl.hidden = false;
            recommendUrl.value = data.url;
            recommendUrl.select();
            note.textContent = data.message;
            try { await navigator.clipboard.writeText(data.url); note.textContent = texts.copied; } catch {}
        } catch {
            note.textContent = texts.error;
        } finally {
            recommendBtn.disabled = false;
        }
    });
})();
</script>
@endpush
