@php
    /** زرّ نسخ بردّ فوريّ «اتنسخ ✓» (2.17-ب) — بلا أيّ مكتبة خارجيّة */
    $label = $label ?? setting('events.copy.default_label', 'نسخ');
    $text = (string) ($text ?? '');
    $tone = $tone ?? 'solid';
@endphp

<button type="button" data-copy="{{ $text }}" data-copy-done="{{ setting('events.copy.done_label', 'اتنسخ ✓') }}"
        class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
        style="{{ $tone === 'solid'
            ? 'background: var(--color-brand-500); color: #04201c'
            : 'background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border)' }}">
    @include('events.components.icon', ['name' => 'copy'])
    <span>{{ $label }}</span>
</button>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-copy]');
                if (!btn) return;

                const label = btn.querySelector('span') || btn;
                const original = label.textContent;

                const fallback = () => {
                    const field = document.createElement('textarea');
                    field.value = btn.dataset.copy;
                    field.setAttribute('readonly', '');
                    field.style.position = 'fixed';
                    field.style.opacity = '0';
                    document.body.appendChild(field);
                    field.select();
                    try { document.execCommand('copy'); } catch (err) { /* المستخدم ينسخ يدويًّا */ }
                    document.body.removeChild(field);
                };

                try {
                    if (navigator.clipboard) { await navigator.clipboard.writeText(btn.dataset.copy); }
                    else { fallback(); }
                } catch (err) { fallback(); }

                // ردّ فوريّ لكلّ فعل (2.17-ب)
                label.textContent = btn.dataset.copyDone;
                if (navigator.vibrate) { try { navigator.vibrate(10); } catch (err) { /* بلا اهتزاز */ } }
                setTimeout(() => { label.textContent = original; }, 1800);
            });
        </script>
    @endpush
@endonce
