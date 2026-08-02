{{--
    كشف المنطقة الزمنيّة — **على كلّ صفحة** لا على صفحتين (الدستور 5).

    كان السكربت مُضمَّنًا في `learning/courses` و`learning/course` وحدهما، فمَن دخل
    من أيّ باب آخر (الداشبورد · الدرس · صفحة المسار · الإشعارات) لا تُكتشَف
    منطقته أصلًا، فتُحسَب إتاحته بآخر قيمة يعرفها الخادم — أو بتوقيت المنصّة.
    وبما أنّ الإتاحة كلّها مبنيّة على «الآن بساعة المستخدم»، فالكشف شرطُ صحّة
    لا زينة، ولذلك محلّه التخطيط العامّ.

    والمتصفّح **يقترح** فقط: النداء موقَّع بالـCSRF، والخادم يتحقّق أنّ القيمة
    منطقة IANA صالحة قبل أن يكتبها في `auto_timezone` وحده — فلا يُدهَس اختيار
    المستخدم اليدويّ، ولا تُصدَّق منطقة مزوّرة.
--}}
<script>
    (() => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const stored = @json($storedTimezone ?? '');
        let detected = '';

        try {
            detected = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (e) {
            detected = '';
        }

        /* لا نداء بلا سبب (2.7): نرسل فقط حين تختلف عن المخزّنة */
        if (!token || !detected || detected === stored) return;

        fetch(@json(route('timezone.detect')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ timezone: detected }),
        }).then((r) => (r.ok ? r.json() : null))
          .then((data) => { if (data && data.changed) window.location.reload(); })
          .catch(() => {});
    })();
</script>
