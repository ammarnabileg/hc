{{--
    عدّاد الفتح (5). أمّا **كشف** المنطقة الزمنيّة فانتقل إلى التخطيط العامّ
    (`learning.partials.timezone-detect`) لأنّه كان يعمل في صفحتين فقط بينما
    الإتاحة تُحسَب في كلّ الشاشات — فلا يُكرَّر هنا.
--}}
<script>
    (() => {
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
