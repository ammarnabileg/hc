@push('scripts')
@php
    /*
     | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
     | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
     */
    $jsText = [
        'reason_required' => setting('admin.settings.partials.autosave_script.aktb_sbb_altadyl_alawl', 'اكتب سبب التعديل الأوّل'),
        'saved' => setting('admin.settings.partials.autosave_script.tm_alhfz', 'تم الحفظ ✓'),
        'save_failed' => setting('admin.settings.partials.autosave_script.maathfzsh', 'مااتحفظش'),
        'reset_done' => setting('admin.settings.partials.autosave_script.rjat_llaftrady', 'رجعت للافتراضيّ ✓'),
        'audit_by' => setting('admin.settings.partials.autosave_script.adlha', 'عدّلها'),
        'audit_none' => setting('admin.settings.partials.autosave_script.mafysh_tadyl_msjl', 'مافيش تعديل مسجَّل'),
        'batch_failed' => setting('admin.settings.partials.autosave_script.mawsltsh_almjmwaa', 'مااوصلتش مفاتيح المجموعة — اضغط «حمّل المزيد» تاني.'),
        'batch_progress' => setting('admin.settings.partials.autosave_script.zahr_mn', 'ظاهر :shown من :total'),
        'batch_all' => setting('admin.settings.partials.autosave_script.klha_zahra', 'كلّها ظاهرة'),
    ];
@endphp

<script>
    const HC_SETTINGS_TEXT = @json($jsText);
/*
 | حفظ تلقائيّ + Reset + Audit بالـHover + بحث موحّد — بجافاسكربت خام،
 | **بلا أيّ مكتبة خارجيّة** (قاعدة البناء). وكلّ فعل له ردّ فوريّ (2.17-ب).
 */
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    var CSRF = token ? token.getAttribute('content') : '';

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (response) {
            return response.json().then(function (data) { return { ok: response.ok, data: data }; });
        });
    }

    function valueOf(input) {
        return input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
    }

    function wire(row) {
        if (row.dataset.wired === '1') { return; }
        row.dataset.wired = '1';

        var input = row.querySelector('[data-setting-input]');
        var status = row.querySelector('[data-setting-status]');
        var example = row.querySelector('[data-setting-example]');
        var reason = row.querySelector('[data-setting-reason]');
        var key = row.getAttribute('data-setting');
        var endpoint = row.getAttribute('data-endpoint');
        var needsReason = row.getAttribute('data-reason') === '1';
        var timer = null;

        if (!input) { return; }

        function save() {
            if (needsReason && (!reason || reason.value.trim().length < 3)) {
                status.textContent = HC_SETTINGS_TEXT.reason_required;
                status.style.color = 'var(--color-state-warn)';
                return;
            }

            var payload = { key: key, value: valueOf(input) };
            if (needsReason) { payload.reason = reason.value; }

            status.textContent = '…';
            status.style.color = 'var(--text-muted)';

            post(endpoint, payload).then(function (result) {
                status.textContent = result.data.message || (result.ok ? HC_SETTINGS_TEXT.saved : HC_SETTINGS_TEXT.save_failed);
                status.style.color = result.ok ? 'var(--color-state-ok)' : 'var(--color-state-warn)';
                if (example && result.data.example) { example.textContent = result.data.example; }
                setTimeout(function () { status.textContent = ''; }, 2500);
            });
        }

        input.addEventListener('change', save);
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(save, 700);
        });

        var reset = row.querySelector('[data-setting-reset]');
        reset && reset.addEventListener('click', function () {
            post('{{ route('admin.settings.reset') }}', { key: key }).then(function (result) {
                if (result.data.value !== undefined && input.type !== 'checkbox') { input.value = result.data.value; }
                if (input.type === 'checkbox') { input.checked = result.data.value === '1'; }
                status.textContent = HC_SETTINGS_TEXT.reset_done;
                setTimeout(function () { status.textContent = ''; }, 2500);
            });
        });

        // Audit: يظهر عند الـHover بتأخير ~200ms حتى لا يفتح بالمرور العابر
        var trigger = row.querySelector('[data-audit-trigger]');
        var box = row.querySelector('[data-audit-box]');
        var hoverTimer = null;

        function showAudit() {
            fetch('{{ route('admin.settings.audit') }}?key=' + encodeURIComponent(key), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    box.classList.remove('hidden');
                    box.innerHTML = data.by
                        ? HC_SETTINGS_TEXT.audit_by + ' <a class="underline" href="' + data.by_url + '">' + data.by + '</a> ' + data.at
                        : HC_SETTINGS_TEXT.audit_none;
                });
        }

        if (trigger && box) {
            trigger.addEventListener('mouseenter', function () { hoverTimer = setTimeout(showAudit, 200); });
            trigger.addEventListener('mouseleave', function () { clearTimeout(hoverTimer); });
            trigger.addEventListener('click', showAudit);
        }
    }

    /*
     | ⭐ الربط يُعاد على **الوارد الجديد** لا على الصفحة مرّةً واحدة: الحقول
     | تصل دفعةً دفعة بعد تحميل الصفحة، وصفٌّ بلا ربطٍ حقلٌ لا يُحفَظ — وهذا
     | أخطر ما يمكن أن يكسره التحميل الكسول. و`data-wired` يمنع ربطًا مزدوجًا
     | يحفظ مرّتين.
     */
    function wireAll(scope) {
        (scope || document).querySelectorAll('.setting-row').forEach(wire);
    }

    wireAll(document);

    /*
     | ⭐ التحميل الكسول لكارت المجموعة (2.15-ب) + «حمّل المزيد» تدريجيًّا
     | (2.15-د ترفض «ترقيم الصفحات بدل التمرير التدريجيّ» — فلا أرقام صفحات).
     | ولا مفتاح يُحذَف: العدّاد يقول كم ظهر من كم، والزرّ يبلغ الآخِر.
     */
    var BATCH_URL = '{{ route('admin.settings.batch') }}';

    document.querySelectorAll('details[data-group-card]').forEach(function (card) {
        var body = card.querySelector('[data-group-fields]');
        var skeleton = card.querySelector('[data-group-skeleton]');
        var errorBox = card.querySelector('[data-group-error]');
        var moreRow = card.querySelector('[data-group-more-row]');
        var moreBtn = card.querySelector('[data-group-more]');
        var progress = card.querySelector('[data-group-progress]');
        var busy = false;

        if (!body) { return; }

        function total() { return Number(card.getAttribute('data-total')) || 0; }
        function loaded() { return Number(card.getAttribute('data-loaded')) || 0; }
        function start() { return Number(card.getAttribute('data-start')) || 0; }

        function sync() {
            var shown = loaded() - start();
            var done = loaded() >= total();

            if (progress) {
                progress.textContent = done
                    ? HC_SETTINGS_TEXT.batch_all
                    : HC_SETTINGS_TEXT.batch_progress
                        .replace(':shown', String(shown))
                        .replace(':total', String(total() - start()));
            }

            if (moreRow) { moreRow.classList.toggle('hidden', done); }
        }

        function load() {
            if (busy || loaded() >= total()) { return; }
            busy = true;

            if (errorBox) { errorBox.classList.add('hidden'); }
            if (skeleton) { skeleton.classList.remove('hidden'); }

            var url = BATCH_URL
                + '?tab=' + encodeURIComponent(card.getAttribute('data-tab') || '')
                + '&group=' + encodeURIComponent(card.getAttribute('data-group') || '')
                + '&q=' + encodeURIComponent(card.getAttribute('data-q') || '')
                + '&offset=' + encodeURIComponent(String(loaded()));

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (data) {
                    body.insertAdjacentHTML('beforeend', data.html);
                    card.setAttribute('data-loaded', String(data.next));
                    wireAll(body);
                    sync();
                })
                .catch(function () {
                    if (errorBox) {
                        errorBox.textContent = HC_SETTINGS_TEXT.batch_failed;
                        errorBox.classList.remove('hidden');
                    }
                })
                .then(function () {
                    busy = false;
                    if (skeleton) { skeleton.classList.add('hidden'); }
                });
        }

        /*
         | ⚠️ `loaded() === 0` شرطٌ لا زينة: كروم يُطلِق `toggle` **عند تحليل**
         | كارتٍ وُلِد مفتوحًا، فبلا الشرط كان الكارت المصيَّر من الخادم يجلب
         | دفعةً ثانية فورًا بلا طلبٍ من أحد — 50 حقلًا بدل 25 في كلّ فتحة.
         | والفتح بعد الإغلاق لا يعيد الجلب كذلك: محتواه في الـDOM أصلًا.
         */
        card.addEventListener('toggle', function () { if (card.open && loaded() === 0) { load(); } });
        moreBtn && moreBtn.addEventListener('click', load);
        sync();
    });

    // البحث الموحّد: النتيجة بمسارها الكامل، والنقر ينقل للحقل بتظليل مؤقّت خفيف
    var search = document.getElementById('settings-search');
    var results = document.getElementById('settings-search-results');
    var searchTimer = null;

    search && search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var term = search.value.trim();

        if (term.length < 2) { results.classList.add('hidden'); return; }

        searchTimer = setTimeout(function () {
            fetch('{{ route('admin.settings.search') }}?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    results.innerHTML = '';
                    results.classList.toggle('hidden', data.results.length === 0);

                    data.results.forEach(function (item) {
                        var link = document.createElement('a');
                        link.className = 'block px-2 py-1 rounded hover:opacity-80';
                        link.href = '{{ route('admin.settings.index') }}?tab=' + item.tab + '&key=' + encodeURIComponent(item.key);
                        link.innerHTML = '<span>' + item.label + '</span><br><small style="color: var(--text-muted)">' + item.path + '</small>';
                        results.appendChild(link);
                    });
                });
        }, 250);
    });

    // تظليل مؤقّت خفيف للحقل القادم من البحث
    var highlight = @json($highlight ?? '');

    if (highlight) {
        var target = document.querySelector('[data-setting="' + highlight + '"]');

        if (target) {
            // الحقل قد يكون داخل كارت مجموعة مطويّ — نفتحه أوّلًا وإلّا «انتقلنا» لعدم
            var card = target.closest('details[data-group-card]');
            if (card) { card.open = true; }

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.style.transition = 'background-color 1.2s var(--ease-standard)';
            target.style.backgroundColor = 'color-mix(in srgb, var(--color-brand-500) 14%, transparent)';
            setTimeout(function () { target.style.backgroundColor = 'transparent'; }, 2200);
        }
    }

    // Toggles أماكن ظهور سياسة الاسترجاع تحفظ بنفس مسار الحقل
    document.querySelectorAll('[data-toggle-setting]').forEach(function (input) {
        input.addEventListener('change', function () {
            post('{{ route('admin.settings.field') }}', {
                key: input.getAttribute('data-toggle-setting'),
                value: input.checked ? 1 : 0,
            });
        });
    });
})();
</script>
@endpush
