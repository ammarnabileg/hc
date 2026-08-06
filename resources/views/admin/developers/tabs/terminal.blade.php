@php
    $u = auth()->user();
    $terminalEnabled = $data['enabled'];
@endphp

{{--
    🧩 المطوّرين — تاب «الطرفيّة» (12.15-هـ · v5.6، سجلّ القرارات 25).

    ⛔ مالك المنصّة حصرًا (يُحرَس أصلًا في `DevelopersController::index()` وفي
    مسار `admin.developers.terminal.run` — هذا العرض بلا حارسٍ مضاعَف بلا فائدة).
    ⛔ **لا Whitelist ولا Blacklist على محتوى الأمر** — أمر المالك المباشر
    «اسمح بكلّ الأوامر». القيد الأمنيّ الوحيد: **كلّ أمرٍ مسجَّل** (الجدول أسفله)
    + مهلة تنفيذٍ زمنيّة (لا محتوى) تمنع تعليق الطلب للأبد.
--}}

{{-- تحذيرٌ واضح أعلى الصفحة (12.15-هـ) --}}
<div class="card p-4 mb-4" style="border: 1px solid var(--color-state-danger); background: color-mix(in srgb, var(--color-state-danger) 8%, transparent)">
    <div class="flex items-start gap-3">
        <span aria-hidden="true" class="text-xl leading-none">⚠️</span>
        <div class="min-w-0">
            <div class="font-bold" style="color: var(--color-state-danger)">
                {{ setting('developers.admin.terminal_warning_title', 'تحذير: تنفيذٌ مباشر على الخادم') }}
            </div>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {{ setting('developers.admin.terminal_warning_body', 'أيّ أمرٍ هنا يُنفَّذ مباشرةً على الخادم — لا قيود ولا تراجع، وكلّ أمرٍ مسجَّل.') }}
            </p>
        </div>
    </div>
</div>

@if (! $terminalEnabled)
    <div class="card p-4 mb-4">
        <x-empty :message="setting('developers.admin.terminal_disabled_msg', 'تاب الطرفيّة معطَّل حاليًّا من الإعدادات.')" />
    </div>
@else
    {{-- صندوق إدخال الأمر + التنفيذ --}}
    <section class="card p-4 md:p-5 mb-4">
        <form id="terminal-form" class="grid gap-3">
            <label class="text-sm" for="terminal-command">
                {{ setting('developers.admin.terminal_field_command', 'الأمر') }}
            </label>
            <textarea id="terminal-command" rows="2" maxlength="4000"
                      placeholder="{{ setting('developers.admin.terminal_command_placeholder', 'اكتب أمر الطرفيّة هنا…') }}"
                      class="block w-full rounded-xl px-3 py-2 text-sm font-mono"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                      autocomplete="off" spellcheck="false"></textarea>

            <div>
                <button id="terminal-run-btn" type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('developers.admin.terminal_run_cta', 'تنفيذ') }}
                </button>
            </div>
        </form>
    </section>

    {{-- منطقة عرض المخرَجات — طرفيّة حقيقيّة (Monospace + خلفيّة داكنة) --}}
    <section class="card p-4 md:p-5 mb-4">
        <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
            <h2 class="font-bold">{{ setting('developers.admin.terminal_output_title', 'المخرَجات') }}</h2>
            <span id="terminal-meta" class="text-xs" style="color: var(--text-muted)"></span>
        </div>
        <pre id="terminal-output" class="rounded-xl p-3 text-xs overflow-x-auto"
             style="background: #0b0f0d; color: #d6ffe8; min-height: 4rem; white-space: pre-wrap; word-break: break-all; font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace">{{ setting('developers.admin.terminal_output_empty', 'لا مخرَجات بعد — نفّذ أمرًا لعرضها هنا.') }}</pre>
    </section>

    {{-- سجلّ الأوامر السابقة (آخر 200) --}}
    <section class="card p-0 overflow-hidden" id="terminal-log-section">
        <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.terminal_log_title', 'سجلّ الأوامر (آخر 200)') }}</h2>

        <div id="terminal-log-empty" class="p-4" style="{{ $data['logs']->isEmpty() ? '' : 'display:none' }}">
            <x-empty :message="setting('developers.admin.terminal_log_empty', 'لا أوامر منفَّذة بعد.')" />
        </div>

        <div class="overflow-x-auto min-w-0" id="terminal-log-wrap" style="{{ $data['logs']->isEmpty() ? 'display:none' : '' }}">
            <table class="w-full text-sm" id="terminal-log-table">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.terminal_col_time', 'الوقت') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.terminal_col_user', 'مَن نفّذ') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.terminal_col_command', 'الأمر') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.terminal_col_exit_code', 'كود الخروج') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.terminal_col_duration', 'المدّة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_actions', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody id="terminal-log-tbody">
                    @foreach ($data['logs'] as $log)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 text-xs">{{ $log->created_at?->format('Y/m/d H:i:s') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $log->user?->name ?? '—' }} @if($log->user?->code)<code class="opacity-60">#{{ $log->user->code }}</code>@endif</td>
                            <td class="px-4 py-3 text-xs"><code style="word-break: break-all">{{ \Illuminate\Support\Str::limit($log->command, 80) }}</code></td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="((int) $log->exit_code === 0) ? 'ok' : 'danger'" :label="(string) $log->exit_code" />
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $log->duration_ms }}ms</td>
                            <td class="px-4 py-3 text-xs">
                                <details>
                                    <summary class="cursor-pointer underline">{{ setting('developers.admin.terminal_view_output_cta', 'عرض المخرَجات') }}</summary>
                                    <pre class="mt-2 rounded-xl p-2 whitespace-pre-wrap break-all"
                                         style="background: var(--surface-sunken); max-width: 420px; font-family: ui-monospace, monospace">{{ $log->output }}</pre>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (function () {
            var form = document.getElementById('terminal-form');
            if (!form) return;

            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var CSRF = tokenMeta ? tokenMeta.getAttribute('content') : '';
            var input = document.getElementById('terminal-command');
            var runBtn = document.getElementById('terminal-run-btn');
            var outputBox = document.getElementById('terminal-output');
            var metaBox = document.getElementById('terminal-meta');

            var LABEL_RUN = {{ Js::from((string) setting('developers.admin.terminal_run_cta', 'تنفيذ')) }};
            var LABEL_RUNNING = {{ Js::from((string) setting('developers.admin.terminal_running_label', 'جارٍ التنفيذ…')) }};
            var LABEL_ERROR = {{ Js::from((string) setting('developers.admin.terminal_error_generic', 'تعذّر تنفيذ الأمر — حاول ثانيةً.')) }};
            var LABEL_EXIT = {{ Js::from((string) setting('developers.admin.terminal_exit_code_label', 'كود الخروج')) }};
            var LABEL_DURATION = {{ Js::from((string) setting('developers.admin.terminal_duration_label', 'المدّة')) }};
            var LABEL_VIEW_OUTPUT = {{ Js::from((string) setting('developers.admin.terminal_view_output_cta', 'عرض المخرَجات')) }};
            var RUN_URL = {{ Js::from(route('admin.developers.terminal.run')) }};
            var ACTOR_NAME = {{ Js::from((string) ($u->name ?? '')) }};
            var ACTOR_CODE = {{ Js::from((string) ($u->code ?? '')) }};
            var ICON_OK = {{ Js::from((string) setting('ux.state.ok.icon', '●')) }};
            var ICON_DANGER = {{ Js::from((string) setting('ux.state.danger.icon', '◉')) }};

            /** يُضيف صفًّا جديدًا أعلى جدول السجلّ فورًا — بلا إعادة تحميل الصفحة (يفقد المخرَجات المعروضة للتوّ) */
            function prependLogRow(command, exitCode, durationMs, output) {
                var emptyBox = document.getElementById('terminal-log-empty');
                var wrap = document.getElementById('terminal-log-wrap');
                var tbody = document.getElementById('terminal-log-tbody');
                if (!tbody) return;

                if (emptyBox) emptyBox.style.display = 'none';
                if (wrap) wrap.style.display = '';

                var tr = document.createElement('tr');
                tr.style.borderTop = '1px solid var(--border)';

                var tdTime = document.createElement('td');
                tdTime.className = 'px-4 py-3 text-xs';
                tdTime.textContent = new Date().toLocaleString('sv-SE');
                tr.appendChild(tdTime);

                var tdUser = document.createElement('td');
                tdUser.className = 'px-4 py-3 text-xs';
                tdUser.textContent = ACTOR_NAME + (ACTOR_CODE ? ' #' + ACTOR_CODE : '');
                tr.appendChild(tdUser);

                var tdCmd = document.createElement('td');
                tdCmd.className = 'px-4 py-3 text-xs';
                var code = document.createElement('code');
                code.style.wordBreak = 'break-all';
                code.textContent = command.length > 80 ? command.slice(0, 80) + '…' : command;
                tdCmd.appendChild(code);
                tr.appendChild(tdCmd);

                var tdExit = document.createElement('td');
                tdExit.className = 'px-4 py-3';
                var badge = document.createElement('span');
                badge.className = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs';
                var ok = exitCode === 0;
                badge.style.background = 'color-mix(in srgb, var(--color-state-' + (ok ? 'ok' : 'danger') + ') 15%, transparent)';
                badge.style.color = 'var(--color-state-' + (ok ? 'ok' : 'danger') + ')';
                badge.textContent = (ok ? ICON_OK : ICON_DANGER) + ' ' + String(exitCode);
                tdExit.appendChild(badge);
                tr.appendChild(tdExit);

                var tdDuration = document.createElement('td');
                tdDuration.className = 'px-4 py-3 text-xs';
                tdDuration.textContent = durationMs + 'ms';
                tr.appendChild(tdDuration);

                var tdActions = document.createElement('td');
                tdActions.className = 'px-4 py-3 text-xs';
                var details = document.createElement('details');
                var summary = document.createElement('summary');
                summary.className = 'cursor-pointer underline';
                summary.textContent = LABEL_VIEW_OUTPUT;
                var pre = document.createElement('pre');
                pre.className = 'mt-2 rounded-xl p-2 whitespace-pre-wrap break-all';
                pre.style.background = 'var(--surface-sunken)';
                pre.style.maxWidth = '420px';
                pre.style.fontFamily = 'ui-monospace, monospace';
                pre.textContent = output;
                details.appendChild(summary);
                details.appendChild(pre);
                tdActions.appendChild(details);
                tr.appendChild(tdActions);

                tbody.insertBefore(tr, tbody.firstChild);
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var command = input.value.trim();
                if (!command) return;

                runBtn.disabled = true;
                runBtn.textContent = LABEL_RUNNING;
                metaBox.textContent = '';

                fetch(RUN_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ command: command })
                })
                    .then(function (res) {
                        return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                    })
                    .then(function (result) {
                        runBtn.disabled = false;
                        runBtn.textContent = LABEL_RUN;

                        if (!result.ok) {
                            outputBox.textContent = (result.body && result.body.message) ? result.body.message : LABEL_ERROR;
                            return;
                        }

                        outputBox.textContent = result.body.output || '';
                        metaBox.textContent = LABEL_EXIT + ': ' + result.body.exit_code + '  ·  ' + LABEL_DURATION + ': ' + result.body.duration_ms + 'ms';

                        // انعكاسٌ فوريّ في جدول السجلّ بصفٍّ جديد أعلاه — بلا إعادة تحميل
                        // الصفحة (كانت ستمحو المخرَجات المعروضة للتوّ في outputBox أعلاه)
                        prependLogRow(command, result.body.exit_code, result.body.duration_ms, result.body.output || '');

                        input.value = '';
                    })
                    .catch(function () {
                        runBtn.disabled = false;
                        runBtn.textContent = LABEL_RUN;
                        outputBox.textContent = LABEL_ERROR;
                    });
            });
        })();
    </script>
@endif
