{{-- ساعة المستخدم (5): نداء الكشف + عدّاد الفتح — والقرار كلّه في الخادم --}}
<script>
    (() => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        /* الكشف التلقائيّ: المتصفّح **يقترح** منطقته، والخادم يتحقّق ويقرّر ويكتب.
           ولا نُرسل إلّا حين تتغيّر عن المخزّنة — فلا نداء بلا سبب (2.7). */
        const stored = @json($storedTimezone ?? '');
        let detected = '';

        try {
            detected = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (e) {
            detected = '';
        }

        if (token && detected && detected !== stored) {
            fetch(@json(route('timezone.detect')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ timezone: detected }),
            }).then((r) => (r.ok ? r.json() : null))
              .then((data) => { if (data && data.changed) window.location.reload(); })
              .catch(() => {});
        }

        /* عدّاد الفتح: يعرض «كم باقي» ويعيد التحميل عند الصفر فتُحسَب الحالة
           من جديد في الخادم — الواجهة لا تفتح شيئًا بنفسها أبدًا. */
        document.querySelectorAll('[data-availability-countdown]').forEach((node) => {
            let left = parseInt(node.dataset.availabilityCountdown, 10);

            const render = () => {
                if (left <= 0) {
                    node.textContent = @json(setting('learning.availability.opening_now'));
                    window.location.reload();
                    return;
                }

                const d = Math.floor(left / 86400);
                const h = Math.floor((left % 86400) / 3600);
                const m = Math.floor((left % 3600) / 60);
                const s = left % 60;
                const pad = (n) => String(n).padStart(2, '0');

                node.textContent = (d > 0 ? d + @json(setting('learning.availability.day_suffix')) + ' ' : '')
                    + pad(h) + ':' + pad(m) + ':' + pad(s);
                left -= 1;
            };

            render();
            setInterval(render, 1000);
        });
    })();
</script>
