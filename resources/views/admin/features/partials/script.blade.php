@push('scripts')
<script>
/*
 | شاشة مفاتيح المزايا — جافاسكربت خام **بلا أيّ مكتبة** (قاعدة البناء).
 |
 | ⚠️ ولا نصّ عربيّ محروق هنا كذلك: كلّ رسالةٍ تُقرَأ من `data-*` مصدرُها
 |    `setting()` في القالب — والسكربت داخل القالب **يُمسَح** كالنصّ تمامًا.
 |
 | ⛔ وهذا كلّه **راحةُ استعمال لا حراسة**: الحارس على الخادم
 |    (`EnsureFeatureEnabled`)، وإخفاء الزرّ وحده لا يُطفئ ميزة.
 */
(function () {
    var root = document.getElementById('features-table');
    if (!root) { return; }

    var meta = document.getElementById('features-texts');
    var TEXT = JSON.parse(meta.getAttribute('data-texts'));
    var URLS = JSON.parse(meta.getAttribute('data-urls'));
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function open(id) {
        var modal = document.getElementById(id);
        if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    }

    function close(id) {
        var modal = document.getElementById(id);
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    function rowOf(el) {
        var host = el.closest('[data-payload]');
        return host ? JSON.parse(host.getAttribute('data-payload')) : null;
    }

    function say(node, message, ok) {
        node.textContent = message;
        node.style.color = ok ? 'var(--color-state-ok)' : 'var(--color-state-warn)';
    }

    var current = null;

    // ---------------------------------------------------- تشغيل فوريّ
    root.addEventListener('click', function (e) {
        var on = e.target.closest('[data-feature-enable]');
        if (!on) { return; }
        var row = rowOf(on);
        post(URLS.toggle, { key: row.key, enabled: true }).then(function (r) {
            if (r.ok) { window.location.reload(); } else { alert(r.data.message || TEXT.error); }
        });
    });

    // ---------------------------------------------------- بوب-أب الإيقاف
    root.addEventListener('click', function (e) {
        var off = e.target.closest('[data-feature-disable]');
        if (!off) { return; }
        current = rowOf(off);

        document.querySelector('[data-disable-feature-name]').textContent = current.label_ar + ' · ' + current.key;
        document.querySelector('[data-disable-reason]').value = current.disabled_reason || '';
        document.querySelector('[data-disable-message-ar]').value = current.message_ar || '';
        document.querySelector('[data-disable-message-en]').value = current.message_en || '';
        document.querySelector('[data-disable-behavior]').value = current.behavior || '';
        document.querySelector('[data-disable-visibility]').value = current.visibility || 'none';
        document.querySelector('[data-disable-notify]').checked = !!current.notify_affected;
        document.querySelector('[data-disable-status]').textContent = '';

        var roles = document.querySelector('[data-disable-roles]');
        Array.prototype.forEach.call(roles.options, function (option) {
            option.selected = (current.visible_roles || []).indexOf(parseInt(option.value, 10)) !== -1;
        });

        open('feature-disable-modal');
    });

    document.querySelector('[data-disable-submit]').addEventListener('click', function () {
        var status = document.querySelector('[data-disable-status]');
        var roles = [];
        Array.prototype.forEach.call(document.querySelector('[data-disable-roles]').options, function (option) {
            if (option.selected) { roles.push(parseInt(option.value, 10)); }
        });

        say(status, TEXT.loading, true);

        post(URLS.toggle, {
            key: current.key,
            enabled: false,
            reason: document.querySelector('[data-disable-reason]').value,
            message_ar: document.querySelector('[data-disable-message-ar]').value,
            message_en: document.querySelector('[data-disable-message-en]').value,
            behavior: document.querySelector('[data-disable-behavior]').value,
            visibility: document.querySelector('[data-disable-visibility]').value,
            visible_roles: roles,
            notify_affected: document.querySelector('[data-disable-notify]').checked,
        }).then(function (r) {
            say(status, r.data.message || TEXT.error, r.ok);
            if (r.ok) { window.location.reload(); }
        });
    });

    // ---------------------------------------------------- بوب-أب النطاق
    function paintTargets() {
        var kind = document.querySelector('[data-scope-type]').value;
        var target = document.querySelector('[data-scope-target]');
        var first = null;
        Array.prototype.forEach.call(target.options, function (option) {
            var match = option.getAttribute('data-kind') === kind;
            option.hidden = !match;
            if (match && first === null) { first = option; }
        });
        if (first) { target.value = first.value; }
    }

    document.querySelector('[data-scope-type]').addEventListener('change', paintTargets);

    root.addEventListener('click', function (e) {
        var button = e.target.closest('[data-feature-scope]');
        if (!button) { return; }
        current = rowOf(button);

        document.querySelector('[data-scope-feature-name]').textContent = current.label_ar + ' · ' + current.key;
        document.querySelector('[data-scope-current]').textContent = (current.overrides || []).length
            ? (current.overrides || []).map(function (o) {
                return o.scope_type + '#' + o.scope_id + ' = ' + (o.enabled ? TEXT.scope_on : TEXT.scope_off);
            }).join(' · ')
            : TEXT.scope_empty;
        document.querySelector('[data-scope-status]').textContent = '';

        paintTargets();
        open('feature-scope-modal');
    });

    document.querySelector('[data-scope-submit]').addEventListener('click', function () {
        var status = document.querySelector('[data-scope-status]');
        var value = document.querySelector('[data-scope-value]').value;

        say(status, TEXT.loading, true);

        post(URLS.scope, {
            key: current.key,
            scope_type: document.querySelector('[data-scope-type]').value,
            scope_id: parseInt(document.querySelector('[data-scope-target]').value, 10),
            enabled: value === '' ? null : value === '1',
        }).then(function (r) {
            say(status, r.data.message || TEXT.error, r.ok);
            if (r.ok) { window.location.reload(); }
        });
    });

    // ---------------------------------------------------- تفاصيل
    root.addEventListener('click', function (e) {
        var button = e.target.closest('[data-feature-details]');
        if (!button) { return; }
        var row = rowOf(button);

        document.querySelector('[data-details-name]').textContent = row.label_ar;
        document.querySelector('[data-details-key]').textContent = row.key;

        var list = document.querySelector('[data-details-routes]');
        list.innerHTML = '';
        (row.routes || []).forEach(function (name) {
            var item = document.createElement('li');
            item.textContent = name + '.*';
            list.appendChild(item);
        });

        open('feature-details-modal');
    });

    // ---------------------------------------------------- Audit
    root.addEventListener('click', function (e) {
        var button = e.target.closest('[data-feature-audit]');
        if (!button) { return; }
        var row = rowOf(button);
        var body = document.querySelector('[data-audit-body]');

        body.textContent = TEXT.loading;
        open('feature-audit-modal');

        fetch(URLS.audit + '?key=' + encodeURIComponent(row.key), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.rows || !data.rows.length) { body.textContent = TEXT.audit_empty; return; }
                body.innerHTML = '';
                data.rows.forEach(function (log) {
                    var line = document.createElement('div');
                    line.className = 'text-xs py-2';
                    line.style.borderTop = '1px solid var(--border)';
                    line.textContent = log.at + ' · ' + (log.by || '—') + ' · ' + log.action + (log.reason ? ' — ' + log.reason : '');
                    body.appendChild(line);
                });
            })
            .catch(function () { body.textContent = TEXT.error; });
    });

    // ---------------------------------------------------- ↺ لميزة واحدة
    root.addEventListener('click', function (e) {
        var button = e.target.closest('[data-feature-reset]');
        if (!button) { return; }
        var row = rowOf(button);
        post(URLS.reset, { key: row.key }).then(function (r) {
            if (r.ok) { window.location.reload(); } else { alert(r.data.message || TEXT.error); }
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close]')) {
            var modal = e.target.closest('[data-modal]');
            if (modal) { close(modal.id); }
        }
    });
})();
</script>
@endpush
