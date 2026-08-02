@once
    @push('scripts')
        <script>
            /* -----------------------------------------------------------------
             | ردّ فوريّ لكلّ فعل (2.17-ب): Toast صغير بلا أيّ مكتبة خارجيّة.
             ----------------------------------------------------------------- */
            window.hcToast = function (message, state = 'ok') {
                const host = document.getElementById('hc-toasts') || (() => {
                    const el = document.createElement('div');
                    el.id = 'hc-toasts';
                    el.style.cssText = 'position:fixed;inset-block-end:1rem;inset-inline-start:1rem;z-index:70;display:grid;gap:.5rem';
                    document.body.appendChild(el);
                    return el;
                })();

                const toast = document.createElement('div');
                toast.className = 'card p-3 text-sm animate-fadeup';
                toast.setAttribute('role', 'status');
                toast.style.borderColor = `var(--color-state-${state})`;
                toast.textContent = message;
                host.appendChild(toast);

                setTimeout(() => toast.remove(), {{ (int) (setting('ux.toast.seconds', 5) * 1000) }});
            };
        </script>
    @endpush
@endonce
