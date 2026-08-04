@once
    @push('scripts')
        @php
            /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
            $jsText = [
                'finished' => (string) setting('volunteer.meetings_countdown.js_finished', 'انتهى الوقت'),
                'day' => (string) setting('volunteer.meetings_countdown.js_day', 'يوم'),
                'hour' => (string) setting('volunteer.meetings_countdown.js_hour', 'ساعة'),
            ];
        @endphp

        <script>
            const T = @json($jsText);
            /* عدّاد تنازليّ للمواعيد ونوافذ التسجيل (2.17-أ).
               ⭐ القيمة النهائيّة تظهر في كلّ الأحوال: لو حصل أيّ خطأ يبقى نصّ الخادم كما هو. */
            (function () {
                const nodes = document.querySelectorAll('[data-countdown]');
                if (!nodes.length) return;

                const format = (ms) => {
                    if (ms <= 0) return T.finished;
                    const total = Math.floor(ms / 1000);
                    const d = Math.floor(total / 86400);
                    const h = Math.floor((total % 86400) / 3600);
                    const m = Math.floor((total % 3600) / 60);
                    if (d > 0) return `${d} ${T.day} و${h} ${T.hour}`;
                    return `${h}:${String(m).padStart(2, '0')} ${T.hour}`;
                };

                const tick = () => {
                    nodes.forEach((el) => {
                        const at = Date.parse(el.dataset.countdown);
                        if (!Number.isFinite(at)) return;
                        const prefix = el.dataset.prefix ? el.dataset.prefix + ' ' : '';
                        const left = at - Date.now();
                        el.textContent = left > 0 ? prefix + format(left) : T.finished;
                    });
                };

                try { tick(); setInterval(tick, 30000); } catch (e) { /* يبقى نصّ الخادم */ }
            })();
        </script>
    @endpush
@endonce
