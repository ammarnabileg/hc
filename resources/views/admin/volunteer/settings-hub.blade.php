@extends('layouts.admin')

@section('title', setting('admin.volunteer.settings_hub.title', 'الإدارة المركزيّة للتطوّع'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.settings_hub.title', 'الإدارة المركزيّة للتطوّع')"
        :subtitle="setting('admin.volunteer.settings_hub.subtitle', 'مرجعٌ واحد لكلّ أرقام منظومة التطوّع — Rep · VXP · التقييم · Kudos · الاعتراضات · الترقّي · السلوك · النوافذ · النصوص.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.org.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.settings_hub.title', 'الإدارة المركزيّة للتطوّع')]]">
        <x-slot:action>
            @if ($canManage)
                <button type="button" data-modal-open="hub-audit-modal"
                        class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.volunteer.settings_hub.audit_log', 'سجلّ التدقيق') }}</button>
            @endif
            <span class="text-xs" style="color: var(--text-muted)">
                @if ($lastChange)
                    {{ strtr(setting('admin.volunteer.settings_hub.last_change', 'آخر تعديل: :name · :when'), [
                        ':name' => $lastChange->user->name ?? '—',
                        ':when' => $lastChange->created_at->diffForHumans(),
                    ]) }}
                @else
                    {{ setting('admin.volunteer.settings_hub.no_changes_yet', 'مفيش تعديل مسجَّل بعد') }}
                @endif
            </span>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'settings-hub'])

    @unless ($canManage)
        <div class="card p-3 mb-4 text-sm flex items-center gap-2" style="border-color: color-mix(in srgb, var(--text-muted) 40%, var(--border))">
            <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken); color: var(--text-muted)">{{ setting('admin.volunteer.settings_hub.read_only_badge', 'عرض فقط') }}</span>
            <span style="color: var(--text-muted)">{{ setting('admin.volunteer.settings_hub.read_only_notice', 'تملك صلاحيّة العرض فقط — الحفظ والـReset لمن يملك صلاحيّة الإدارة.') }}</span>
        </div>
    @endunless

    <form method="post" action="{{ route('admin.volunteer.settings-hub.save-all') }}" id="hub-form">
        @csrf

        <div class="card p-3 mb-4 flex flex-wrap items-center gap-3">
            <input type="search" id="hub-search" placeholder="{{ setting('admin.volunteer.settings_hub.search_placeholder', 'بحث بالاسم أو الـKey…') }}"
                   class="rounded-xl px-3 py-2 text-sm flex-1 min-w-0" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <label class="flex items-center gap-2 text-sm shrink-0">
                <input type="checkbox" id="hub-modified-only">
                {{ setting('admin.volunteer.settings_hub.modified_only', 'المعدَّل عن الافتراضيّ فقط') }}
            </label>
            @if ($canManage)
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard shrink-0"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.settings_hub.save_all', 'حفظ الكلّ') }}</button>
            @endif
        </div>

        <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)" role="tablist">
            <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar">
                @foreach ($tabs as $key => $tab)
                    <button type="button" data-hub-tab="{{ $key }}" role="tab"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard hub-tab-btn"
                            style="{{ $loop->first
                                ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                                : 'background: var(--surface-raised); color: var(--text)' }}">{{ $tab['label'] }}</button>
                @endforeach
            </div>
        </div>

        @foreach ($tabs as $key => $tab)
            <div data-hub-panel="{{ $key }}" class="card p-4 md:p-5" @if (! $loop->first) style="display:none" @endif>
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h2 class="font-bold">{{ $tab['label'] }}</h2>
                    @if ($canManage && count($tab['rows']))
                        <button type="submit" formaction="{{ route('admin.volunteer.settings-hub.reset-tab', ['tab' => $key]) }}"
                                class="text-xs underline shrink-0" style="color: var(--text-muted)">{{ setting('admin.volunteer.settings_hub.reset_tab', '↺ رجّع هذا التاب للافتراضيّ') }}</button>
                    @endif
                </div>

                @if (empty($tab['rows']))
                    <div class="p-6 text-center text-sm" style="color: var(--text-muted)">
                        {{ setting('admin.volunteer.settings_hub.empty_state', 'لم تُضبَط إعدادات هذا التاب بعد — تعمل بالقيم الافتراضيّة.') }}
                    </div>
                @else
                    @foreach ($tab['rows'] as $row)
                        @include('admin.volunteer.partials.hub-setting-field', ['row' => $row, 'canManage' => $canManage])
                    @endforeach
                @endif
            </div>
        @endforeach
    </form>

    @if ($canManage)
        <x-modal id="hub-override-modal" :title="setting('admin.volunteer.settings_hub.override_title', 'Override لكيان')">
            <form method="post" action="{{ route('admin.volunteer.settings-hub.override.save') }}">
                @csrf
                <p class="text-sm font-semibold mb-3" id="hub-override-field-label"></p>
                <input type="hidden" name="key" id="hub-override-key">

                <label class="block text-sm font-semibold mb-1" for="hub-ov-entity">{{ setting('admin.volunteer.org.alkyan', 'الكيان') }}</label>
                <select name="entity_id" id="hub-ov-entity" required class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="hub-ov-value">{{ setting('admin.volunteer.org.alqyma_aljdyda', 'القيمة الجديدة') }}</label>
                <input type="text" name="value" id="hub-ov-value" required maxlength="255"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="hub-ov-reason">{{ setting('admin.volunteer.org.alsbb_ilzamy', 'السبب (إلزاميّ)') }}</label>
                <textarea name="reason" id="hub-ov-reason" rows="2" required minlength="5" maxlength="300"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.ahfz_aloverride', 'احفظ الـOverride') }}</button>
            </form>
        </x-modal>

        <x-modal id="hub-audit-modal" :title="setting('admin.volunteer.settings_hub.audit_log', 'سجلّ التدقيق')">
            @forelse ($auditLog as $entry)
                <div class="py-2 text-sm" style="border-bottom: 1px solid var(--border)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold">{{ $entry->user->name ?? setting('admin.volunteer.settings_hub.system', 'النظام') }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $entry->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $entry->action }} — <code>{{ data_get($entry->new_values, 'key', '—') }}</code>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.volunteer.settings_hub.no_changes_yet', 'مفيش تعديل مسجَّل بعد') }}</p>
            @endforelse
        </x-modal>
    @endif

    <script>
        (function () {
            const panels = Array.from(document.querySelectorAll('[data-hub-panel]'));
            const tabButtons = Array.from(document.querySelectorAll('[data-hub-tab]'));
            const search = document.getElementById('hub-search');
            const modifiedOnly = document.getElementById('hub-modified-only');

            function showTab(key) {
                panels.forEach((p) => { p.style.display = p.dataset.hubPanel === key ? '' : 'none'; });
                tabButtons.forEach((b) => {
                    const active = b.dataset.hubTab === key;
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                    b.style.background = active ? 'var(--color-brand-500)' : 'var(--surface-raised)';
                    b.style.color = active ? '#04201c' : 'var(--text)';
                    b.style.fontWeight = active ? '700' : '400';
                });
            }

            tabButtons.forEach((b) => b.addEventListener('click', () => showTab(b.dataset.hubTab)));

            function activeTabKey() {
                const active = tabButtons.find((b) => b.getAttribute('aria-selected') === 'true');
                return active ? active.dataset.hubTab : null;
            }

            function applyFilters() {
                const q = (search.value || '').trim().toLowerCase();
                const onlyModified = modifiedOnly.checked;
                const visibleCountByTab = {};

                document.querySelectorAll('[data-hub-row]').forEach((row) => {
                    const label = row.dataset.label || '';
                    const key = (row.dataset.key || '').toLowerCase();
                    const modified = row.dataset.modified === '1';
                    const matches = (! q || label.includes(q) || key.includes(q)) && (! onlyModified || modified);
                    row.style.display = matches ? '' : 'none';

                    if (matches) {
                        const panel = row.closest('[data-hub-panel]');
                        const tabKey = panel ? panel.dataset.hubPanel : null;
                        if (tabKey) visibleCountByTab[tabKey] = (visibleCountByTab[tabKey] || 0) + 1;
                    }
                });

                if (! q && ! onlyModified) return;

                const current = activeTabKey();
                if (current && visibleCountByTab[current]) return;

                const firstMatch = tabButtons.find((b) => visibleCountByTab[b.dataset.hubTab]);
                if (firstMatch) showTab(firstMatch.dataset.hubTab);
            }

            search.addEventListener('input', applyFilters);
            modifiedOnly.addEventListener('change', applyFilters);

            document.querySelectorAll('[data-hub-override-key]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.getElementById('hub-override-key').value = btn.dataset.hubOverrideKey;
                    document.getElementById('hub-override-field-label').textContent = btn.dataset.hubOverrideLabel;
                });
            });
        })();
    </script>
@endsection
