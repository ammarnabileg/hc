@once
    @push('scripts')
        <script>
            /* عدّاد تنازليّ للمواعيد ونوافذ التسجيل (2.17-أ).
               ⭐ القيمة النهائيّة تظهر في كلّ الأحوال: لو حصل أيّ خطأ يبقى نصّ الخادم كما هو. */
            (function () {
                const nodes = document.querySelectorAll('[data-countdown]');
                if (!nodes.length) return;

                const format = (ms) => {
                    if (ms <= 0) return 'انتهى الوقت';
                    const total = Math.floor(ms / 1000);
                    const d = Math.floor(total / 86400);
                    const h = Math.floor((total % 86400) / 3600);
                    const m = Math.floor((total % 3600) / 60);
                    if (d > 0) return `${d} يوم و${h} ساعة`;
                    return `${h}:${String(m).padStart(2, '0')} ساعة`;
                };

                const tick = () => {
                    nodes.forEach((el) => {
                        const at = Date.parse(el.dataset.countdown);
                        if (!Number.isFinite(at)) return;
                        const prefix = el.dataset.prefix ? el.dataset.prefix + ' ' : '';
                        const left = at - Date.now();
                        el.textContent = left > 0 ? prefix + format(left) : 'انتهى الوقت';
                    });
                };

                try { tick(); setInterval(tick, 30000); } catch (e) { /* يبقى نصّ الخادم */ }
            })();
        </script>
    @endpush
@endonce
