@php
    /**
     * حقل إعداد واحد داخل الإدارة المركزيّة (24.2 · 13.4-ك) — نظير
     * setting-field.blade.php لكن بإجراء صفٍّ إضافيّ: **Reset للحقل**
     * (formaction بلا فورم متداخل)، وقفلٌ كامل حين لا تملك الصلاحيّة
     * (بلا صلاحيّة = عرض فقط — 24.2 «الحالات»).
     * $row: ['key','label','type','default','value','modified']
     */
    $canManage = $canManage ?? false;
    $id = 'hub-set-'.str_replace(['.', '_'], '-', $row['key']);
@endphp

<div class="py-3" style="border-bottom: 1px solid var(--border)"
     data-hub-row data-key="{{ $row['key'] }}" data-label="{{ mb_strtolower($row['label']) }}" data-modified="{{ $row['modified'] ? '1' : '0' }}">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <label for="{{ $id }}" class="text-sm font-semibold">
            {{ $row['label'] }}
            @if ($row['modified'])
                <span class="text-xs rounded-full px-2 py-0.5 align-middle"
                      style="background: color-mix(in srgb, var(--color-state-warn) 15%, transparent); color: var(--color-state-warn)">{{ setting('admin.volunteer.partials.setting_field.madl', '▲ معدَّل') }}</span>
            @endif
        </label>

        <div class="flex items-center gap-2">
            <code class="text-xs" style="color: var(--text-muted)">{{ $row['key'] }}</code>
            @if ($canManage)
                <button type="button" data-modal-open="hub-override-modal"
                        data-hub-override-key="{{ $row['key'] }}" data-hub-override-label="{{ $row['label'] }}"
                        class="text-xs underline" style="color: var(--text-muted)">{{ setting('admin.volunteer.partials.hub_setting_field.override_lkyan', 'Override لكيان') }}</button>
                <button type="submit" formaction="{{ route('admin.volunteer.settings-hub.reset-field') }}"
                        name="key" value="{{ $row['key'] }}"
                        class="text-xs underline" style="color: var(--text-muted)"
                        title="{{ setting('admin.volunteer.partials.hub_setting_field.rja_llhql', 'رجّع الحقل للافتراضيّ') }}">↺</button>
            @endif
        </div>
    </div>

    <div class="mt-2">
        @if ($row['type'] === 'bool')
            <select id="{{ $id }}" name="settings[{{ $row['key'] }}]" @disabled(! $canManage)
                    class="w-full md:w-56 rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="1" @selected((string) $row['value'] === '1')>{{ setting('admin.volunteer.partials.setting_field.mfal', 'مفعَّل') }}</option>
                <option value="0" @selected((string) $row['value'] !== '1')>{{ setting('admin.volunteer.partials.setting_field.mwqwf', 'موقوف') }}</option>
            </select>
        @elseif ($row['type'] === 'json' || $row['type'] === 'text')
            <textarea id="{{ $id }}" name="settings[{{ $row['key'] }}]" rows="3" @disabled(! $canManage)
                      class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $row['value'] }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $row['type'] === 'number' ? 'number' : 'text' }}" step="any"
                   name="settings[{{ $row['key'] }}]" value="{{ $row['value'] }}" @disabled(! $canManage)
                   class="w-full md:w-72 rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endif
    </div>

    <div class="mt-1 text-xs" style="color: var(--text-muted); overflow-wrap: anywhere">{{ setting('admin.volunteer.partials.setting_field.alaftrady', 'الافتراضيّ:') }} {{ \Illuminate\Support\Str::limit($row['default'], 90) }}</div>
</div>
